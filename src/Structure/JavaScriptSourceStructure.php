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
 * Classes, methods and functions in JavaScript and TypeScript.
 *
 * PHP hands its tokens over through `PhpToken`. JavaScript does not, so this
 * lexes first: comments, the three kinds of string, template literals with the
 * expressions nested inside them, and regular expressions. Without that pass, a
 * brace written in a template literal or a `/` starting a regex breaks every
 * range that follows.
 *
 * Telling a regular expression from a division is decided by what comes before
 * the slash, which is the same heuristic every hand-written JavaScript lexer
 * uses. It is right on the shapes documentation shows and can be fooled by
 * pathological code.
 *
 * TypeScript reads through here too: type annotations, generics, access
 * modifiers and `abstract` sit inside the parts already skipped over, so a
 * declaration keeps its shape. What TypeScript adds and JavaScript has no
 * equivalent for, `interface`, `type` and `enum`, is not a symbol here.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class JavaScriptSourceStructure implements ClassStructure, MethodStructure, FunctionStructure
{
    private const MODIFIERS = [
        'static', 'async', 'get', 'set', 'public', 'private', 'protected',
        'readonly', 'abstract', 'override', 'declare', 'accessor',
    ];

    /**
     * @var list<array{kind: string, text: string, start: int, end: int}>
     */
    private array $tokens = [];

    /**
     * @var list<SourceSymbol>
     */
    private array $classes = [];

    /**
     * @var list<SourceSymbol>
     */
    private array $methods = [];

    /**
     * @var list<SourceSymbol>
     */
    private array $functions = [];

    public function __construct(private readonly string $code)
    {
        $this->tokens = $this->tokenize();
        $this->parse();
    }

    public function nextClass(SourceRange $within): ?SourceSymbol
    {
        return self::firstWithin($this->classes, $within);
    }

    public function classNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        return self::firstNamed($this->classes, $name, $within);
    }

    public function nextMethod(SourceRange $within): ?SourceSymbol
    {
        return self::firstWithin($this->methods, $within);
    }

    public function methodNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        return self::firstNamed($this->methods, $name, $within);
    }

    public function functionNamed(string $name, SourceRange $within): ?SourceSymbol
    {
        return self::firstNamed($this->functions, $name, $within);
    }

    /**
     * @param list<SourceSymbol> $symbols
     */
    private static function firstWithin(array $symbols, SourceRange $within): ?SourceSymbol
    {
        foreach ($symbols as $symbol) {
            if ($within->contains($symbol->declaration)) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * @param list<SourceSymbol> $symbols
     */
    private static function firstNamed(array $symbols, string $name, SourceRange $within): ?SourceSymbol
    {
        foreach ($symbols as $symbol) {
            if ($symbol->name === $name && $within->contains($symbol->declaration)) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * @return list<array{kind: string, text: string, start: int, end: int}>
     */
    private function tokenize(): array
    {
        $code = $this->code;
        $length = strlen($code);
        $offset = 0;
        $tokens = [];
        $templateDepth = [];

        while ($offset < $length) {
            $char = $code[$offset];

            if ('' === trim($char)) {
                ++$offset;

                continue;
            }

            if ('/' === $char && '/' === ($code[$offset + 1] ?? '')) {
                $end = strpos($code, "\n", $offset);
                $end = false === $end ? $length : $end;
                $tokens[] = ['kind' => 'comment', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            if ('/' === $char && '*' === ($code[$offset + 1] ?? '')) {
                $end = strpos($code, '*/', $offset + 2);
                $end = false === $end ? $length : $end + 2;
                $tokens[] = ['kind' => 'comment', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $end = self::skipString($code, $offset, $length);
                $tokens[] = ['kind' => 'string', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            if ('`' === $char) {
                $end = $this->skipTemplate($offset, $length);
                $tokens[] = ['kind' => 'string', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            if ('/' === $char && self::startsRegex($tokens)) {
                $end = self::skipRegex($code, $offset, $length);
                $tokens[] = ['kind' => 'regex', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            if (1 === preg_match('/[A-Za-z_$#]/', $char)) {
                $end = $offset + 1;

                while ($end < $length && 1 === preg_match('/[A-Za-z0-9_$]/', $code[$end])) {
                    ++$end;
                }

                $tokens[] = ['kind' => 'word', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            if (1 === preg_match('/[0-9]/', $char)) {
                $end = $offset + 1;

                while ($end < $length && 1 === preg_match('/[0-9a-zA-Z._]/', $code[$end])) {
                    ++$end;
                }

                $tokens[] = ['kind' => 'number', 'text' => substr($code, $offset, $end - $offset), 'start' => $offset, 'end' => $end];
                $offset = $end;

                continue;
            }

            $tokens[] = ['kind' => 'punctuation', 'text' => $char, 'start' => $offset, 'end' => $offset + 1];
            ++$offset;
        }

        unset($templateDepth);

        return $tokens;
    }

    /**
     * A slash opens a regular expression unless what precedes it can end an
     * expression, in which case it divides.
     *
     * @param list<array{kind: string, text: string, start: int, end: int}> $tokens
     */
    private static function startsRegex(array $tokens): bool
    {
        for ($index = count($tokens) - 1; $index >= 0; --$index) {
            $token = $tokens[$index];

            if ('comment' === $token['kind']) {
                continue;
            }

            if ('word' === $token['kind']) {
                // `return /re/` is a regex, `value / 2` is a division
                return \in_array($token['text'], ['return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await'], true);
            }

            if (\in_array($token['kind'], ['string', 'number', 'regex'], true)) {
                return false;
            }

            // `)` usually ends an expression, so the slash divides. Not when it
            // closes the head of a statement: `if (x) /re/.test(x)` is a regex,
            // and reading it as a division swallows every brace until the next
            // slash, which moves the end of the enclosing method.
            if (')' === $token['text']) {
                return self::closesStatementHead($tokens, $index);
            }

            return !\in_array($token['text'], [']', '}'], true);
        }

        return true;
    }

    /**
     * @param list<array{kind: string, text: string, start: int, end: int}> $tokens
     */
    private static function closesStatementHead(array $tokens, int $index): bool
    {
        $depth = 0;

        for ($cursor = $index; $cursor >= 0; --$cursor) {
            $text = $tokens[$cursor]['text'];

            if ('punctuation' !== $tokens[$cursor]['kind']) {
                continue;
            }

            if (')' === $text) {
                ++$depth;
            } elseif ('(' === $text && 0 === --$depth) {
                $before = $cursor - 1;

                while ($before >= 0 && 'comment' === $tokens[$before]['kind']) {
                    --$before;
                }

                return $before >= 0
                    && 'word' === $tokens[$before]['kind']
                    && \in_array($tokens[$before]['text'], ['if', 'for', 'while', 'switch', 'catch', 'with'], true);
            }
        }

        return false;
    }

    private function skipTemplate(int $offset, int $length): int
    {
        $code = $this->code;
        ++$offset;

        while ($offset < $length) {
            $char = $code[$offset];

            if ('\\' === $char) {
                $offset += 2;

                continue;
            }

            if ('`' === $char) {
                return $offset + 1;
            }

            // `${ ... }` holds an expression, which may hold another template
            if ('$' === $char && '{' === ($code[$offset + 1] ?? '')) {
                $depth = 1;
                $offset += 2;

                while ($offset < $length && $depth > 0) {
                    $inner = $code[$offset];

                    if ('`' === $inner) {
                        $offset = $this->skipTemplate($offset, $length);

                        continue;
                    }

                    if ('"' === $inner || "'" === $inner) {
                        $offset = self::skipString($code, $offset, $length);

                        continue;
                    }

                    if ('{' === $inner) {
                        ++$depth;
                    } elseif ('}' === $inner) {
                        --$depth;
                    }

                    ++$offset;
                }

                continue;
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

    private static function skipRegex(string $code, int $offset, int $length): int
    {
        ++$offset;
        $inClass = false;

        while ($offset < $length) {
            $char = $code[$offset];

            if ('\\' === $char) {
                $offset += 2;

                continue;
            }

            if ("\n" === $char) {
                return $offset;
            }

            if ('[' === $char) {
                $inClass = true;
            } elseif (']' === $char) {
                $inClass = false;
            } elseif ('/' === $char && !$inClass) {
                ++$offset;

                while ($offset < $length && 1 === preg_match('/[a-z]/', $code[$offset])) {
                    ++$offset;
                }

                return $offset;
            }

            ++$offset;
        }

        return $length;
    }

    private function parse(): void
    {
        $matching = $this->matchingBraces();
        $count = count($this->tokens);

        /** @var list<array{name: string, bodyStart: int, bodyEnd: int}> $classBodies */
        $classBodies = [];

        for ($index = 0; $index < $count; ++$index) {
            $token = $this->tokens[$index];

            if ('word' !== $token['kind'] || 'class' !== $token['text'] || $this->isMember($index)) {
                continue;
            }

            $nameIndex = $this->nextToken($index + 1);

            if (null === $nameIndex || 'word' !== $this->tokens[$nameIndex]['kind'] || '{' === $this->tokens[$nameIndex]['text']) {
                continue;
            }

            $openIndex = $this->braceAfter($nameIndex + 1, $matching);

            if (null === $openIndex) {
                continue;
            }

            $closeIndex = $matching[$openIndex];

            $this->classes[] = new SourceSymbol(
                SymbolKind::ClassLike,
                $this->tokens[$nameIndex]['text'],
                new SourceRange($this->declarationStart($index), $this->tokens[$closeIndex]['end']),
                new SourceRange($this->tokens[$openIndex]['end'], $this->tokens[$closeIndex]['start']),
            );

            $classBodies[] = [
                'name' => $this->tokens[$nameIndex]['text'],
                'bodyStart' => $openIndex,
                'bodyEnd' => $closeIndex,
            ];
        }

        for ($index = 0; $index < $count; ++$index) {
            $this->collectFunction($index, $matching);
        }

        foreach ($classBodies as $body) {
            $this->collectMethods($body, $matching);
        }
    }

    /**
     * @param array<int, int>                                  $matching
     * @param array{name: string, bodyStart: int, bodyEnd: int} $body
     */
    private function collectMethods(array $body, array $matching): void
    {
        $index = $body['bodyStart'] + 1;

        while ($index < $body['bodyEnd']) {
            $token = $this->tokens[$index];

            // anything with a body of its own is skipped whole: a nested class
            // and its methods belong to that class, not to this one
            if ('punctuation' === $token['kind'] && '{' === $token['text'] && isset($matching[$index])) {
                $index = $matching[$index] + 1;

                continue;
            }

            if ('comment' === $token['kind']) {
                ++$index;

                continue;
            }

            $method = $this->readMember($index, $matching);

            if (null === $method) {
                ++$index;

                continue;
            }

            [$name, $start, $end, $next] = $method;

            $this->methods[] = new SourceSymbol(
                SymbolKind::Method,
                $name,
                new SourceRange($start, $end),
                null,
                $body['name'],
            );

            $index = $next;
        }
    }

    /**
     * Reads a member declaration at `$index`, or nothing when what sits there
     * is not one.
     *
     * @param array<int, int> $matching
     *
     * @return array{string, int, int, int}|null the name, the range and the token to resume from
     */
    private function readMember(int $index, array $matching): ?array
    {
        $first = $index;

        // modifiers, then an optional generator star, then the name
        while (isset($this->tokens[$index])
            && 'word' === $this->tokens[$index]['kind']
            && \in_array($this->tokens[$index]['text'], self::MODIFIERS, true)) {
            $next = $this->nextToken($index + 1);

            // `get` and `static` are legal member names too: `get()` is a method
            if (null === $next || '(' === $this->tokens[$next]['text'] || '=' === $this->tokens[$next]['text']) {
                break;
            }

            $index = $next;
        }

        if (isset($this->tokens[$index]) && '*' === $this->tokens[$index]['text']) {
            $index = $this->nextToken($index + 1) ?? $index;
        }

        if (!isset($this->tokens[$index]) || 'word' !== $this->tokens[$index]['kind']) {
            return null;
        }

        $name = ltrim($this->tokens[$index]['text'], '#');
        $parenIndex = $this->skipTypeParameters($this->nextToken($index + 1));

        if (null === $parenIndex || '(' !== $this->tokens[$parenIndex]['text'] || !isset($matching[$parenIndex])) {
            return null;
        }

        $bodyIndex = $this->braceAfter($matching[$parenIndex] + 1, $matching);

        if (null === $bodyIndex) {
            return null;
        }

        // a signature with no body is a TypeScript overload or an abstract
        // member; it ends at its semicolon
        $closeIndex = $matching[$bodyIndex];

        return [
            $name,
            $this->declarationStart($first),
            $this->tokens[$closeIndex]['end'],
            $closeIndex + 1,
        ];
    }

    /**
     * @param array<int, int> $matching
     */
    private function collectFunction(int $index, array $matching): void
    {
        $token = $this->tokens[$index];

        if ('word' !== $token['kind'] || 'function' !== $token['text'] || $this->isMember($index)) {
            return;
        }

        $nameIndex = $this->nextToken($index + 1);

        if (null !== $nameIndex && '*' === $this->tokens[$nameIndex]['text']) {
            $nameIndex = $this->nextToken($nameIndex + 1);
        }

        if (null === $nameIndex || 'word' !== $this->tokens[$nameIndex]['kind']) {
            return;
        }

        $parenIndex = $this->skipTypeParameters($this->nextToken($nameIndex + 1));

        if (null === $parenIndex || '(' !== $this->tokens[$parenIndex]['text'] || !isset($matching[$parenIndex])) {
            return;
        }

        $openIndex = $this->braceAfter($matching[$parenIndex] + 1, $matching);

        if (null === $openIndex) {
            return;
        }

        $closeIndex = $matching[$openIndex];

        $this->functions[] = new SourceSymbol(
            SymbolKind::Function_,
            $this->tokens[$nameIndex]['text'],
            new SourceRange($this->declarationStart($index), $this->tokens[$closeIndex]['end']),
            new SourceRange($this->tokens[$openIndex]['end'], $this->tokens[$closeIndex]['start']),
        );
    }

    /**
     * `this.class` and `obj.function` are property names, not declarations.
     */
    private function isMember(int $index): bool
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            if ('comment' === $this->tokens[$cursor]['kind']) {
                continue;
            }

            return '.' === $this->tokens[$cursor]['text'];
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    private function matchingBraces(): array
    {
        $matching = [];
        $stack = ['{' => [], '(' => [], '[' => []];
        $pairs = ['}' => '{', ')' => '(', ']' => '['];

        foreach ($this->tokens as $index => $token) {
            if ('punctuation' !== $token['kind']) {
                continue;
            }

            if (isset($stack[$token['text']])) {
                $stack[$token['text']][] = $index;

                continue;
            }

            if (isset($pairs[$token['text']])) {
                $opening = array_pop($stack[$pairs[$token['text']]]);

                if (null !== $opening) {
                    $matching[$opening] = $index;
                }
            }
        }

        return $matching;
    }

    /**
     * Steps over a TypeScript type parameter list, `<T extends Payable>`.
     *
     * A lexer cannot know whether `<` opens generics or compares two values, so
     * this only accepts the reading that leads straight to a parameter list:
     * the angle brackets have to balance and a `(` has to follow. `a < b > (c)`
     * is left alone because nothing about it declares anything.
     */
    private function skipTypeParameters(?int $index): ?int
    {
        if (null === $index || '<' !== ($this->tokens[$index]['text'] ?? '')) {
            return $index;
        }

        $depth = 0;
        $count = count($this->tokens);

        for ($cursor = $index; $cursor < $count; ++$cursor) {
            $text = $this->tokens[$cursor]['text'];

            if (\in_array($text, [';', '{', '}'], true)) {
                return $index;
            }

            if ('<' === $text) {
                ++$depth;
            } elseif ('>' === $text && 0 === --$depth) {
                $next = $this->nextToken($cursor + 1);

                return null !== $next && '(' === $this->tokens[$next]['text'] ? $next : $index;
            }
        }

        return $index;
    }

    private function nextToken(int $from): ?int
    {
        $count = count($this->tokens);

        for ($index = $from; $index < $count; ++$index) {
            if ('comment' !== $this->tokens[$index]['kind']) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The `{` opening a body, skipping whatever the declaration carries between
     * the signature and it: a return type, a heritage clause, an implements
     * list. It stops at a `;`, which is what an overload or an abstract member
     * ends with.
     *
     * @param array<int, int> $matching
     */
    private function braceAfter(int $from, array $matching): ?int
    {
        $count = count($this->tokens);

        for ($index = $from; $index < $count; ++$index) {
            $token = $this->tokens[$index];

            if ('comment' === $token['kind']) {
                continue;
            }

            if (';' === $token['text']) {
                return null;
            }

            if ('{' === $token['text']) {
                return isset($matching[$index]) ? $index : null;
            }
        }

        return null;
    }

    /**
     * Where the declaration starts: the JSDoc and the decorators written above
     * it, as long as no blank line separates them from it.
     */
    private function declarationStart(int $index): int
    {
        $start = $this->tokens[$index]['start'];

        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $this->tokens[$cursor];
            $gap = substr($this->code, $token['end'], $start - $token['end']);

            if ('' !== trim($gap) || substr_count($gap, "\n") > 1) {
                break;
            }

            if ('comment' === $token['kind']) {
                $start = $token['start'];

                continue;
            }

            // `export`, `default`, `abstract`: they belong to the declaration,
            // and skipping them is what lets the walk reach a decorator above
            if ('word' === $token['kind'] && \in_array($token['text'], ['export', 'default', 'abstract', 'declare'], true)) {
                $start = $token['start'];

                continue;
            }

            // a decorator: `@Name` or `@Name(...)`, read backwards
            if (')' === $token['text']) {
                $opening = array_search($cursor, $this->matchingBraces(), true);

                if (!is_int($opening)) {
                    break;
                }

                $nameIndex = $opening - 1;

                if ($nameIndex < 1 || 'word' !== $this->tokens[$nameIndex]['kind'] || '@' !== $this->tokens[$nameIndex - 1]['text']) {
                    break;
                }

                $start = $this->tokens[$nameIndex - 1]['start'];
                $cursor = $nameIndex - 1;

                continue;
            }

            if ('word' === $token['kind'] && $cursor > 0 && '@' === $this->tokens[$cursor - 1]['text']) {
                $start = $this->tokens[$cursor - 1]['start'];
                --$cursor;

                continue;
            }

            break;
        }

        return self::startOfLine($this->code, $start);
    }

    private static function startOfLine(string $code, int $offset): int
    {
        $lineStart = strrpos(substr($code, 0, $offset), "\n");

        return false === $lineStart ? 0 : $lineStart + 1;
    }
}
