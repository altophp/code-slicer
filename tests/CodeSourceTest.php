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
use Alto\Code\Slicer\Exception\InvalidSourceRange;
use Alto\Code\Slicer\Exception\SourceFileNotReadable;
use Alto\Code\Slicer\Exception\UnknownSourceLanguage;
use Alto\Code\Slicer\SourceRange;

final class CodeSourceTest extends TestCase
{
    public function testASourceRangeRejectsInvalidOffsets(): void
    {
        $this->expectException(InvalidSourceRange::class);

        new SourceRange(2, 1);
    }

    public function testItCreatesASourceFromAString(): void
    {
        $source = CodeSource::fromString(
            "first\r\nsecond\rthird",
            self::language('php'),
            'Example.php',
        );

        self::assertSame("first\r\nsecond\rthird", $source->content());
        self::assertSame(self::language('php'), $source->language());
        self::assertSame('Example.php', $source->name());
        self::assertSame(3, $source->lineCount());
    }

    public function testItResolvesAStringLanguageSlug(): void
    {
        $source = CodeSource::fromString('final class Example {}', language: 'php');

        self::assertSame(self::language('php'), $source->language());
        self::assertSame('final class Example {}', $source->slice()->class('Example')->content());
    }

    public function testAnExplicitStringLanguageOverridesTheFilename(): void
    {
        $source = CodeSource::fromFile(__DIR__ . '/Fixtures/composer.json', language: 'php');

        self::assertSame(self::language('php'), $source->language());
    }

    public function testItRejectsAnUnknownStringLanguageSlug(): void
    {
        $this->expectException(UnknownSourceLanguage::class);
        $this->expectExceptionMessage('Unknown source language "unknown".');

        CodeSource::fromString('anything', language: 'unknown');
    }

    public function testItCreatesASourceFromAFileAndDetectsItsLanguage(): void
    {
        $path = __DIR__ . '/Fixtures/ExampleService.php';
        $source = CodeSource::fromFile($path);

        self::assertSame(self::language('php'), $source->language());
        self::assertSame($path, $source->name());
        self::assertStringContainsString('final class ExampleService', $source->content());
    }

    public function testItRejectsAnUnreadableFile(): void
    {
        $this->expectException(SourceFileNotReadable::class);

        CodeSource::fromFile(__DIR__ . '/Fixtures/missing.php');
    }

    public function testItCreatesASnippetFromInclusiveLineNumbers(): void
    {
        $slice = CodeSource::fromString("first\nsecond\nthird\n")
            ->lines(2, 3);

        self::assertSame("second\nthird", $slice->content());
        self::assertSame(2, $slice->startLine());
        self::assertSame(3, $slice->endLine());
        self::assertSame(2, $slice->lineCount());
    }

    public function testItRejectsAnInvalidLineRange(): void
    {
        $this->expectException(InvalidSourceRange::class);

        CodeSource::fromString("first\nsecond")->lines(2, 3);
    }

    public function testItRejectsAnOffsetOutsideTheSource(): void
    {
        $this->expectException(InvalidSourceRange::class);

        CodeSource::fromString('short')->lineAtOffset(6);
    }

    public function testTheFullSnippetKeepsTheSourceMetadata(): void
    {
        $slice = CodeSource::fromString(
            "<?php\n",
            self::language('php'),
            'inline.php',
        )->slice();

        self::assertSame("<?php\n", $slice->content());
        self::assertSame(self::language('php'), $slice->language());
        self::assertSame('inline.php', $slice->sourceName());
        self::assertSame(1, $slice->startLine());
        self::assertSame(1, $slice->endLine());
    }

    /**
     * Line numbers are the one promise the whole package rests on, so the
     * shapes that usually break a line count get their own case.
     */
    public function testAnEmptySourceHasOneEmptyLine(): void
    {
        $source = CodeSource::fromString('');

        self::assertSame('', $source->content());
        self::assertSame(1, $source->lineCount());
        self::assertSame(1, $source->slice()->startLine());
        self::assertSame(1, $source->slice()->endLine());
    }

    public function testALastLineWithoutABreakStillCounts(): void
    {
        $source = CodeSource::fromString("first\nsecond");

        self::assertSame(2, $source->lineCount());
        self::assertSame('second', $source->lines(2, 2)->content());
    }

    public function testATrailingBreakDoesNotOpenAnotherLine(): void
    {
        $source = CodeSource::fromString("first\nsecond\n");

        self::assertSame(2, $source->lineCount());

        // A range stops at the end of its last line, before the break that
        // ends it, whether or not the source carries one.
        self::assertSame('second', $source->lines(2, 2)->content());
    }

    public function testCrlfAndCrCountAsOneBreakEach(): void
    {
        $lf = CodeSource::fromString("first\nsecond\nthird");
        $crlf = CodeSource::fromString("first\r\nsecond\r\nthird");
        $cr = CodeSource::fromString("first\rsecond\rthird");

        self::assertSame("first\nsecond\nthird", $lf->content());
        self::assertSame("first\r\nsecond\r\nthird", $crlf->content());
        self::assertSame("first\rsecond\rthird", $cr->content());
        self::assertSame(3, $crlf->lineCount());
        self::assertSame(3, $cr->lineCount());
        self::assertSame('second', $crlf->lines(2, 2)->content());
        self::assertSame('second', $cr->lines(2, 2)->content());
        self::assertSame("second\r\nthird", $crlf->lines(2, 3)->content());
        self::assertSame("second\rthird", $cr->lines(2, 3)->content());
        self::assertSame(2, $crlf->lines(2, 3)->lineCount());
        self::assertSame(2, $cr->lines(2, 3)->lineCount());
    }

    public function testAFileWithCrlfIsReadWithTheSameLineNumbers(): void
    {
        $path = sys_get_temp_dir() . '/codeslicer-crlf-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($path, "<?php\r\n\r\nfinal class Example\r\n{\r\n}\r\n");

        try {
            $source = CodeSource::fromFile($path);

            self::assertSame(5, $source->lineCount());
            self::assertSame('final class Example', $source->lines(3, 3)->content());
            self::assertSame("<?php\r\n\r\nfinal class Example\r\n{\r\n}\r\n", $source->content());
        } finally {
            unlink($path);
        }
    }

    public function testAnUnknownExtensionFallsBackToPlainText(): void
    {
        $source = CodeSource::fromString('anything', name: 'notes.unknown');

        self::assertNull($source->language());
    }

    public function testItKeepsKnownNonStructuralLanguageMetadata(): void
    {
        $json = CodeSource::fromFile(__DIR__ . '/Fixtures/composer.json');
        $yaml = CodeSource::fromFile(__DIR__ . '/Fixtures/services.yaml');

        self::assertSame('json', $json->language()?->slug);
        self::assertSame('yaml', $yaml->language()?->slug);
        self::assertSame('    "name": "alto/example"', $json->lines(2, 2)->content());
        self::assertSame('    Alto\Example\Service: ~', $yaml->lines(2, 2)->content());
    }
}
