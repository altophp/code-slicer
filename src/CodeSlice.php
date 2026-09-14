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
use Alto\Code\Slicer\Exception\SourceSymbolNotFound;
use Alto\Code\Slicer\Exception\SourceTextNotFound;
use Alto\Code\Slicer\Exception\UnsupportedSourceLanguage;
use Alto\Code\Slicer\Structure\ClassStructure;
use Alto\Code\Slicer\Structure\FunctionStructure;
use Alto\Code\Slicer\Structure\MethodStructure;
use Alto\Code\Slicer\Structure\RuleStructure;
use Alto\Code\Slicer\Structure\TemplateStructure;
use Alto\Language\Language;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class CodeSlice
{
    /**
     * @internal Create slices through CodeSource.
     */
    public function __construct(
        private CodeSource $source,
        private SourceRange $range,
    ) {
        $sourceRange = new SourceRange(0, strlen($source->content()));

        if (!$sourceRange->contains($range)) {
            throw InvalidSourceRange::fromOffsets($range->start, $range->end);
        }
    }

    public function lines(int $start, int $end): self
    {
        $range = $this->source->rangeForLines($start, $end);

        if (!$this->range->contains($range)) {
            throw InvalidSourceRange::fromLines($start, $end);
        }

        return $this->withRange($range);
    }

    public function after(string $text): self
    {
        if ('' === $text) {
            throw new \InvalidArgumentException('The searched text cannot be empty.');
        }

        $position = strpos($this->source->content(), $text, $this->range->start);

        if (false === $position || $position + strlen($text) > $this->range->end) {
            throw SourceTextNotFound::forText($text);
        }

        return $this->withRange(new SourceRange($position + strlen($text), $this->range->end));
    }

    public function before(string $text): self
    {
        if ('' === $text) {
            throw new \InvalidArgumentException('The searched text cannot be empty.');
        }

        $position = strpos($this->source->content(), $text, $this->range->start);

        if (false === $position || $position + strlen($text) > $this->range->end) {
            throw SourceTextNotFound::forText($text);
        }

        return $this->withRange(new SourceRange($this->range->start, $position));
    }

    public function beforeNextClass(): self
    {
        $class = $this->classes()->nextClass($this->range);

        if (null === $class) {
            throw SourceSymbolNotFound::next('class');
        }

        return $this->withRange(new SourceRange($this->range->start, $class->declaration->start));
    }

    public function beforeNextMethod(): self
    {
        $method = $this->methods()->nextMethod($this->range);

        if (null === $method) {
            throw SourceSymbolNotFound::next('method');
        }

        return $this->withRange(new SourceRange($this->range->start, $method->declaration->start));
    }

    public function beforeMethod(string $name): self
    {
        $method = $this->methods()->methodNamed($name, $this->range);

        if (null === $method) {
            throw SourceSymbolNotFound::named('method', $name);
        }

        return $this->withRange(new SourceRange($this->range->start, $method->declaration->start));
    }

    public function method(string $name): self
    {
        $method = $this->methods()->methodNamed($name, $this->range);

        if (null === $method) {
            throw SourceSymbolNotFound::named('method', $name);
        }

        return $this->withRange($method->declaration);
    }

    public function afterMethod(string $name): self
    {
        $method = $this->methods()->methodNamed($name, $this->range);

        if (null === $method) {
            throw SourceSymbolNotFound::named('method', $name);
        }

        return $this->withRange(new SourceRange($method->declaration->end, $this->range->end));
    }

    /**
     * Narrow to a named class, which is how two methods of the same name in one
     * file are told apart: narrow first, then ask.
     */
    public function class(string $name): self
    {
        $class = $this->classes()->classNamed($name, $this->range);

        if (null === $class) {
            throw SourceSymbolNotFound::named('class', $name);
        }

        return $this->withRange($class->declaration);
    }

    /**
     * A function declared outside any class. Asking for one never returns a
     * method, and asking for a method never returns one of these.
     */
    public function function(string $name): self
    {
        $function = $this->functions()->functionNamed($name, $this->range);

        if (null === $function) {
            throw SourceSymbolNotFound::named('function', $name);
        }

        return $this->withRange($function->declaration);
    }

    public function rule(string $selector): self
    {
        $rule = $this->rules()->ruleNamed($selector, $this->range);

        if (null === $rule) {
            throw SourceSymbolNotFound::named('rule', $selector);
        }

        return $this->withRange($rule->declaration);
    }

    /**
     * The prelude is optional: `atRule('media')` takes the first one whatever
     * it carries, `atRule('media', '(width >= 48rem)')` takes that one.
     */
    public function atRule(string $name, ?string $prelude = null): self
    {
        $atRule = $this->rules()->atRuleNamed($name, $prelude, $this->range);

        if (null === $atRule) {
            throw SourceSymbolNotFound::named('at-rule', '@' . $name . (null === $prelude ? '' : ' ' . $prelude));
        }

        return $this->withRange($atRule->declaration);
    }

    public function block(string $name): self
    {
        $block = $this->template()->blockNamed($name, $this->range);

        if (null === $block) {
            throw SourceSymbolNotFound::named('block', $name);
        }

        return $this->withRange($block->declaration);
    }

    public function macro(string $name): self
    {
        $macro = $this->template()->macroNamed($name, $this->range);

        if (null === $macro) {
            throw SourceSymbolNotFound::named('macro', $name);
        }

        return $this->withRange($macro->declaration);
    }

    private function classes(): ClassStructure
    {
        $structure = $this->source->structure();

        return $structure instanceof ClassStructure
            ? $structure
            : throw UnsupportedSourceLanguage::forCapability($this->source->language(), 'classes');
    }

    private function methods(): MethodStructure
    {
        $structure = $this->source->structure();

        return $structure instanceof MethodStructure
            ? $structure
            : throw UnsupportedSourceLanguage::forCapability($this->source->language(), 'methods');
    }

    private function functions(): FunctionStructure
    {
        $structure = $this->source->structure();

        return $structure instanceof FunctionStructure
            ? $structure
            : throw UnsupportedSourceLanguage::forCapability($this->source->language(), 'functions');
    }

    private function rules(): RuleStructure
    {
        $structure = $this->source->structure();

        return $structure instanceof RuleStructure
            ? $structure
            : throw UnsupportedSourceLanguage::forCapability($this->source->language(), 'rules');
    }

    private function template(): TemplateStructure
    {
        $structure = $this->source->structure();

        return $structure instanceof TemplateStructure
            ? $structure
            : throw UnsupportedSourceLanguage::forCapability($this->source->language(), 'blocks');
    }

    public function source(): CodeSource
    {
        return $this->source;
    }

    public function range(): SourceRange
    {
        return $this->range;
    }

    public function content(): string
    {
        return substr(
            $this->source->content(),
            $this->range->start,
            $this->range->end - $this->range->start,
        );
    }

    public function language(): ?Language
    {
        return $this->source->language();
    }

    /**
     * The label of the source this window was cut from. A path when the source
     * was read from a file, whatever the caller named it otherwise, and never
     * a guarantee that the two can be told apart. See `CodeSource::name()`.
     */
    public function sourceName(): ?string
    {
        return $this->source->name();
    }

    public function startLine(): int
    {
        return $this->source->lineAtOffset($this->range->start);
    }

    public function endLine(): int
    {
        $lastOffset = $this->range->end > $this->range->start
            ? $this->range->end - 1
            : $this->range->start;

        return $this->source->lineAtOffset($lastOffset);
    }

    public function lineCount(): int
    {
        if ('' === $this->content()) {
            return 0;
        }

        return $this->endLine() - $this->startLine() + 1;
    }

    private function withRange(SourceRange $range): self
    {
        return new self($this->source, $range);
    }
}
