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
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class SourceSymbol
{
    /**
     * @param SourceRange      $declaration everything the selector returns, comments and
     *                                      attributes attached above the declaration included
     * @param SourceRange|null $body        the inside of the symbol, when it has one
     * @param string|null      $scope       the name of the symbol holding this one, which is what
     *                                      tells two `connect()` in two classes apart
     */
    public function __construct(
        public SymbolKind $kind,
        public string $name,
        public SourceRange $declaration,
        public ?SourceRange $body = null,
        public ?string $scope = null,
    ) {}
}
