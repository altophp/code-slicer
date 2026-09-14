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

namespace Alto\Code\Slicer\Tests;

use Alto\Code\Slicer\CodeSource;
use Alto\Code\Slicer\Exception\SourceSymbolNotFound;
use Alto\Code\Slicer\Exception\UnsupportedSourceLanguage;

final class CssStructureTest extends TestCase
{
    private const SHEET = <<<'CSS'
        /* The sheet, not the rule below it. */

        @import url("reset.css");

        /* Primary action: strong contrast, no layout shift on hover. */
        .button,
        .button--ghost {
            color: var(--button-color);

            /* Reserve the border before interaction. */
            border: 1px solid transparent;
        }

        @media (width >= 48rem) {
            .button {
                padding-inline: 2rem;
            }
        }

        @keyframes pulse {
            from { opacity: 1; }
            to { opacity: .4; }
        }
        CSS;

    public function testARuleKeepsTheCommentAttachedToIt(): void
    {
        $content = self::sheet()->rule('.button, .button--ghost')->content();

        self::assertStringStartsWith('/* Primary action:', $content);
        self::assertStringContainsString('/* Reserve the border before interaction. */', $content);
        self::assertStringNotContainsString('The sheet, not the rule', $content);
        self::assertStringEndsWith('}', $content);
    }

    public function testASelectorListIsMatchedOnItsTextWithWhitespaceCollapsed(): void
    {
        // `.a , .b` and `.a, .b` are the same rule. `.a` alone is not.
        $content = self::sheet()->rule("  .button ,\n .button--ghost  ")->content();

        self::assertStringContainsString('color: var(--button-color);', $content);
    }

    public function testTheSameSelectorInTwoScopesIsResolvedByNarrowingFirst(): void
    {
        $top = self::sheet()->rule('.button, .button--ghost')->content();
        $inMedia = self::sheet()->atRule('media', '(width >= 48rem)')->rule('.button')->content();

        self::assertStringContainsString('border: 1px solid transparent;', $top);
        self::assertSame("    .button {\n        padding-inline: 2rem;\n    }", $inMedia);
    }

    public function testAnAtRuleWithoutABlockEndsAtItsSemicolon(): void
    {
        self::assertSame('@import url("reset.css");', self::sheet()->atRule('import')->content());
    }

    public function testAnAtRuleMatchesOnItsNameWhenNoPreludeIsGiven(): void
    {
        self::assertStringStartsWith('@media (width >= 48rem) {', self::sheet()->atRule('media')->content());
    }

    public function testKeyframesAreAnAtRuleAndTheirStepsAreNot(): void
    {
        $content = self::sheet()->atRule('keyframes', 'pulse')->content();

        self::assertStringStartsWith('@keyframes pulse {', $content);

        $this->expectException(SourceSymbolNotFound::class);

        self::sheet()->rule('pulse');
    }

    public function testABraceOrASemicolonInsideAValueDoesNotEndTheRule(): void
    {
        $content = CodeSource::fromString(<<<'CSS'
            .quote {
                content: "} not the end; really";
                background: url("a;b}c.png");
            }
            CSS, self::language('css'))
            ->slice()
            ->rule('.quote')
            ->content();

        self::assertStringEndsWith("}\n}", $content . "\n}");
        self::assertStringContainsString('url("a;b}c.png")', $content);
    }

    public function testNestedCssBelongsToTheRuleAroundIt(): void
    {
        $source = CodeSource::fromString(<<<'CSS'
            .card {
                padding: 1rem;

                & .title {
                    font-weight: 600;
                }
            }
            CSS, self::language('css'));

        $content = $source->slice()->rule('.card')->content();

        self::assertStringContainsString('& .title {', $content);
        self::assertStringContainsString('font-weight: 600;', $source->slice()->rule('& .title')->content());
    }

    public function testCssHasNoMethodsToSelect(): void
    {
        $this->expectException(UnsupportedSourceLanguage::class);
        $this->expectExceptionMessage('Language "css" has no methods to select.');

        self::sheet()->method('anything');
    }

    public function testAQuotedEscapeDoesNotEndAValue(): void
    {
        $content = CodeSource::fromString('.quote { content: "a\"b"; }', self::language('css'))
            ->slice()
            ->rule('.quote')
            ->content();

        self::assertStringContainsString('a\"b', $content);
    }

    public function testIncompleteCssIsIgnoredSafely(): void
    {
        foreach (['/* comment */ {}', '.quote { content: "unfinished'] as $code) {
            CodeSource::fromString($code, self::language('css'))->structure();
        }

        self::addToAssertionCount(2);
    }

    private static function sheet(): \Alto\Code\Slicer\CodeSlice
    {
        return CodeSource::fromString(self::SHEET, self::language('css'))->slice();
    }
}
