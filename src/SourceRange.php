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

use Alto\Code\Slicer\Exception\InvalidSourceRange;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class SourceRange
{
    public function __construct(
        public int $start,
        public int $end,
    ) {
        if ($start < 0 || $end < $start) {
            throw InvalidSourceRange::fromOffsets($start, $end);
        }
    }

    public function contains(SourceRange $range): bool
    {
        return $range->start >= $this->start && $range->end <= $this->end;
    }
}
