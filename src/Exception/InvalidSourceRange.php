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

namespace Alto\Code\Slicer\Exception;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class InvalidSourceRange extends \InvalidArgumentException
{
    public static function fromOffsets(int $start, int $end): self
    {
        return new self(sprintf('Invalid source range [%d, %d].', $start, $end));
    }

    public static function fromLines(int $start, int $end): self
    {
        return new self(sprintf('Invalid line range [%d, %d].', $start, $end));
    }
}
