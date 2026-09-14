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
use Alto\Code\Slicer\SymbolKind;

/**
 * Rules and at-rules, found by scanning rather than by matching a pattern.
 *
 * A regular expression over braces breaks on the first `{` inside a string or
 * a `url()`, and on the first `;` inside one. This walks the source once and
 * keeps track of what it is inside of, which is also what lets a nested rule
 * know the at-rule holding it.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class CssSourceStructure implements RuleStructure
{
    /**
     * @var list<SourceSymbol>
     */
    private array $symbols = [];

    /**
     * @var list<array{int, int}>
     */
    private array $comments = [];

    public function __construct(private readonly string $code)
    {
        $this->parse();
    }

    public function ruleNamed(string $selector, SourceRange $within): ?SourceSymbol
    {
        $wanted = self::normalize($selector);

        foreach ($this->symbols as $symbol) {
            if (SymbolKind::Rule === $symbol->kind && $symbol->name === $wanted && $within->contains($symbol->declaration)) {
                return $symbol;
            }
        }

        return null;
    }

    public function atRuleNamed(string $name, ?string $prelude, SourceRange $within): ?SourceSymbol
    {
        $wantedName = '@' . ltrim(strtolower($name), '@');
        $wantedPrelude = null === $prelude ? null : self::normalize($prelude);

        foreach ($this->symbols as $symbol) {
            if (SymbolKind::AtRule !== $symbol->kind || !$within->contains($symbol->declaration)) {
                continue;
            }

            [$atName, $atPrelude] = self::splitAtRule($symbol->name);

            if ($atName === $wantedName && (null === $wantedPrelude || $atPrelude === $wantedPrelude)) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * @return array{string, string}
     */
    private static function splitAtRule(string $name): array
    {
        $at = strtolower((string) strtok($name, " \t\n"));

        return [$at, trim(substr($name, strlen($at)))];
    }

    /**
     * Whitespace runs collapse, and the space a wrapped selector list leaves in
     * front of its commas goes with them. Nothing else: `.a > .b` and `.a>.b`
     * stay two different names, because deciding they are the same is reading
     * the selector rather than its text.
     */
    private static function normalize(string $text): string
    {
        return trim((string) preg_replace(['/\s+/', '/\s+,/'], [' ', ','], $text));
    }

    private function parse(): void
    {
        $code = $this->code;
        $length = strlen($code);
        $offset = 0;
        $preludeStart = 0;

        /** @var list<array{name: string, start: int, bodyStart: int, scope: ?string}> $open */
        $open = [];

        while ($offset < $length) {
            $char = $code[$offset];

            if ('/' === $char && '*' === ($code[$offset + 1] ?? '')) {
                $end = strpos($code, '*/', $offset + 2);
                $end = false === $end ? $length : $end + 2;
                $this->comments[] = [$offset, $end];
                $offset = $end;

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $offset = self::skipString($code, $offset, $length);

                continue;
            }

            if ('{' === $char) {
                $open[] = [
                    'name' => $this->preludeText($preludeStart, $offset),
                    'start' => $this->declarationStart($preludeStart, $offset),
                    'bodyStart' => $offset + 1,
                    'scope' => [] === $open ? null : $open[count($open) - 1]['name'],
                ];
                $preludeStart = ++$offset;

                continue;
            }

            if ('}' === $char) {
                $block = array_pop($open);

                if (null !== $block && '' !== $block['name']) {
                    $this->symbols[] = new SourceSymbol(
                        str_starts_with($block['name'], '@') ? SymbolKind::AtRule : SymbolKind::Rule,
                        $block['name'],
                        new SourceRange($block['start'], $offset + 1),
                        new SourceRange($block['bodyStart'], $offset),
                        $block['scope'],
                    );
                }

                $preludeStart = ++$offset;

                continue;
            }

            if (';' === $char) {
                // an at-rule with no block of its own: @import, @charset
                $statement = $this->preludeText($preludeStart, $offset);

                if (str_starts_with($statement, '@')) {
                    $this->symbols[] = new SourceSymbol(
                        SymbolKind::AtRule,
                        $statement,
                        new SourceRange($this->declarationStart($preludeStart, $offset), $offset + 1),
                        null,
                        [] === $open ? null : $open[count($open) - 1]['name'],
                    );
                }

                $preludeStart = ++$offset;

                continue;
            }

            ++$offset;
        }
    }

    /**
     * The selector or at-rule text, with the comments written inside it removed.
     *
     * `/​* explain *​/ .button {` would otherwise be a rule whose name carries the
     * explanation, and no caller would ever match it.
     */
    private function preludeText(int $start, int $end): string
    {
        $text = '';
        $cursor = $start;

        foreach ($this->comments as [$commentStart, $commentEnd]) {
            if ($commentStart >= $end || $commentEnd <= $start) {
                continue;
            }

            $text .= substr($this->code, $cursor, max(0, $commentStart - $cursor));
            $cursor = max($cursor, $commentEnd);
        }

        return self::normalize($text . substr($this->code, $cursor, max(0, $end - $cursor)));
    }

    /**
     * Where the declaration starts, the comment above it included when attached.
     *
     * A comment written straight above a rule explains that rule and belongs to
     * the excerpt. One separated by a blank line was addressing the file rather
     * than the rule, and stays out.
     */
    private function declarationStart(int $preludeStart, int $end): int
    {
        $start = null;

        foreach ($this->comments as [$commentStart, $commentEnd]) {
            if ($commentStart < $preludeStart || $commentEnd > $end) {
                continue;
            }

            $gap = substr($this->code, $commentEnd, $this->firstTokenAfter($commentEnd, $end) - $commentEnd);

            if (substr_count($gap, "\n") > 1) {
                $start = null;

                continue;
            }

            $start ??= $commentStart;
        }

        return self::startOfLine($this->code, $start ?? $this->firstTokenAfter($preludeStart, $end, true));
    }

    /**
     * The first thing that is not whitespace, and not a comment when asked: a
     * detached comment above a rule must not drag the declaration up to it.
     */
    private function firstTokenAfter(int $offset, int $limit, bool $skipComments = false): int
    {
        while ($offset < $limit) {
            if ('' === trim($this->code[$offset])) {
                ++$offset;

                continue;
            }

            if (!$skipComments) {
                return $offset;
            }

            $comment = null;

            foreach ($this->comments as [$commentStart, $commentEnd]) {
                if ($commentStart === $offset) {
                    $comment = $commentEnd;

                    break;
                }
            }

            if (null === $comment) {
                return $offset;
            }

            $offset = $comment;
        }

        return $offset;
    }

    private static function skipString(string $code, int $offset, int $length): int
    {
        $quote = $code[$offset];
        ++$offset;

        while ($offset < $length) {
            if ('\\' === $code[$offset]) {
                $offset += 2;

                continue;
            }

            if ($code[$offset] === $quote) {
                return $offset + 1;
            }

            ++$offset;
        }

        return $length;
    }

    private static function startOfLine(string $code, int $offset): int
    {
        $lineStart = strrpos(substr($code, 0, $offset), "\n");

        return false === $lineStart ? 0 : $lineStart + 1;
    }
}
