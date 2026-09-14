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
use Alto\Code\Slicer\Exception\SourceSymbolNotFound;
use Alto\Code\Slicer\SourceRange;
use Alto\Code\Slicer\Structure\PhpSourceStructure;

/**
 * What counts as a class or a method, and what only looks like one.
 *
 * A structural selector reads the token stream, so text that spells `function`
 * or `class` inside a comment, a string or a heredoc must never become a
 * symbol. These tests hold that line, and record the rule the parser applies
 * when a name appears more than once.
 */
final class PhpStructureTest extends TestCase
{
    public function testAWordInACommentIsNotAMethod(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                // public function ghost(): void
                /* public function phantom(): void */
                /** public function spectre(): void */
                public function real(): void
                {
                }
            }
            CODE, self::language('php'));

        $found = array_values(array_filter(
            ['ghost', 'phantom', 'spectre'],
            static fn(string $name): bool => self::isMethod($source, $name),
        ));

        self::assertSame([], $found, 'Names written inside a comment were read as methods.');
        self::assertStringContainsString('public function real(): void', $source->slice()->method('real')->content());
    }

    public function testAWordInAStringIsNotASymbol(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                public function real(): void
                {
                    $a = 'public function ghost(): void {}';
                    $b = "final class Impostor {}";
                }
            }
            CODE, self::language('php'));

        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->method('ghost');
    }

    public function testAWordInAHeredocIsNotASymbol(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                public function real(): string
                {
                    return <<<'TEMPLATE'
                        final class Impostor
                        {
                            public function ghost(): void
                            {
                            }
                        }
                        TEMPLATE;
                }
            }
            CODE, self::language('php'));

        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->method('ghost');
    }

    public function testAnAnonymousClassDoesNotOwnMethods(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                public function build(): object
                {
                    return new class {
                        public function hidden(): void
                        {
                        }
                    };
                }
            }
            CODE, self::language('php'));

        // The anonymous class carries no name, so it is not a class the window
        // can be narrowed to, and what it holds is not addressable either.
        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->method('hidden');
    }

    public function testAMethodKeepsTheAttributesAndDocblockAboveIt(): void
    {
        $slice = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                /**
                 * The one that matters.
                 */
                #[\Deprecated]
                #[Route('/here', name: 'here')]
                public function documented(): void
                {
                }
            }
            CODE, self::language('php'))
            ->slice()
            ->method('documented');

        self::assertStringStartsWith("    /**\n     * The one that matters.", $slice->content());
        self::assertStringContainsString('#[\Deprecated]', $slice->content());
        self::assertStringContainsString("#[Route('/here', name: 'here')]", $slice->content());
        self::assertStringEndsWith('}', $slice->content());
    }

    public function testASemicolonInsideAnAttributeDoesNotCutTheDeclaration(): void
    {
        $slice = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                #[Query('SELECT 1; SELECT 2')]
                public function documented(): void
                {
                }
            }
            CODE, self::language('php'))
            ->slice()
            ->method('documented');

        self::assertStringStartsWith('    #[Query(', $slice->content());
    }

    public function testAnAbstractMethodEndsAtItsSemicolon(): void
    {
        $slice = CodeSource::fromString(<<<'CODE'
            <?php

            abstract class Example
            {
                abstract public function contract(): void;

                public function after(): void
                {
                }
            }
            CODE, self::language('php'))
            ->slice()
            ->method('contract');

        self::assertSame('    abstract public function contract(): void;', $slice->content());
    }

    public function testTheFirstMatchWinsWhenTwoClassesShareAMethodName(): void
    {
        // Not an arbitrary choice to leave undocumented: two classes in one
        // file can both answer to `render`, and the window is what
        // disambiguates them. Narrow it first, then ask.
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            final class First
            {
                public function render(): string
                {
                    return 'first';
                }
            }

            final class Second
            {
                public function render(): string
                {
                    return 'second';
                }
            }
            CODE, self::language('php'));

        self::assertStringContainsString("return 'first';", $source->slice()->method('render')->content());

        $inSecond = $source->slice()->after('final class Second')->method('render');

        self::assertStringContainsString("return 'second';", $inSecond->content());
    }

    public function testAMethodOutsideTheCurrentWindowIsNotFound(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            final class Example
            {
                public function first(): void
                {
                }

                public function second(): void
                {
                }
            }
            CODE, self::language('php'));

        $this->expectException(SourceSymbolNotFound::class);
        $this->expectExceptionMessage('No method named "first"');

        $source->slice()->afterMethod('first')->method('first');
    }

    public function testAFunctionOutsideAClassIsNotAMethod(): void
    {
        $source = CodeSource::fromString(<<<'CODE'
            <?php

            function helper(): void
            {
            }

            final class Example
            {
                public function member(): void
                {
                }
            }
            CODE, self::language('php'));

        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->method('helper');
    }

    public function testTheStructureCanFindTheNextAndNamedSymbolsDirectly(): void
    {
        $code = '<?php final class Example { public function run(): void {} }';
        $structure = new PhpSourceStructure($code);
        $within = new SourceRange(0, strlen($code));

        self::assertSame('Example', $structure->nextClass($within)?->name);
        self::assertSame('Example', $structure->classNamed('Example', $within)?->name);
        self::assertSame('run', $structure->nextMethod($within)?->name);
    }

    public function testIncompleteDeclarationsAreIgnoredSafely(): void
    {
        $sources = [
            '<?php }',
            '<?php class',
            '<?php class Example;',
            '<?php class Example',
            '<?php class Example {',
            '<?php class Example { function () {} }',
            '<?php class Example { function broken() }',
            "<?php;class FlushLeft{}",
            "<?php;\vclass OddWhitespace{}",
        ];

        foreach ($sources as $code) {
            new PhpSourceStructure($code);
        }

        self::addToAssertionCount(count($sources));
    }

    private static function isMethod(CodeSource $source, string $name): bool
    {
        try {
            $source->slice()->method($name);

            return true;
        } catch (SourceSymbolNotFound) {
            return false;
        }
    }
}
