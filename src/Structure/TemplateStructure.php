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
 * A template language with named regions of its own.
 *
 * Blocks and macros are both named and both delimited, and they are not the
 * same thing: a block is a slot a child template overrides, a macro is called.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface TemplateStructure extends SourceStructure
{
    public function blockNamed(string $name, SourceRange $within): ?SourceSymbol;

    public function macroNamed(string $name, SourceRange $within): ?SourceSymbol;
}
