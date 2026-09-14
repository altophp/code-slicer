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
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class PhpSourceStructure implements ClassStructure, MethodStructure
{
    /**
     * @var list<SourceSymbol>
     */
    private array $classes = [];

    /**
     * @var list<SourceSymbol>
     */
    private array $methods = [];

    private int $positionOffset = 0;

    public function __construct(string $code)
    {
        $this->parse($code);
    }

    public function nextClass(SourceRange $within): ?SourceSymbol
    {
        return $this->firstWithin($this->classes, $within);
    }

    public function nextMethod(SourceRange $within): ?SourceSymbol
    {
        return $this->firstWithin($this->methods, $within);
    }

    public function classNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        foreach ($this->classes as $class) {
            if ($class->name === $name && $within->contains($class->declaration)) {
                return $class;
            }
        }

        return null;
    }

    public function methodNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        foreach ($this->methods as $method) {
            if ($method->name === $name && $within->contains($method->declaration)) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param list<SourceSymbol> $symbols
     */
    private function firstWithin(array $symbols, SourceRange $within): ?SourceSymbol
    {
        foreach ($symbols as $symbol) {
            if ($within->contains($symbol->declaration)) {
                return $symbol;
            }
        }

        return null;
    }

    private function parse(string $code): void
    {
        if (!str_contains($code, '<?')) {
            $prefix = "<?php\n";
            $code = $prefix . $code;
            $this->positionOffset = strlen($prefix);
        }

        $tokens = array_values(\PhpToken::tokenize($code));
        $depths = [];
        $matchingBraces = [];
        $openBraces = [];
        $depth = 0;

        foreach ($tokens as $index => $token) {
            $depths[$index] = $depth;

            if ('{' === $token->text) {
                $openBraces[] = $index;
                ++$depth;
            } elseif ('}' === $token->text) {
                --$depth;
                $openIndex = array_pop($openBraces);

                if (is_int($openIndex)) {
                    $matchingBraces[$openIndex] = $index;
                }
            }
        }

        /** @var list<array{open: int, close: int, depth: int}> $classBodies */
        $classBodies = [];

        foreach ($tokens as $index => $token) {
            if (T_CLASS !== $token->id) {
                continue;
            }

            $nameIndex = $this->nextSignificantToken($tokens, $index + 1);

            if (null === $nameIndex || T_STRING !== $tokens[$nameIndex]->id) {
                continue;
            }

            $openIndex = $this->nextTokenWithText($tokens, $nameIndex + 1, '{');

            if (null === $openIndex || !isset($matchingBraces[$openIndex])) {
                continue;
            }

            $closeIndex = $matchingBraces[$openIndex];
            $start = $this->declarationStart($tokens, $index);
            $end = $this->position($tokens[$closeIndex]) + strlen($tokens[$closeIndex]->text);

            $this->classes[] = new SourceSymbol(
                SymbolKind::ClassLike,
                $tokens[$nameIndex]->text,
                new SourceRange($start, $end),
                new SourceRange($this->position($tokens[$openIndex]) + 1, $this->position($tokens[$closeIndex])),
            );
            $classBodies[] = [
                'open' => $this->position($tokens[$openIndex]),
                'close' => $this->position($tokens[$closeIndex]) + strlen($tokens[$closeIndex]->text),
                'depth' => $depths[$openIndex] + 1,
            ];
        }

        foreach ($tokens as $index => $token) {
            if (T_FUNCTION !== $token->id || !$this->isAtClassLevel($this->position($token), $depths[$index], $classBodies)) {
                continue;
            }

            $nameIndex = $this->methodNameToken($tokens, $index + 1);

            if (null === $nameIndex) {
                continue;
            }

            $bodyIndex = $this->nextMethodBoundary($tokens, $nameIndex + 1);

            if (null === $bodyIndex) {
                continue;
            }

            if (';' === $tokens[$bodyIndex]->text) {
                $end = $this->position($tokens[$bodyIndex]) + 1;
            } elseif (isset($matchingBraces[$bodyIndex])) {
                $close = $tokens[$matchingBraces[$bodyIndex]];
                $end = $this->position($close) + strlen($close->text);
                // A method inside a balanced class has a balanced body. Keep
                // the guard against an inconsistent token map defensive.
                // @codeCoverageIgnoreStart
            } else {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $this->methods[] = new SourceSymbol(
                SymbolKind::Method,
                $tokens[$nameIndex]->text,
                new SourceRange($this->declarationStart($tokens, $index), $end),
                scope: $this->scopeAt($this->position($tokens[$index])),
            );
        }
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function nextSignificantToken(array $tokens, int $from): ?int
    {
        $count = count($tokens);

        for ($index = $from; $index < $count; ++$index) {
            if (!$tokens[$index]->isIgnorable()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function nextTokenWithText(array $tokens, int $from, string $text): ?int
    {
        $count = count($tokens);

        for ($index = $from; $index < $count; ++$index) {
            if ($tokens[$index]->text === $text) {
                return $index;
            }

            if (';' === $tokens[$index]->text) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function methodNameToken(array $tokens, int $from): ?int
    {
        $count = count($tokens);

        for ($index = $from; $index < $count; ++$index) {
            $token = $tokens[$index];

            if ($token->isIgnorable() || '&' === $token->text) {
                continue;
            }

            return T_STRING === $token->id ? $index : null;
        }

        // A class-level function token is followed by at least the class
        // closing brace, so the loop above always decides first.
        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function nextMethodBoundary(array $tokens, int $from): ?int
    {
        $count = count($tokens);

        for ($index = $from; $index < $count; ++$index) {
            if ('{' === $tokens[$index]->text || ';' === $tokens[$index]->text) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function declarationStart(array $tokens, int $declarationIndex): int
    {
        for ($index = $declarationIndex - 1; $index >= 0; --$index) {
            $token = $tokens[$index];

            if (';' === $token->text || '{' === $token->text || '}' === $token->text || T_OPEN_TAG === $token->id) {
                $startIndex = $index + 1;
                $count = count($tokens);

                while ($startIndex < $count && T_WHITESPACE === $tokens[$startIndex]->id) {
                    ++$startIndex;
                }

                return $this->startOfIndentedLine($tokens, $startIndex);
            }
        }

        // PHP declarations parsed here always follow an opening tag or a
        // class boundary.
        // @codeCoverageIgnoreStart
        return $this->position($tokens[$declarationIndex]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private function startOfIndentedLine(array $tokens, int $tokenIndex): int
    {
        $token = $tokens[$tokenIndex] ?? throw new \LogicException('Declaration token is missing.');
        $previous = $tokens[$tokenIndex - 1] ?? null;

        if (null === $previous || T_WHITESPACE !== $previous->id) {
            return $this->position($token);
        }

        $whitespace = $previous->text;
        $lastLineFeed = strrpos($whitespace, "\n");
        $lastCarriageReturn = strrpos($whitespace, "\r");
        $lastLineBreak = max(
            false === $lastLineFeed ? -1 : $lastLineFeed,
            false === $lastCarriageReturn ? -1 : $lastCarriageReturn,
        );
        $indentation = -1 === $lastLineBreak
            ? $whitespace
            : substr($whitespace, $lastLineBreak + 1);

        // PhpToken only classifies spaces and tabs in this indentation suffix
        // as whitespace.
        // @codeCoverageIgnoreStart
        if (strlen($indentation) !== strspn($indentation, " \t")) {
            return $this->position($token);
        }
        // @codeCoverageIgnoreEnd

        return $this->position($token) - strlen($indentation);
    }

    private function position(\PhpToken $token): int
    {
        return $token->pos - $this->positionOffset;
    }

    /**
     * The class holding a position, which is what tells two methods of the same
     * name apart without narrowing the window first.
     */
    private function scopeAt(int $position): ?string
    {
        foreach ($this->classes as $class) {
            if (null !== $class->body && $position > $class->body->start && $position < $class->body->end) {
                return $class->name;
            }
        }

        // Called only after isAtClassLevel() established a containing class.
        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param list<array{open: int, close: int, depth: int}> $classBodies
     */
    private function isAtClassLevel(int $position, int $depth, array $classBodies): bool
    {
        foreach ($classBodies as $body) {
            if ($position > $body['open'] && $position < $body['close'] && $depth === $body['depth']) {
                return true;
            }
        }

        return false;
    }
}
