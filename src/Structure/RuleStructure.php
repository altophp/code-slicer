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
 * A language built from rules introduced by a prelude, and from at-rules.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface RuleStructure extends SourceStructure
{
    /**
     * The selector is matched on its text after whitespace is collapsed, never
     * on what it would select in a browser: `.a , .b` and `.a, .b` are the same
     * rule, `.a` and `div.a` are not.
     */
    public function ruleNamed(string $selector, SourceRange $within): ?SourceSymbol;

    /**
     * A null prelude matches the first at-rule of that name whatever it carries.
     */
    public function atRuleNamed(string $name, ?string $prelude, SourceRange $within): ?SourceSymbol;
}
