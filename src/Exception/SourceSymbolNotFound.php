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
final class SourceSymbolNotFound extends \RuntimeException
{
    public static function next(string $kind): self
    {
        return new self(sprintf('No next %s was found in the current slice.', $kind));
    }

    public static function named(string $kind, string $name): self
    {
        return new self(sprintf('No %s named "%s" was found in the current slice.', $kind, $name));
    }
}
