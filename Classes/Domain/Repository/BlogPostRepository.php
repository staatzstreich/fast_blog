<?php

declare(strict_types=1);

/*
 * This file is part of the Michaelstaatz TYPO3 extensions.
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

namespace Michaelstaatz\FastBlog\Domain\Repository;

use Michaelstaatz\FastBlog\Domain\Model\BlogPost;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<BlogPost>
 */
class BlogPostRepository extends Repository
{
    protected $defaultOrderings = [
        'pubDate' => QueryInterface::ORDER_DESCENDING,
    ];

    public function initializeObject(): void
    {
        // The table only ever holds imported blog posts, all living in one dedicated
        // sysfolder - respecting the current page's storage pid would make the plugin
        // (placed on the "blog" page) miss records unless that folder is a direct child,
        // so query across the whole site regardless of storage location instead.
        $querySettings = $this->createQuery()->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * All distinct categories actually used by a visible post, sorted alphabetically -
     * dataset is small (personal blog), so aggregating in PHP over the already-fetched
     * post list is simpler than a dedicated MM-aware query.
     *
     * @return Category[]
     */
    public function findDistinctCategories(): array
    {
        $categories = [];
        foreach ($this->findAll() as $post) {
            foreach ($post->getCategories() as $category) {
                $categories[$category->getUid()] = $category;
            }
        }
        uasort($categories, static fn(Category $a, Category $b): int => strnatcasecmp($a->getTitle(), $b->getTitle()));

        return array_values($categories);
    }
}
