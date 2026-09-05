<?php

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

$EM_CONF[$_EXTKEY] = [
    'title' => 'Fast Blog',
    'description' => 'Markdown-first blog: posts live in fileadmin as .md files with YAML frontmatter and are imported via a CLI command. List view with pagination and tag filter, detail view with per-post language switcher.',
    'category' => 'plugin',
    'author' => 'Michael Staatz',
    'author_email' => '',
    'state' => 'stable',
    'version' => '1.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'seo' => '14.3.0-14.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Michaelstaatz\\FastBlog\\' => 'Classes/',
        ],
    ],
];
