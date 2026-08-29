<?php

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

return (new \PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        // declare strict_types is project standard for all PHP entry points
        'declare_strict_types' => true,
    ])
    ->setFinder(
        (new \PhpCsFixer\Finder())
            ->in([
                __DIR__ . '/Classes',
                __DIR__ . '/Configuration',
            ])
            ->name('/\.(php|inc)$/')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true),
    );