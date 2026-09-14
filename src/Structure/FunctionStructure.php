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

namespace Alto\Code\Slicer\Structure;

use Alto\Code\Slicer\SourceRange;
use Alto\Code\Slicer\SourceSymbol;

/**
 * A language with functions declared outside any class.
 *
 * A standalone function is not a method, and the two stay apart in the API:
 * asking for one must never return the other.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface FunctionStructure extends SourceStructure
{
    public function functionNamed(string $name, SourceRange $within): ?SourceSymbol;
}
