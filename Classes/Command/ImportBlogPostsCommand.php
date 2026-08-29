<?php

declare(strict_types=1);

/*
 * This file is part of the michaelstaatz/fast-blog extension.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Michaelstaatz\FastBlog\Command;

use Doctrine\DBAL\ParameterType;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Michaelstaatz\FastBlog\Service\ExtensionSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Imports Markdown files (with YAML frontmatter) dropped into the configured
 * directory (e.g. fileadmin/blog_posts/) as tx_fastblog_domain_model_blogpost
 * records, so TYPO3 can render them itself. Writes go through the raw
 * QueryBuilder (not the Extbase repository/PersistenceManager) to keep pid,
 * hidden, sys_language_uid and l10n_parent fully explicit and side-step
 * Extbase persistence quirks for what is essentially a data-import script.
 *
 * Language handling needs no configuration: the frontmatter "lang" two-letter
 * code is resolved against the ISO codes of the languages configured in the
 * site configuration (config/sites/*). Unknown codes fall back to the default
 * language (uid 0).
 *
 * Idempotent: re-running matches existing records by "source_file" and
 * updates them instead of creating duplicates.
 */
#[AsCommand(
    name: 'fastblog:import',
    description: 'Imports Markdown files from the configured directory as BlogPost records TYPO3 can render.',
)]
final class ImportBlogPostsCommand extends Command
{
    private const TABLE = 'tx_fastblog_domain_model_blogpost';
    private const CATEGORY_TABLE = 'sys_category';
    private const CATEGORY_MM_TABLE = 'sys_category_record_mm';

    /** @var array<int, string>|null language uid => normalized two-letter ISO code, lazily built */
    private ?array $siteLanguages = null;

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $storagePid = $this->settings->getBlogStoragePid();
        if ($storagePid <= 0) {
            $io->error('No "blogStoragePid" configured (fast_blog extension settings). Please create the sysfolder for blog posts and put its page UID there.');

            return Command::FAILURE;
        }

        $directory = rtrim($this->settings->getOutputDirectory(), '/');
        $files = glob($directory . '/*.md') ?: [];
        if ($files === []) {
            $io->warning(sprintf('No Markdown files found in "%s".', $directory));

            return Command::SUCCESS;
        }

