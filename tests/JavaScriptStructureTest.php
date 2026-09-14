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
use Alto\Code\Slicer\SourceRange;
use Alto\Code\Slicer\Structure\JavaScriptSourceStructure;
use Alto\Language\Language;

/**
 * PHP hands its tokens over. JavaScript does not, so the adapter lexes the
 * source itself and these tests are mostly about what a lexer gets wrong when
 * it is written carelessly: braces inside a template literal, a slash that
 * opens a regular expression instead of dividing, a keyword written in a
 * comment.
 */
final class JavaScriptStructureTest extends TestCase
{
    private const MODULE = <<<'JS'
        /** Format a price without coupling the demo to the DOM. */
        export function formatPrice(amount, currency) {
            return new Intl.NumberFormat('fr', { style: 'currency', currency }).format(amount);
        }

        export class CheckoutController {
            /** Keep focus on the first invalid field. */
            async submit() {
                // The button stays disabled while this promise is pending.
                await this.checkout.submit();
            }

            static get selector() {
                return '[data-checkout]';
            }

            *lines() {
                yield* this.cart.lines;
            }

            #reset() {
                this.form.reset();
            }
        }

        class ModalController {
            async submit() {
                return 'modal';
            }
        }
        JS;

    public function testAFunctionKeepsItsJsDoc(): void
    {
        $content = self::module()->function('formatPrice')->content();

        self::assertStringStartsWith('/** Format a price', $content);
        self::assertStringContainsString('export function formatPrice(amount, currency) {', $content);
        self::assertStringEndsWith('}', $content);
    }

    public function testAnAsyncMethodKeepsItsJsDocAndItsInnerComments(): void
    {
        $content = self::module()->class('CheckoutController')->method('submit')->content();

        self::assertStringStartsWith('    /** Keep focus on the first invalid field. */', $content);
        self::assertStringContainsString('// The button stays disabled', $content);
    }

    public function testStaticGettersGeneratorsAndPrivateMethodsAreMethods(): void
    {
        $class = self::module()->class('CheckoutController');

        self::assertStringContainsString("return '[data-checkout]';", $class->method('selector')->content());
        self::assertStringContainsString('yield* this.cart.lines;', $class->method('lines')->content());
        self::assertStringContainsString('this.form.reset();', $class->method('reset')->content());
    }

    public function testTheSameMethodInTwoClassesIsResolvedByNarrowingFirst(): void
    {
        self::assertStringContainsString(
            "return 'modal';",
            self::module()->class('ModalController')->method('submit')->content(),
        );
    }

    public function testAFunctionIsNotAMethodAndAMethodIsNotAFunction(): void
    {
        try {
            self::module()->function('submit');
            self::fail('A method was returned by function().');
        } catch (SourceSymbolNotFound) {
        }

        $this->expectException(SourceSymbolNotFound::class);

        self::module()->method('formatPrice');
    }

    public function testBracesInATemplateLiteralDoNotBreakTheRange(): void
    {
        $source = self::source(<<<'JS'
            export class Renderer {
                render(item) {
                    return `<li class="${item.done ? 'done' : ''}">${`${item.label}`}</li>`;
                }

                after() {
                    return 'reached';
                }
            }
            JS);

        self::assertStringContainsString("return 'reached';", $source->slice()->method('after')->content());
        self::assertStringEndsWith('}', $source->slice()->method('render')->content());
    }

    public function testASlashCanOpenARegularExpressionOrDivide(): void
    {
        $source = self::source(<<<'JS'
            export class Parser {
                clean(value) {
                    return value.replace(/[{}]+/g, '').length / 2;
                }

                after() {
                    return 'reached';
                }
            }
            JS);

        self::assertStringContainsString("return 'reached';", $source->slice()->method('after')->content());
    }

    public function testASlashAfterAStatementHeadOpensARegularExpression(): void
    {
        // `)` normally ends an expression, so a slash after it divides. Not
        // after the head of an `if`: reading that as a division swallows every
        // brace until the next slash, which moves the end of the method.
        $source = self::source(<<<'JS'
            export class Guard {
                run(value) {
                    if (value) /[{]/.test(value);

                    return 'ran';
                }

                after() {
                    return 'reached';
                }
            }
            JS);

        self::assertStringContainsString("return 'ran';", $source->slice()->method('run')->content());
        self::assertStringContainsString("return 'reached';", $source->slice()->method('after')->content());
    }

    public function testAKeywordInACommentOrAStringIsNotADeclaration(): void
    {
        $source = self::source(<<<'JS'
            // export function ghost() {}
            const template = 'class Impostor { phantom() {} }';

            export function real() {
                return template;
            }
            JS);

        self::assertStringContainsString('return template;', $source->slice()->function('real')->content());

        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->function('ghost');
    }

    public function testATypeScriptDeclarationKeepsItsDecoratorAndItsGenerics(): void
    {
        $source = self::source(<<<'TS'
            @Controller({ path: '/checkout' })
            export abstract class CheckoutService<T extends Payable> implements Payable {
                public async total<K>(key: K): Promise<number> {
                    return 0;
                }

                protected abstract validate(input: T): void;
            }

            export function parse<T>(raw: string): T {
                return JSON.parse(raw) as T;
            }
            TS, self::language('typescript'));

        $class = $source->slice()->class('CheckoutService');

        self::assertStringStartsWith("@Controller({ path: '/checkout' })", $class->content());
        self::assertStringContainsString('public async total<K>(key: K): Promise<number> {', $class->method('total')->content());
        self::assertStringContainsString('return JSON.parse(raw) as T;', $source->slice()->function('parse')->content());
    }

    public function testATypeScriptDeclarationWithNoBodyIsNotASymbol(): void
    {
        // An abstract member and an overload signature declare a shape, not a
        // fragment anyone can show. Both end at a semicolon.
        $source = self::source(<<<'TS'
            export abstract class Service {
                protected abstract validate(input: string): void;
            }
            TS, self::language('typescript'));

        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->method('validate');
    }

    public function testAnInterfaceMemberIsNotAMethod(): void
    {
        $source = self::source(<<<'TS'
            export interface Payable {
                total(): number;
            }
            TS, self::language('typescript'));

        $this->expectException(SourceSymbolNotFound::class);

        $source->slice()->method('total');
    }

    public function testTheStructureCanFindNextSymbolsDirectly(): void
    {
        $structure = new JavaScriptSourceStructure(self::MODULE);
        $within = new SourceRange(0, strlen(self::MODULE));

        self::assertSame('CheckoutController', $structure->nextClass($within)?->name);
        self::assertSame('submit', $structure->nextMethod($within)?->name);
        self::assertNull($structure->nextClass(new SourceRange(strlen(self::MODULE), strlen(self::MODULE))));
    }

    public function testLexerEdgeCasesDoNotCreateOrTruncateDeclarations(): void
    {
        $sources = [
            'const number = 12345;',
            '/a\\/b/g.test(value);',
            "const broken = /a\nnext();",
            'const broken = /unfinished',
            'const escaped = "a\\\"b";',
            'const broken = "unfinished',
            'const escaped = `a\\`b`;',
            'const nested = `${{ value: 1 }}`;',
            'const broken = `unfinished',
            'if /* comment */ (value) /a/.test(value);',
            'return /* comment */ /a/.test(value);',
            '"value" / 2;',
            ') /a/.test(value);',
            '(value) / 2;',
        ];

        foreach ($sources as $code) {
            new JavaScriptSourceStructure($code);
        }

        self::addToAssertionCount(count($sources));
    }

    public function testMalformedDeclarationsAreIgnoredSafely(): void
    {
        $sources = [
            'class',
            'class {}',
            'class MissingBody',
            'class Broken {',
            'function',
            'function *',
            'function named',
            'function named()',
            'function named<T;',
            'function named<T',
            'class Example { get() {} static() {} }',
            'class Example { method<T;() {} }',
            'class Example { method<T() {} }',
            'class Example { field = { nested: true }; after() {} }',
            'obj./* comment */function;',
            'class Example /* comment */ {}',
            '@Decorator class Example {}',
            '@Decorator( class Example {}',
            '@(value) class Example {}',
            ")\nclass Example {}",
        ];

        foreach ($sources as $code) {
            new JavaScriptSourceStructure($code);
        }

        self::addToAssertionCount(count($sources));
    }

    private static function module(): CodeSlice
    {
        return self::source(self::MODULE)->slice();
    }

    private static function source(string $code, ?Language $language = null): CodeSource
    {
        return CodeSource::fromString($code, $language ?? self::language('javascript'));
    }
}
