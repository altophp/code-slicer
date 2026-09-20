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
use Alto\Code\Slicer\Exception\SourceTextNotFound;

final class LineBoundaryTest extends TestCase
{
    public function testInclusiveBoundariesSelectActualSourceLines(): void
    {
        foreach (["\n", "\r\n", "\r"] as $eol) {
            $code = implode($eol, [
                '<main>',
                '    <twig:Card>',
                '        <h2>Order</h2>',
                '',
                '        <p>Confirmed</p>',
                '    </twig:Card>',
                '</main>',
            ]);
            $source = CodeSource::fromString($code, name: 'checkout.html.twig');
            $original = $source->slice();
            $slice = $original->fromLine('<twig:Card>')->throughLine('</twig:Card>');

            self::assertSame(implode($eol, [
                '    <twig:Card>',
                '        <h2>Order</h2>',
                '',
                '        <p>Confirmed</p>',
                '    </twig:Card>',
            ]), $slice->content());
            self::assertSame(2, $slice->startLine());
            self::assertSame(6, $slice->endLine());
            self::assertSame($source, $slice->source());
            self::assertSame($source->rangeForLines(2, 6)->start, $slice->range()->start);
            self::assertSame($source->rangeForLines(2, 6)->end, $slice->range()->end);
            self::assertSame($code, $original->content());
        }
    }

    public function testInclusiveSearchUsesTheFirstMatchInsideTheSlice(): void
    {
        $source = CodeSource::fromString("call();\nfirst\ncall();\nsecond\ncall();\nlast");
        $slice = $source->lines(2, 6);

        self::assertSame("call();\nsecond\ncall();\nlast", $slice->fromLine('call()')->content());
        self::assertSame("first\ncall();", $slice->throughLine('call()')->content());
        self::assertSame(3, $slice->fromLine('call()')->startLine());
    }

    public function testInclusiveBoundariesCanSelectOneLineIncludingAtFileEdges(): void
    {
        foreach (['', "\n", "\r\n", "\r"] as $eol) {
            $source = CodeSource::fromString('    <twig:ProductSearch :query="query" />' . $eol);
            $slice = $source->slice()->fromLine('<twig:ProductSearch')->throughLine('/>');

            self::assertSame('    <twig:ProductSearch :query="query" />', $slice->content());
            self::assertSame(1, $slice->lineCount());
        }
    }

    public function testInclusiveBoundariesPreserveUtf8AndMixedLineEndings(): void
    {
        $slice = CodeSource::fromString("préface\r\n\tété();\n\n\tfin();\rsuffix")
            ->slice()->fromLine('été()')->throughLine('fin()');

        self::assertSame("\tété();\n\n\tfin();", $slice->content());
        self::assertSame(2, $slice->startLine());
        self::assertSame(4, $slice->endLine());
    }

    public function testMarkersDoNotRequireCommentSyntaxIndentationOrLineEndings(): void
    {
        foreach (["\n", "\r\n", "\r"] as $eol) {
            foreach (['<!-- %s -->', '// %s', '# %s', '{# %s #}', '/* %s */'] as $comment) {
                $code = implode($eol, [
                    'outside',
                    '    ' . sprintf($comment, 'checkout:start'),
                    '    first();',
                    '',
                    '    second();',
                    "\t" . sprintf($comment, 'checkout:end'),
                    'outside',
                ]);
                $source = CodeSource::fromString($code, name: 'example');
                $original = $source->slice();
                $slice = $original->afterLine('checkout:start')->beforeLine('checkout:end');

                self::assertSame('    first();' . $eol . $eol . '    second();', $slice->content());
                self::assertSame(3, $slice->startLine());
                self::assertSame(5, $slice->endLine());
                self::assertSame($source, $slice->source());
                self::assertSame(strpos($code, '    first();'), $slice->range()->start);
                self::assertSame($code, $original->content());
            }
        }
    }

    public function testSearchUsesTheFirstMatchWithinTheCurrentSlice(): void
    {
        $source = CodeSource::fromString("marker\nfirst\nmarker\nsecond\nmarker\nlast");
        $slice = $source->lines(2, 6);

        self::assertSame('first', $slice->beforeLine('marker')->content());
        self::assertSame("second\nmarker\nlast", $slice->afterLine('marker')->content());
        self::assertSame(4, $slice->afterLine('marker')->startLine());
    }

    public function testMarkersAtTheEdgesCanProduceEmptySlices(): void
    {
        foreach (['marker', "marker\n", "marker\r\n", "marker\r"] as $code) {
            foreach (['afterLine', 'beforeLine'] as $method) {
                $slice = CodeSource::fromString($code)->slice()->{$method}('marker');

                self::assertSame('', $slice->content());
                self::assertSame(0, $slice->lineCount());
            }
        }

        $slice = CodeSource::fromString("start\nend")->slice()->afterLine('start')->beforeLine('end');
        self::assertSame('', $slice->content());
    }

    public function testPartialLinesNeverExpandTheCurrentSlice(): void
    {
        $slice = CodeSource::fromString("prefix marker suffix\nlast")
            ->slice()->after('prefix ')->before(' suffix');

        self::assertSame('', $slice->beforeLine('marker')->content());
        self::assertSame($slice->range()->start, $slice->beforeLine('marker')->range()->end);
        self::assertSame('', $slice->afterLine('marker')->content());
        self::assertSame($slice->range()->end, $slice->afterLine('marker')->range()->start);
        self::assertSame('marker', $slice->fromLine('marker')->content());
        self::assertSame('marker', $slice->throughLine('marker')->content());
        self::assertEquals($slice->range(), $slice->fromLine('marker')->throughLine('marker')->range());
    }

    public function testMissingOrPartiallyExcludedMarkersAreRejected(): void
    {
        foreach (['afterLine', 'beforeLine', 'fromLine', 'throughLine'] as $method) {
            foreach (['outside', 'mar', 'Marker'] as $code) {
                $source = CodeSource::fromString("marker\n" . $code . "\nmarker");
                try {
                    $source->lines(2, 2)->{$method}('marker');
                    self::fail('A marker outside the current slice was accepted.');
                } catch (SourceTextNotFound) {
                    self::addToAssertionCount(1);
                }
            }

            try {
                CodeSource::fromString('marker')->slice()->before('ker')->{$method}('marker');
                self::fail('A partially excluded marker was accepted.');
            } catch (SourceTextNotFound) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNeedlesMustBeNonEmptyAndStayOnOneLine(): void
    {
        foreach (['afterLine', 'beforeLine', 'fromLine', 'throughLine'] as $method) {
            foreach (['', "a\nb", "a\rb", "a\r\nb"] as $text) {
                $this->expectInvalidNeedle($method, $text);
            }
        }
    }

    public function testUtf8MarkersAndMixedLineEndingsKeepExactContent(): void
    {
        $slice = CodeSource::fromString("préface\r\n<!-- début -->\r\n\tété();\n\n<!-- fin -->\rsuffix")
            ->slice()->afterLine('début')->beforeLine('fin');

        self::assertSame("\tété();\n", $slice->content());
        self::assertSame(3, $slice->startLine());
    }

    private function expectInvalidNeedle(string $method, string $text): void
    {
        try {
            CodeSource::fromString($text)->slice()->{$method}($text);
            self::fail('An invalid marker was accepted.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
