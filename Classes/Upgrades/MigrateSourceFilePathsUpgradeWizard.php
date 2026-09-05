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

namespace Michaelstaatz\FastBlog\Upgrades;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\RepeatableInterface;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * fastblog:import used to store "source_file" as an absolute filesystem path.
 * It now stores a path relative to the docroot (Environment::getPublicPath())
 * instead, so the database stays portable across systems with a different
 * docroot - e.g. copying it between environments. This wizard rewrites
 * already-stored absolute values once.
 *
 * Marked repeatable on purpose: unlike a typical one-off schema migration,
 * this may need to run again whenever the database is copied to a system
 * with a different docroot, not just once ever.
 */
#[UpgradeWizard('fastBlogMigrateSourceFilePaths')]
final class MigrateSourceFilePathsUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const TABLE = 'tx_fastblog_domain_model_blogpost';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'fast_blog: Migrate "source_file" to relative paths';
    }

    public function getDescription(): string
    {
        return 'Rewrites absolute "source_file" paths in tx_fastblog_domain_model_blogpost to paths relative '
            . 'to the docroot, so re-importing still recognizes existing posts after moving the database to a '
            . 'system with a different docroot.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->findAbsoluteRows() !== [];
    }

    public function executeUpdate(): bool
    {
        $publicPath = rtrim(Environment::getPublicPath(), '/');

        foreach ($this->findAbsoluteRows() as $row) {
            $relativeFile = ltrim(substr((string) $row['source_file'], strlen($publicPath)), '/');

            $updateBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
            $updateBuilder->update(self::TABLE)
                ->where($updateBuilder->expr()->eq('uid', $updateBuilder->createNamedParameter((int) $row['uid'], ParameterType::INTEGER)))
                ->set('source_file', $relativeFile)
                ->executeStatement();

            $this->output->writeln(sprintf('uid %d: %s -> %s', $row['uid'], $row['source_file'], $relativeFile));
        }

        return true;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * @return array<int, array{uid: int, source_file: string}>
     */
    private function findAbsoluteRows(): array
    {
        $publicPath = rtrim(Environment::getPublicPath(), '/');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $rows = $queryBuilder->select('uid', 'source_file')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string) $row['source_file'] !== '' && str_starts_with((string) $row['source_file'], $publicPath),
        ));
    }
}
