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
 * A language whose code is grouped into named classes.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface ClassStructure extends SourceStructure
{
    public function nextClass(SourceRange $within): ?SourceSymbol;

    public function classNamed(string $name, SourceRange $within): ?SourceSymbol;
}
