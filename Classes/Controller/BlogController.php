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

namespace Michaelstaatz\FastBlog\Controller;

use Doctrine\DBAL\ParameterType;
use Michaelstaatz\FastBlog\Domain\Model\BlogPost;
use Michaelstaatz\FastBlog\Domain\Repository\BlogPostRepository;
use Michaelstaatz\FastBlog\Service\ExtensionSettings;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Renders the imported BlogPost records - list (newest first, paginated, tag
 * filter) and detail view. Pagination/tag filtering happens over a plain PHP
 * array (ArrayPaginator) rather than SQL, since a personal blog's post count
 * never justifies the extra query complexity.
 */
final class BlogController extends ActionController
{
    /**
     * Route argument namespace of the registered plugin
     * (configurePlugin('FastBlog', 'Bloglist') => tx_fastblog_bloglist).
     */
    private const PLUGIN_NAMESPACE = 'tx_fastblog_bloglist';

    public function __construct(
        private readonly BlogPostRepository $blogPostRepository,
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionSettings $extensionSettings,
    ) {}

    public function listAction(?int $category = null, int $page = 1): ResponseInterface
    {
        $posts = iterator_to_array($this->blogPostRepository->findAll());
        if ($category !== null) {
            $posts = array_values(array_filter(
                $posts,
                static function (BlogPost $post) use ($category): bool {
                    foreach ($post->getCategories() as $postCategory) {
                        if ($postCategory->getUid() === $category) {
                            return true;
                        }
                    }

                    return false;
                },
            ));
        }

        // The route requirement only enforces "\d+", so "0" is still a valid input -
        // ArrayPaginator rejects offsets < 1 with an exception. Clamp instead.
        $page = max(1, $page);

        $paginator = new ArrayPaginator($posts, $page, $this->extensionSettings->getPostsPerPage());
        $pagination = new SimplePagination($paginator);
        $this->view->assignMultiple([
            'posts' => $paginator->getPaginatedItems(),
            'pagination' => $pagination,
            'paginator' => $paginator,
            'blogCategories' => $this->blogPostRepository->findDistinctCategories(),
            'currentCategory' => $category,
        ]);

        return $this->htmlResponse();
    }

    public function showAction(BlogPost $post): ResponseInterface
    {
        $this->view->assignMultiple([
            'post' => $post,
            'translations' => $this->findTranslations($post),
        ]);

        return $this->htmlResponse();
    }

    /**
     * Resolves every available translation of the given post (DE<->EN<->..., linked
     * via l10n_parent) as a list for the language switcher, bypassing Extbase's
     * language overlay by querying the raw rows directly. Deliberately looked up by
     * slug, not uid: Extbase's language overlay keeps getUid() pinned to the
     * default-language record's uid even when the object's other fields (title,
     * slug, ...) are correctly overlaid with the translated values, so uid alone
     * can't tell us which language row is actually being displayed - the slug can.
     *
     * @return array<string, mixed>[] each with title, languageId and url
     */
    private function findTranslations(BlogPost $post): array
    {
        $table = 'tx_fastblog_domain_model_blogpost';
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction())->add(new HiddenRestriction());

        $current = $queryBuilder->select('uid', 'sys_language_uid', 'l10n_parent')
            ->from($table)
            ->where($queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($post->getSlug())))
            ->executeQuery()
            ->fetchAssociative();

        if ($current === false) {
            return [];
        }

        // Default language displayed: all translations belong to this record.
        // Translation displayed: the default-language record plus all sibling
        // translations of the same parents - the current row itself is excluded.
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction())->add(new HiddenRestriction());

        if ((int) $current['sys_language_uid'] === 0) {
            $counterparts = $queryBuilder->select('uid', 'sys_language_uid')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($current['uid'], ParameterType::INTEGER)),
                    $queryBuilder->expr()->neq('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                )
                ->executeQuery()
                ->fetchAllAssociative();
        } elseif ((int) $current['l10n_parent'] > 0) {
            $counterparts = $queryBuilder->select('uid', 'sys_language_uid')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($current['l10n_parent'], ParameterType::INTEGER)),
                        $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($current['l10n_parent'], ParameterType::INTEGER)),
                    ),
                    $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($current['uid'], ParameterType::INTEGER)),
                )
                ->executeQuery()
                ->fetchAllAssociative();
        } else {
            $counterparts = [];
        }

        if ($counterparts === []) {
            return [];
        }

        $site = $this->request->getAttribute('site');
        if (!$site instanceof Site) {
            return [];
        }

        // Build the URLs through the framework instead of hand-assembling them from
        // getBase(): the target language can carry its own domain/path prefix and a
        // configured route enhancer turns the "post" argument into the pretty slug
        // URL - without this code having to assume the blog lives under "/blog/".
        // Target page is the current page: the plugin's page uid is the same in
        // every language (translated page, not a copy).
        $translations = [];
        foreach ($counterparts as $counterpart) {
            $counterpartLanguageId = (int) $counterpart['sys_language_uid'];

            // The post may exist in a language the site is not configured for (the
            // import is frontend-agnostic) - linking there would throw in PageLinkBuilder.
            try {
                $language = $site->getLanguageById($counterpartLanguageId);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $translations[] = [
                'languageId' => $counterpartLanguageId,
                'title' => $language->getTitle(),
                'hreflang' => $language->getHreflang(),
                'url' => (string) $site->getRouter()->generateUri(
                    $this->request->getAttribute('routing')->getPageId(),
                    [
                        '_language' => $language,
                        self::PLUGIN_NAMESPACE => [
                            'post' => (int) $counterpart['uid'],
                            'action' => 'show',
                            'controller' => 'Blog',
                        ],
                    ],
                ),
            ];
        }

        return $translations;
    }
}