        $converter = $this->createMarkdownConverter();
        $imported = 0;
        $updated = 0;
        $translationPosts = [];

        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                $io->warning(sprintf('Could not read file: %s', $file));
                continue;
            }

            $parsed = $this->splitFrontmatter($raw);
            if ($parsed === null) {
                $io->warning(sprintf('No valid YAML frontmatter in: %s', $file));
                continue;
            }
            [$frontmatter, $body] = $parsed;

            $languageUid = $this->resolveLanguageUid((string) ($frontmatter['lang'] ?? ''));
            $translationKey = (string) ($frontmatter['translationKey'] ?? '');
            $title = (string) ($frontmatter['title'] ?? basename($file));
            $hiddenState = !empty($frontmatter['draft']) ? 1 : 0;

            $data = [
                'pid' => $storagePid,
                'title' => $title,
                'slug' => $this->resolveSlug($title, $file),
                'pub_date' => $this->parseDate($frontmatter['pubDate'] ?? null),
                'description' => (string) ($frontmatter['description'] ?? ''),
                'meta_description' => (string) ($frontmatter['meta_description'] ?? ''),
                'focus_keywords' => implode(', ', (array) ($frontmatter['focus_keywords'] ?? [])),
                'author' => (string) ($frontmatter['author'] ?? ''),
                'bodytext' => $body,
                'content_html' => (string) $converter->convert($body),
                'source_file' => $file,
                'translation_key' => $translationKey,
                'sys_language_uid' => $languageUid,
                'l10n_parent' => 0,
            ];

            if ($languageUid !== 0 && $translationKey !== '') {
                $parentUid = $this->findTranslationParentUid($translationKey);
                if ($parentUid !== null) {
                    $data['l10n_parent'] = $parentUid;
                }
            }

            $tags = array_map('strval', (array) ($frontmatter['tags'] ?? []));
            $categoryUids = $this->resolveCategoryUids($tags);

            $existingUid = $this->findExistingUid($file);
            if ($existingUid !== null) {
                // "hidden" is a backend editorial state - re-importing a file
                // must not silently unhide (or hide) a record an editor toggled.
                unset($data['hidden']);
                $this->updateRecord($existingUid, $data);
                $blogPostUid = $existingUid;
                $updated++;
                $io->writeln(sprintf('Updated: %s (uid %d)', $title, $existingUid));
            } else {
                $blogPostUid = $this->insertRecord($hiddenState, $data);
                $imported++;
                $io->writeln(sprintf('Imported: %s', $title));
            }

            $this->writeCategoryRelations($blogPostUid, $categoryUids);

            if ($languageUid !== 0 && $translationKey !== '') {
                $translationPosts[] = ['uid' => $blogPostUid, 'translationKey' => $translationKey];
            }
        }

        // Second pass: a translation imported before its default-language counterpart
        // (pure file-order effect, glob()) could not link to it above - re-resolve
        // now that all default-language rows exist.
        foreach ($translationPosts as $translationPost) {
            $parentUid = $this->findTranslationParentUid($translationPost['translationKey']);
            if ($parentUid !== null && $parentUid !== $translationPost['uid']) {
                $queryBuilder = $this->getQueryBuilder();
                $queryBuilder->update(self::TABLE)
                    ->set('l10n_parent', $parentUid)
                    ->where($queryBuilder->expr()->eq('uid', $translationPost['uid']))
                    ->executeStatement();
            }
        }

        $io->success(sprintf('%d new, %d updated blog post(s).', $imported, $updated));

        return Command::SUCCESS;
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    private function splitFrontmatter(string $raw): ?array
    {
        if (!preg_match('/^---\r?\n(.*?)\r?\n---\r?\n\r?\n?(.*)$/s', $raw, $matches)) {
            return null;
        }

        $frontmatter = Yaml::parse($matches[1]);
        if (!is_array($frontmatter)) {
            return null;
        }

        return [$frontmatter, trim($matches[2])];
    }

    private function resolveSlug(string $title, string $currentFile): string
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($title));
        $base = substr($base, 0, 60) ?: 'post';

        $candidate = $base;
        $suffix = 2;
        while ($this->slugTakenByOtherFile($candidate, $currentFile)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugTakenByOtherFile(string $slug, string $currentFile): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        $count = $queryBuilder->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)),
                $queryBuilder->expr()->neq('source_file', $queryBuilder->createNamedParameter($currentFile)),
            )
            ->executeQuery()
            ->fetchOne();

        return (int) $count > 0;
    }

    private function findTranslationParentUid(string $translationKey): ?int
    {
        $queryBuilder = $this->getQueryBuilder();
        $uid = $queryBuilder->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('translation_key', $queryBuilder->createNamedParameter($translationKey)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        return $uid !== false ? (int) $uid : null;
    }

    private function findExistingUid(string $file): ?int
    {
        $queryBuilder = $this->getQueryBuilder();
        $uid = $queryBuilder->select('uid')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('source_file', $queryBuilder->createNamedParameter($file)))
            ->executeQuery()
            ->fetchOne();

        return $uid !== false ? (int) $uid : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertRecord(int $hiddenState, array $data): int
    {
        $data['hidden'] = $hiddenState;
        $data['tstamp'] = time();
        $data['crdate'] = time();
        $this->getQueryBuilder()->insert(self::TABLE)->values($data)->executeStatement();

        return (int) $this->connectionPool->getConnectionForTable(self::TABLE)->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateRecord(int $uid, array $data): void
    {
        $data['tstamp'] = time();
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->update(self::TABLE)->where($queryBuilder->expr()->eq('uid', $uid));
        foreach ($data as $field => $value) {
            $queryBuilder->set($field, $value);
        }
        $queryBuilder->executeStatement();
    }

    /**
     * @param string[] $tags
     * @return int[] sys_category uids
     */
    private function resolveCategoryUids(array $tags): array
    {
        $uids = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            $uids[] = $this->findOrCreateCategory($tag);
        }

        return $uids;
    }

    private function findOrCreateCategory(string $title): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $uid = $queryBuilder->select('uid')
            ->from(self::CATEGORY_TABLE)
            ->where(
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        if ($uid !== false) {
            return (int) $uid;
        }

        $insertBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
        $insertBuilder->insert(self::CATEGORY_TABLE)->values([
            'pid' => 0,
            'tstamp' => time(),
            'crdate' => time(),
            'title' => $title,
        ])->executeStatement();

        return (int) $this->connectionPool->getConnectionForTable(self::CATEGORY_TABLE)->lastInsertId();
    }

    /**
     * Rewrites the categories MM relation for a blog post from scratch (delete + insert),
     * matching the exact row shape TYPO3\CMS\Core\Database\RelationHandler::writeMM() uses
     * for a type=category field with MM_opposite_field ("items") set: uid_local is the
     * category, uid_foreign is our record - the relation direction is inverted compared to
     * a "plain" MM relation.
     *
     * @param int[] $categoryUids
     */
    private function writeCategoryRelations(int $blogPostUid, array $categoryUids): void
    {
        $mmQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $mmQueryBuilder->delete(self::CATEGORY_MM_TABLE)
            ->where(
                $mmQueryBuilder->expr()->eq('uid_foreign', $mmQueryBuilder->createNamedParameter($blogPostUid, ParameterType::INTEGER)),
                $mmQueryBuilder->expr()->eq('tablenames', $mmQueryBuilder->createNamedParameter(self::TABLE)),
                $mmQueryBuilder->expr()->eq('fieldname', $mmQueryBuilder->createNamedParameter('categories')),
            )
            ->executeStatement();

        $sorting = 1;
        foreach ($categoryUids as $categoryUid) {
            $insertBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
            $insertBuilder->insert(self::CATEGORY_MM_TABLE)->values([
                'uid_local' => $categoryUid,
                'uid_foreign' => $blogPostUid,
                'tablenames' => self::TABLE,
                'fieldname' => 'categories',
                'sorting_foreign' => $sorting,
            ])->executeStatement();
            $sorting++;
        }
    }

    /**
     * Symfony's Yaml parser natively recognizes unquoted ISO date scalars (e.g. "pubDate: 2026-07-13")
     * and converts them to a Unix timestamp (int) rather than leaving them as a "Y-m-d" string - only
     * quoted values ("pubDate: '2026-07-13'") survive as plain strings. Must handle both, or an
     * unquoted date silently falls through to the time() fallback below.
     */
    private function parseDate(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && $value !== '') {
            // The "!" resets all format-unspecified parts (i.e. the time) to 0/epoch instead of
            // PHP's default of filling them in with the current system time.
            $date = \DateTime::createFromFormat('!Y-m-d', $value);
            if ($date !== false) {
                return $date->getTimestamp();
            }
        }

        return time();
    }

    /**
     * Resolves the frontmatter "lang" code against the ISO codes of all site
     * languages (first configured site wins on conflict). Unknown or empty codes
     * fall back to the default language (uid 0).
     */
    private function resolveLanguageUid(string $lang): int
    {
        $code = strtolower(substr(trim($lang), 0, 2));
        if ($code !== '') {
            foreach ($this->getSiteLanguages() as $uid => $iso) {
                if ($iso === $code) {
                    return $uid;
                }
            }
        }

        return 0;
    }

    /**
     * @return array<int, string> language uid => two-letter ISO code
     */
    private function getSiteLanguages(): array
    {
        if ($this->siteLanguages === null) {
            $this->siteLanguages = [];
            foreach ($this->siteFinder->getAllSites() as $site) {
                foreach ($site->getAllLanguages() as $siteLanguage) {
                    $code = $siteLanguage->getLocale()?->getLanguageCode() ?? '';
                    if ($code === '' || $code === 'default') {
                        // The default language's typo3Language is literally "default"
                        // (meaning "no translation language"), which is not a usable
                        // ISO code - the hreflang attribute is the next candidate.
                        $code = $siteLanguage->getTypo3Language();
                        if ($code === 'default') {
                            $code = $siteLanguage->getHreflang();
                        }
                    }
                    if ($code === '' || $code === 'default' || $code === '0') {
                        continue;
                    }
                    $this->siteLanguages[$siteLanguage->getLanguageId()] ??= strtolower(substr($code, 0, 2));
                }
            }
        }

        return $this->siteLanguages;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return $queryBuilder;
    }

    private function createMarkdownConverter(): MarkdownConverter
    {
        // The rendered HTML ends up in content_html and is output unescaped by
        // Show.html - blog content is editor-supplied via fileadmin, so raw HTML
        // and unsafe links are stripped/sanitized at import time.
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        return new MarkdownConverter($environment);
    }
}
