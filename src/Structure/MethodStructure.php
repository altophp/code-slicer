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
 * A language whose classes hold named methods.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface MethodStructure extends SourceStructure
{
    public function nextMethod(SourceRange $within): ?SourceSymbol;

    public function methodNamed(string $name, SourceRange $within): ?SourceSymbol;
}
