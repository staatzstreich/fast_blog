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

use Michaelstaatz\FastBlog\Controller\BlogController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

// configurePlugin()'s docblock still asks for ext_localconf.php specifically (it registers
// controller actions into $GLOBALS['TYPO3_CONF_VARS'] and auto-generates the
// "tt_content.fastblog_bloglist = ... EXTBASEPLUGIN" TypoScript) - the rest of this
// extension avoids ext_localconf.php entirely; this is the only exception.
ExtensionUtility::configurePlugin(
    'FastBlog',
    'Bloglist',
    [BlogController::class => 'list, show'],
);
