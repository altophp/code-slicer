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

use Alto\Code\Slicer\CodeSlice;
use Alto\Code\Slicer\CodeSource;
use Alto\Code\Slicer\Exception\SourceSymbolNotFound;
use Alto\Code\Slicer\Exception\UnsupportedSourceLanguage;

final class TwigStructureTest extends TestCase
{
    private const TEMPLATE = <<<'TWIG'
        {% extends "base.html.twig" %}

        {% block title "Checkout" %}

        {# The summary a customer sees before paying. #}
        {% block summary %}
            <ul>
                {% for line in cart.lines %}
                    <li>{{ line.label }}</li>
                {% endfor %}
            </ul>

            {% block total %}
                <strong>{{ cart.total|price }}</strong>
            {% endblock %}
        {% endblock summary %}

        {% macro price(amount) %}
            <span>{{ amount|number_format(2) }}</span>
        {% endmacro %}
        TWIG;

    public function testABlockKeepsTheCommentAttachedToIt(): void
    {
        $content = self::template()->block('summary')->content();

        self::assertStringStartsWith('{# The summary a customer sees before paying. #}', $content);
        self::assertStringEndsWith('{% endblock summary %}', $content);
    }

    public function testABlockWithAValueOnItsTagHasNoEnd(): void
    {
        // `{% block title "Checkout" %}` is the whole block: it carries its
        // value and never meets an `{% endblock %}`.
        self::assertSame('{% block title "Checkout" %}', self::template()->block('title')->content());
    }

    public function testANestedBlockIsItsOwnBlock(): void
    {
        $content = self::template()->block('total')->content();

        self::assertSame("    {% block total %}\n        <strong>{{ cart.total|price }}</strong>\n    {% endblock %}", $content);
    }

    public function testAMacroIsNotABlock(): void
    {
        self::assertStringStartsWith('{% macro price(amount) %}', self::template()->macro('price')->content());

        $this->expectException(SourceSymbolNotFound::class);

        self::template()->block('price');
    }

    public function testAClosingMarkerInsideAStringDoesNotEndTheTag(): void
    {
        $source = CodeSource::fromString(<<<'TWIG'
            {% block raw %}
                {{ "a %} b"|escape }}
                <p>kept</p>
            {% endblock %}
            TWIG, self::language('twig'));

        self::assertStringContainsString('<p>kept</p>', $source->slice()->block('raw')->content());
    }

    public function testTheSameBlockNameInTwoTemplatesIsResolvedByNarrowingFirst(): void
    {
        $source = CodeSource::fromString(<<<'TWIG'
            {% block first %}
                {% block body %}one{% endblock %}
            {% endblock %}

            {% block second %}
                {% block body %}two{% endblock %}
            {% endblock %}
            TWIG, self::language('twig'));

        self::assertStringContainsString('one', $source->slice()->block('body')->content());
        self::assertStringContainsString('two', $source->slice()->block('second')->block('body')->content());
    }

    public function testATemplateHasNoClassesToSelect(): void
    {
        $this->expectException(UnsupportedSourceLanguage::class);
        $this->expectExceptionMessage('Language "twig" has no classes to select.');

        self::template()->class('anything');
    }

    public function testPlainTextAndUnrelatedBracesAreIgnored(): void
    {
        foreach (['plain text', '{x'] as $template) {
            CodeSource::fromString($template, self::language('twig'))->structure();
        }

        self::addToAssertionCount(2);
    }

    public function testAMismatchedClosingTagLeavesTheOtherRegionOpen(): void
    {
        $source = CodeSource::fromString(
            '{% macro value() %}{% endblock %}{% endmacro %}',
            self::language('twig'),
        );

        self::assertStringStartsWith('{% macro value()', $source->slice()->macro('value')->content());
    }

    public function testEscapedAndIncompleteStringsInsideTagsAreHandledSafely(): void
    {
        $escaped = CodeSource::fromString(
            '{% block title "a\\\"b" %}',
            self::language('twig'),
        );

        self::assertStringContainsString('a\\\"b', $escaped->slice()->block('title')->content());
        CodeSource::fromString('{% block title "unfinished', self::language('twig'))->structure();
        self::addToAssertionCount(1);
    }

    private static function template(): CodeSlice
    {
        return CodeSource::fromString(self::TEMPLATE, self::language('twig'))->slice();
    }
}
