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
final class SourceFileNotReadable extends \RuntimeException
{
    public static function fromPath(string $path): self
    {
        return new self(sprintf('Source file "%s" is not readable.', $path));
    }
}
