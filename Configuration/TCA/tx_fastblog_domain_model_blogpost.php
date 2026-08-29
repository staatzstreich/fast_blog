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

return [
    'ctrl' => [
        'title' => 'tx_fastblog_domain_model_blogpost',
        'label' => 'title',
        'label_alt' => 'slug',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'default_sortby' => 'pub_date DESC',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:fast_blog/Resources/Public/Icons/Extension.svg',
        'searchFields' => 'title,description,slug',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                sys_language_uid, l10n_parent, hidden,
                title, slug, pub_date, author,
                description, meta_description,
                categories, focus_keywords,
                --div--:content, bodytext, content_html,
                --div--:meta, source_file, translation_key,
            ',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.title',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'slug' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.slug',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'pub_date' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.pub_date',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'default' => 0,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.description',
            'config' => [
                'type' => 'text',
                'rows' => 3,
                'max' => 512,
            ],
        ],
        'meta_description' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.meta_description',
            'config' => [
                'type' => 'text',
                'rows' => 2,
                'max' => 255,
            ],
        ],
        'categories' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.categories',
            'config' => [
                'type' => 'category',
            ],
        ],
        'focus_keywords' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.focus_keywords',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 512,
                'eval' => 'trim',
            ],
        ],
        'author' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.author',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'bodytext' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.bodytext',
            'config' => [
                'type' => 'text',
                'rows' => 15,
                'enableRichtext' => false,
                'readOnly' => true,
            ],
        ],
        'content_html' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.content_html',
            'config' => [
                'type' => 'text',
                'rows' => 15,
                'readOnly' => true,
            ],
        ],
        'source_file' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.source_file',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 1024,
                'readOnly' => true,
            ],
        ],
        'translation_key' => [
            'label' => 'LLL:EXT:fast_blog/Resources/Private/Language/locallang_db.xlf:blogpost.translation_key',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 64,
                'readOnly' => true,
            ],
        ],
    ],
];
