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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content',
    'CType',
    'fastblog',
    'LLL:EXT:fast_blog/Resources/Private/Language/locallang_be.xlf:content_element.group.fastblog',
    'after:default',
);

ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_be.xlf:content_element.bloglist.title',
        'description' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_be.xlf:content_element.bloglist.description',
        'value' => 'fastblog_bloglist',
        'icon' => 'content-header',
        'group' => 'fastblog',
    ],
    '
        header,
    ',
    [
        'columnsOverrides' => [
            'frame_class' => [
                'config' => [
                    'default' => 'none',
                ],
            ],
        ],
    ],
);
