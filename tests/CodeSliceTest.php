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
use Alto\Code\Slicer\Exception\InvalidSourceRange;
use Alto\Code\Slicer\Exception\SourceSymbolNotFound;
use Alto\Code\Slicer\Exception\SourceTextNotFound;
use Alto\Code\Slicer\Exception\UnsupportedSourceLanguage;
use Alto\Code\Slicer\SourceRange;

final class CodeSliceTest extends TestCase
{
    public function testItRejectsARangeOutsideItsSource(): void
    {
        $this->expectException(InvalidSourceRange::class);

        new CodeSlice(CodeSource::fromString('short'), new SourceRange(0, 6));
    }

    public function testItCanNarrowAnExistingSliceByAbsoluteLineNumbers(): void
    {
        $slice = CodeSource::fromString("first\nsecond\nthird")
            ->slice()
            ->lines(2, 2);

        self::assertSame('second', $slice->content());
    }

    public function testTextSelectorsRejectAnEmptyNeedle(): void
    {
        foreach (['before', 'after'] as $method) {
            try {
                CodeSource::fromString('content')->slice()->{$method}('');
                self::fail(sprintf('%s() accepted an empty needle.', $method));
            } catch (\InvalidArgumentException) {
            }
        }

        self::addToAssertionCount(1);
    }

    public function testAfterRejectsTextOutsideTheCurrentWindow(): void
    {
        $this->expectException(SourceTextNotFound::class);

        CodeSource::fromString("inside\noutside")
            ->lines(1, 1)
            ->after('outside');
    }

    public function testItSelectsContentBetweenTextMarkers(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            before
            // example:start
                first();
                second();
            // example:end
            after
            CODE);

        $slice = $source->slice()
            ->after("// example:start\n")
            ->before("\n// example:end");

