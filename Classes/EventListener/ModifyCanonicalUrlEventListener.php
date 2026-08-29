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

namespace Michaelstaatz\FastBlog\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Seo\Event\ModifyUrlForCanonicalTagEvent;

/**
 * TYPO3's default canonical URL generator only reflects "loose" query parameters -
 * once a value is absorbed into a route enhancer's PageArguments (as ours are, via
 * StaticRangeMapper/PersistedAliasMapper - see the site's config.yaml),
 * it becomes invisible to that logic and the canonical silently falls back to the bare
 * page URL. That's wrong here: a paginated and/or tag-filtered list view is genuinely
 * different content, not a duplicate of the unfiltered page 1.
 *
 * Fix: for requests that resolved through our plugin, use the actual request URI
 * (already reflects the pretty, route-enhanced path) as the canonical, just stripping
 * cHash - an internal cache-validation artifact with no SEO meaning.
 */
#[AsEventListener(identifier: 'fastblog/modify-canonical-url')]
final class ModifyCanonicalUrlEventListener
{
    private const PLUGIN_NAMESPACE = 'tx_fastblog_bloglist';

    public function __invoke(ModifyUrlForCanonicalTagEvent $event): void
    {
        $request = $event->getRequest();
        $pageArguments = $request->getAttribute('routing');
        if (!$pageArguments instanceof PageArguments || !isset($pageArguments->getArguments()[self::PLUGIN_NAMESPACE])) {
            return;
        }

        $uri = $request->getUri();
        parse_str($uri->getQuery(), $queryParams);
        unset($queryParams['cHash']);
        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $event->setUrl((string) $uri->withQuery($query)->withFragment(''));
    }
}
