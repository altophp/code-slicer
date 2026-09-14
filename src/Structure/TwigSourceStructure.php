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
 * Twig blocks and macros.
 *
 * A template is text with tags in it, so the scan is the reverse of the other
 * adapters: everything is content until `{%`, `{{` or `{#` opens a region. Only
 * the tags matter here, and only the four that open or close a named region.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class TwigSourceStructure implements TemplateStructure
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

    public function blockNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        return $this->named(SymbolKind::Block, $name, $within);
    }

    public function macroNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        return $this->named(SymbolKind::Macro, $name, $within);
    }

    private function named(SymbolKind $kind, string $name, SourceRange $within): ?SourceSymbol
    {
        foreach ($this->symbols as $symbol) {
            if ($symbol->kind === $kind && $symbol->name === $name && $within->contains($symbol->declaration)) {
                return $symbol;
            }
        }

        return null;
    }

    private function parse(): void
    {
        $code = $this->code;
        $length = strlen($code);
        $offset = 0;

        /** @var list<array{kind: SymbolKind, name: string, start: int, bodyStart: int, scope: ?string}> $open */
        $open = [];

        while ($offset < $length) {
            $brace = strpos($code, '{', $offset);

            if (false === $brace || $brace + 1 >= $length) {
                return;
            }

            $marker = $code[$brace + 1];

            if ('#' === $marker) {
                $end = strpos($code, '#}', $brace + 2);
                $end = false === $end ? $length : $end + 2;
                $this->comments[] = [$brace, $end];
                $offset = $end;

                continue;
            }

            if ('{' === $marker) {
                $end = $this->endOfRegion($brace + 2, '}}');
                $offset = $end;

                continue;
            }

            if ('%' !== $marker) {
                $offset = $brace + 1;

                continue;
            }

            $end = $this->endOfRegion($brace + 2, '%}');
            $body = trim(substr($code, $brace + 2, $end - $brace - 4));
            $body = trim(rtrim(ltrim($body, '-~'), '-~'));
            $tag = strtolower((string) strtok($body, " \t\n"));
            $arguments = trim(substr($body, strlen($tag)));

            if ('block' === $tag || 'macro' === $tag) {
                $kind = 'block' === $tag ? SymbolKind::Block : SymbolKind::Macro;
                $name = (string) strtok($arguments, " \t\n(");
                $rest = trim(substr($arguments, strlen($name)));

                // `{% block title page.title %}` is a whole block on one tag:
                // it carries its value and never meets an `{% endblock %}`.
                if (SymbolKind::Block === $kind && '' !== $rest) {
                    $this->symbols[] = new SourceSymbol(
                        $kind,
                        $name,
                        new SourceRange($this->declarationStart($brace), $end),
                        null,
                        [] === $open ? null : $open[count($open) - 1]['name'],
                    );
                    $offset = $end;

                    continue;
                }

                $open[] = [
                    'kind' => $kind,
                    'name' => $name,
                    'start' => $this->declarationStart($brace),
                    'bodyStart' => $end,
                    'scope' => [] === $open ? null : $open[count($open) - 1]['name'],
                ];
                $offset = $end;

                continue;
            }

            if ('endblock' === $tag || 'endmacro' === $tag) {
                $expected = 'endblock' === $tag ? SymbolKind::Block : SymbolKind::Macro;

                for ($index = count($open) - 1; $index >= 0; --$index) {
                    if ($open[$index]['kind'] !== $expected) {
                        continue;
                    }

                    $region = $open[$index];
                    array_splice($open, $index);

                    $this->symbols[] = new SourceSymbol(
                        $region['kind'],
                        $region['name'],
                        new SourceRange($region['start'], $end),
                        new SourceRange($region['bodyStart'], $brace),
                        $region['scope'],
                    );

                    break;
                }
            }

            $offset = $end;
        }
    }

    /**
     * The offset just past the closing marker, with quoted text skipped so a
     * `%}` written inside a string does not end the tag early.
     */
    private function endOfRegion(int $offset, string $closing): int
    {
        $length = strlen($this->code);

        while ($offset < $length) {
            $char = $this->code[$offset];

            if ('"' === $char || "'" === $char) {
                $offset = self::skipString($this->code, $offset, $length);

                continue;
            }

            if ($char === $closing[0] && substr($this->code, $offset, 2) === $closing) {
                return $offset + 2;
            }

            ++$offset;
        }

        return $length;
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

    /**
     * The tag, with the Twig comment above it when nothing separates the two.
     */
    private function declarationStart(int $tagStart): int
    {
        foreach (array_reverse($this->comments) as [$commentStart, $commentEnd]) {
            // Comments are collected while scanning from left to right, so a
            // known comment cannot end after the current tag.
            // @codeCoverageIgnoreStart
            if ($commentEnd > $tagStart) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $gap = substr($this->code, $commentEnd, $tagStart - $commentEnd);

            if ('' === trim($gap) && substr_count($gap, "\n") <= 1) {
                return self::startOfLine($this->code, $commentStart);
            }

            break;
        }

        return self::startOfLine($this->code, $tagStart);
    }

    private static function startOfLine(string $code, int $offset): int
    {
        $lineStart = strrpos(substr($code, 0, $offset), "\n");

        return false === $lineStart ? 0 : $lineStart + 1;
    }
}
