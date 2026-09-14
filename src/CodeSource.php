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
use Alto\Code\Slicer\Exception\SourceFileNotReadable;
use Alto\Code\Slicer\Exception\UnknownSourceLanguage;
use Alto\Code\Slicer\Exception\UnsupportedSourceLanguage;
use Alto\Code\Slicer\Structure\CssSourceStructure;
use Alto\Code\Slicer\Structure\JavaScriptSourceStructure;
use Alto\Code\Slicer\Structure\PhpSourceStructure;
use Alto\Code\Slicer\Structure\SourceStructure;
use Alto\Code\Slicer\Structure\TwigSourceStructure;
use Alto\Language\Language;
use Alto\Language\Languages;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CodeSource
{
    /**
     * @var list<int>
     */
    private array $lineOffsets;

    /**
     * @var list<int>
     */
    private array $lineEndOffsets;

    private ?SourceStructure $structure = null;

    private function __construct(
        private readonly string $content,
        private readonly ?Language $language,
        private readonly ?string $name,
    ) {
        [$this->lineOffsets, $this->lineEndOffsets] = $this->buildLineIndex();
    }

    public static function fromFile(string $path, Language|string|null $language = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw SourceFileNotReadable::fromPath($path);
        }

        $content = file_get_contents($path);

        // The file can only become unreadable here through a race after the
        // checks above, which cannot be reproduced deterministically.
        // @codeCoverageIgnoreStart
        if (false === $content) {
            throw SourceFileNotReadable::fromPath($path);
        }
        // @codeCoverageIgnoreEnd

        return new self(
            $content,
            null === $language ? Languages::fromFilename($path) : self::resolveLanguage($language),
            $path,
        );
    }

    public static function fromString(
        string $code,
        Language|string|null $language = null,
        ?string $name = null,
    ): self {
        return new self($code, self::resolveLanguage($language), $name);
    }

    public function content(): string
    {
        return $this->content;
    }

    public function language(): ?Language
    {
        return $this->language;
    }

    /**
     * A label for the source, not a promise about the filesystem.
     *
     * `fromFile()` stores the path it read. `fromString()` stores whatever the
     * caller passed, which may look exactly like a path and never was one. The
     * two are deliberately not told apart: a consumer that needs to link to a
     * file knows which constructor it called. Should one ever need to ask,
     * that is the day this becomes a value object rather than a string.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    public function lineCount(): int
    {
        return count($this->lineOffsets);
    }

    public function slice(): CodeSlice
    {
        return new CodeSlice($this, new SourceRange(0, strlen($this->content)));
    }

    public function lines(int $start, int $end): CodeSlice
    {
        return new CodeSlice($this, $this->rangeForLines($start, $end));
    }

    /**
     * @internal
     */
    public function rangeForLines(int $start, int $end): SourceRange
    {
        $lineCount = $this->lineCount();

        if ($start < 1 || $end < $start || $end > $lineCount) {
            throw InvalidSourceRange::fromLines($start, $end);
        }

        $startOffset = $this->lineOffsets[$start - 1];
        $endOffset = $this->lineEndOffsets[$end - 1];

        return new SourceRange($startOffset, $endOffset);
    }

    /**
     * @internal
     */
    public function lineAtOffset(int $offset): int
    {
        $length = strlen($this->content);

        if ($offset < 0 || $offset > $length) {
            throw InvalidSourceRange::fromOffsets($offset, $offset);
        }

        $low = 0;
        $high = count($this->lineOffsets) - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->lineOffsets[$middle] <= $offset) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return $high + 1;
    }

    /**
     * TypeScript reads through the JavaScript adapter. Where they differ is in
     * what a declaration may carry, not in how a class or a function is shaped,
     * and the adapter skips what it does not understand rather than guessing.
     *
     * @internal
     */
    public function structure(): SourceStructure
    {
        return $this->structure ??= match ($this->language?->slug) {
            'php' => new PhpSourceStructure($this->content),
            'javascript', 'typescript' => new JavaScriptSourceStructure($this->content),
            'css' => new CssSourceStructure($this->content),
            'twig' => new TwigSourceStructure($this->content),
            default => throw UnsupportedSourceLanguage::forStructure($this->language),
        };
    }

    /**
     * @return array{list<int>, list<int>}
     */
    private function buildLineIndex(): array
    {
        $starts = [0];
        $ends = [];
        $length = strlen($this->content);

        for ($offset = 0; $offset < $length; ++$offset) {
            if ("\r" !== $this->content[$offset] && "\n" !== $this->content[$offset]) {
                continue;
            }

            $ends[] = $offset;

            if ("\r" === $this->content[$offset]
                && $offset + 1 < $length
                && "\n" === $this->content[$offset + 1]
            ) {
                ++$offset;
            }

            if ($offset + 1 < $length) {
                $starts[] = $offset + 1;
            }
        }

        if (count($ends) < count($starts)) {
            $ends[] = $length;
        }

        return [$starts, $ends];
    }

    private static function resolveLanguage(Language|string|null $language): ?Language
    {
        if (!is_string($language)) {
            return $language;
        }

        return Languages::get($language) ?? throw UnknownSourceLanguage::fromSlug($language);
    }
}
