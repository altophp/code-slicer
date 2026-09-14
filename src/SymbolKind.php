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

namespace Alto\Code\Slicer;

/**
 * What a symbol is, so a selector can refuse a match of the wrong nature
 * rather than return it because the name happened to fit.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
enum SymbolKind: string
{
    case ClassLike = 'class';
    case Method = 'method';
    case Function_ = 'function';
    case Rule = 'rule';
    case AtRule = 'at-rule';
    case Block = 'block';
    case Macro = 'macro';
}
