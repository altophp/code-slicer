<?php

declare(strict_types=1);

/*
 * This file is part of the ALTO library.
 *
 * © 2026-present Simon André
 *
 * For full copyright and license information, please see
 * the LICENSE file distributed with this source code.
 */

namespace Alto\Code\Slicer\Tests;

use Alto\Language\Language;
use Alto\Language\Languages;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    final protected static function language(string $slug): Language
    {
        return Languages::get($slug)
            ?? throw new \LogicException(sprintf('Unknown test language "%s".', $slug));
    }
}