        self::assertSame("    first();\n    second();", $slice->content());
        self::assertSame(3, $slice->startLine());
        self::assertSame(4, $slice->endLine());
    }

    public function testASelectionDoesNotMutateTheOriginalSlice(): void
    {
        $slice = CodeSource::fromString('before marker after')->slice();
        $selected = $slice->after('marker ');

        self::assertSame('before marker after', $slice->content());
        self::assertSame('after', $selected->content());
    }

    public function testItExposesTheCompleteSourceAndSelectedRangeForDownstreamProjection(): void
    {
        $source = CodeSource::fromString("before\nselected\nafter");
        $slice = $source->lines(2, 2);

        self::assertSame($source, $slice->source());
        self::assertSame(7, $slice->range()->start);
        self::assertSame(15, $slice->range()->end);
        self::assertSame('selected', $slice->content());
    }

    public function testTextSearchIsLimitedToTheCurrentWindow(): void
    {
        $slice = CodeSource::fromString("marker\ninside\nmarker")
            ->lines(2, 3)
            ->before('marker');

        self::assertSame("inside\n", $slice->content());
    }

    public function testItRejectsMissingText(): void
    {
        $this->expectException(SourceTextNotFound::class);

        CodeSource::fromString('content')->slice()->before('missing');
    }

    public function testItRejectsLinesOutsideTheCurrentWindow(): void
    {
        $this->expectException(InvalidSourceRange::class);

        CodeSource::fromString("one\ntwo\nthree")
            ->lines(2, 3)
            ->lines(1, 2);
    }

    public function testItSelectsEverythingBeforeTheNextClassDeclaration(): void
    {
        $source = CodeSource::fromString(<<<'PHP'
            <?php

            declare(strict_types=1);

            #[Example]
            final class Service
            {
            }
            PHP, self::language('php'));

        $slice = $source->slice()->beforeNextClass();

        self::assertSame("<?php\n\ndeclare(strict_types=1);\n\n", $slice->content());
    }

    public function testItSelectsEverythingBeforeANamedMethod(): void
    {
        $source = CodeSource::fromFile(__DIR__ . '/Fixtures/ExampleService.php');
        $slice = $source->slice()->beforeMethod('execute');

        self::assertStringContainsString('function configure()', $slice->content());
        self::assertStringNotContainsString('function execute()', $slice->content());
    }

    public function testItSelectsEverythingAfterANamedMethod(): void
    {
        $source = CodeSource::fromFile(__DIR__ . '/Fixtures/ExampleService.php');
        $slice = $source->slice()->afterMethod('configure');

        self::assertStringNotContainsString('function configure()', $slice->content());
        self::assertStringContainsString('function execute()', $slice->content());
        self::assertStringContainsString('function finish()', $slice->content());
    }

    public function testItExtractsACompleteNamedMethodForDocumentation(): void
    {
        $slice = CodeSource::fromFile(__DIR__ . '/Fixtures/ExampleService.php')
            ->slice()
            ->method('execute');

        self::assertStringStartsWith("    /**\n     * Execute the documented example.", $slice->content());
        self::assertStringContainsString('#[\Deprecated]', $slice->content());
        self::assertStringContainsString('public function execute(): int', $slice->content());
        self::assertStringContainsString('// Keep this explanation visible in UX demos.', $slice->content());
        self::assertStringEndsWith('}', $slice->content());
        self::assertStringNotContainsString('function configure()', $slice->content());
        self::assertStringNotContainsString('function finish()', $slice->content());
    }

    public function testPhpStructureKeepsOriginalLineEndingBytes(): void
    {
        $sourceCode = <<<'PHP'
<?php

final class Example
{
    public function execute(): void
    {
        work();
    }
}
PHP;
        $method = <<<'PHP'
    public function execute(): void
    {
        work();
    }
PHP;

        foreach (["\r\n", "\r"] as $lineEnding) {
            $slice = CodeSource::fromString(
                str_replace("\n", $lineEnding, $sourceCode),
                self::language('php'),
            )->slice()->method('execute');

            self::assertSame(str_replace("\n", $lineEnding, $method), $slice->content());
        }
    }

    public function testPhpStructureAcceptsCodeWithoutAnOpeningTag(): void
    {
        $code = <<<'PHP'
final class Example
{
    public function execute(): void
    {
        work();
    }
}
PHP;

        $slice = CodeSource::fromString($code, self::language('php'))
            ->slice()
            ->class('Example')
            ->method('execute');

        self::assertSame(3, $slice->startLine());
        self::assertSame(6, $slice->endLine());
        self::assertSame(<<<'PHP'
    public function execute(): void
    {
        work();
    }
PHP, $slice->content());
    }

    public function testBeforeNextMethodIgnoresAClosureInsideThePreviousMethod(): void
    {
        $source = CodeSource::fromFile(__DIR__ . '/Fixtures/ExampleService.php');
        $slice = $source->slice()
            ->afterMethod('configure')
            ->beforeNextMethod();

        self::assertStringNotContainsString('static function', $slice->content());
        self::assertStringNotContainsString('function execute()', $slice->content());
    }

    public function testItRejectsAMissingMethod(): void
    {
        $this->expectException(SourceSymbolNotFound::class);
        $this->expectExceptionMessage('No method named "missing"');

        CodeSource::fromFile(__DIR__ . '/Fixtures/ExampleService.php')
            ->slice()
            ->beforeMethod('missing');
    }

    public function testItRejectsAMissingMethodForEveryMethodSelector(): void
    {
        $source = CodeSource::fromString('<?php final class Example {}', self::language('php'));

        foreach (['method', 'afterMethod'] as $selector) {
            try {
                $source->slice()->{$selector}('missing');
                self::fail(sprintf('%s() returned a missing method.', $selector));
            } catch (SourceSymbolNotFound) {
            }
        }

        self::addToAssertionCount(1);
    }

    public function testItRejectsMissingNextSymbols(): void
    {
        $source = CodeSource::fromString('<?php', self::language('php'));

        foreach (['beforeNextClass', 'beforeNextMethod'] as $selector) {
            try {
                $source->slice()->{$selector}();
                self::fail(sprintf('%s() returned a missing symbol.', $selector));
            } catch (SourceSymbolNotFound) {
            }
        }

        self::addToAssertionCount(1);
    }

    public function testItRejectsAMissingClassAtRuleAndMacro(): void
    {
        $cases = [
            [CodeSource::fromString('<?php', self::language('php')), 'class', 'Missing'],
            [CodeSource::fromString('.present {}', self::language('css')), 'atRule', 'missing'],
            [CodeSource::fromString('{% block present %}{% endblock %}', self::language('twig')), 'macro', 'missing'],
        ];

        foreach ($cases as [$source, $selector, $name]) {
            try {
                $source->slice()->{$selector}($name);
                self::fail(sprintf('%s() returned a missing symbol.', $selector));
            } catch (SourceSymbolNotFound) {
            }
        }

        self::addToAssertionCount(1);
    }

    public function testAnEmptySliceHasNoLines(): void
    {
        self::assertSame(0, CodeSource::fromString('marker')->slice()->before('marker')->lineCount());
    }

    public function testStructuralSelectorsRequireASupportedLanguage(): void
    {
        $this->expectException(UnsupportedSourceLanguage::class);

        CodeSource::fromString('class Example {}')
            ->slice()
            ->beforeNextClass();
    }
}
