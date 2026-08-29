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

namespace Michaelstaatz\FastBlog\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

final class ExtensionSettings
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * Resolves the configured markdown directory (a path relative to the public
     * webroot, e.g. "fileadmin/blog_posts/") to an absolute filesystem path.
     */
    public function getOutputDirectory(): string
    {
        $relativePath = trim((string) $this->get('outputDirectory', 'fileadmin/blog_posts'), '/');

        return rtrim(Environment::getPublicPath(), '/') . '/' . $relativePath;
    }

    public function getBlogStoragePid(): int
    {
        return (int) $this->get('blogStoragePid', 0);
    }

    private function get(string $key, mixed $default): mixed
    {
        try {
            $value = $this->extensionConfiguration->get('fast_blog', $key);
        } catch (\Throwable) {
            return $default;
        }

        return $value === null || $value === '' ? $default : $value;
    }
}
