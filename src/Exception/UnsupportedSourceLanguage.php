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

use Alto\Language\Language;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class UnsupportedSourceLanguage extends \LogicException
{
    public static function forStructure(?Language $language): self
    {
        return new self(sprintf(
            'Structural selectors are not available for language "%s".',
            self::name($language),
        ));
    }

    /**
     * A language that has a structure, but not this part of one. CSS knows
     * rules and no methods, and saying so is more useful than an empty result.
     */
    public static function forCapability(?Language $language, string $selector): self
    {
        return new self(sprintf('Language "%s" has no %s to select.', self::name($language), $selector));
    }

    private static function name(?Language $language): string
    {
        return null === $language ? 'unknown' : $language->slug;
    }
}
