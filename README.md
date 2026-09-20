# ALTO Code Slicer

Extract immutable source-code slices by line, text, or language structure while preserving exact
byte ranges and line numbers.

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-00B7FF?logoColor=00B7FF&labelColor=050608)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/altophp/code-slicer/CI.yml?branch=main&label=Tests&labelColor=050608&color=00B7FF)
&nbsp; [![Packagist](https://img.shields.io/packagist/v/alto/code-slicer?label=Packagist&labelColor=050608&color=00B7FF)](https://packagist.org/packages/alto/code-slicer)
&nbsp; ![License](https://img.shields.io/github/license/altophp/code-slicer?label=License&labelColor=050608&color=00B7FF)
&nbsp; [![GitHub Sponsors](https://img.shields.io/github/sponsors/smnandre?logo=githubsponsors&logoColor=00B7FF&label=%20Sponsor&labelColor=050608&color=00B7FF)](https://github.com/sponsors/smnandre)

Code Slicer loads a complete source and progressively narrows an immutable `CodeSlice`. Every slice
keeps its language, source name, and original line numbers.

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('src/Command/BuildCommand.php')
    ->slice()
    ->method('execute');

echo $slice->content();
echo $slice->startLine();
```

Selection stops before presentation. Code Slicer returns source ranges and leaves syntax tokens,
annotations, highlighting, and rendering to downstream consumers.

## Installation

```bash
composer require alto/code-slicer
```

Code Slicer requires PHP 8.4 or later, the tokenizer extension, and `alto/language`.

## Quick start

Select a PHP method from an in-memory source:

```php
use Alto\Code\Slicer\CodeSource;

$source = CodeSource::fromString(
    <<<'PHP'
final class Checkout
{
    public function complete(): void
    {
        // ...
    }
}
PHP,
    'php',
    'Checkout.php',
);

$slice = $source
    ->slice()
    ->method('complete');

echo $slice->content();
```

Every selection returns a new `CodeSlice`; the source and previous slices remain unchanged.

## Creating sources

`fromFile()` reads a local file and resolves its language through `alto/language`:

```php
$source = CodeSource::fromFile('assets/checkout_controller.js');

$source->language()?->slug; // javascript
$source->name();            // assets/checkout_controller.js
$source->lineCount();
```

For in-memory code, pass an optional language explicitly. The optional name is metadata and does
not imply that a file exists:

```php
use Alto\Code\Slicer\CodeSource;

$source = CodeSource::fromString($code, 'php', 'Example.php');
```

Unknown and unnamed sources carry `null` as their language. Text and line selectors still work;
structural selectors fail explicitly.

## Selecting code

Line numbers are one-based, inclusive, and always refer to the complete source:

```php
$slice = $source->lines(20, 42);
$narrower = $slice->lines(24, 30);
```

Text selectors are exact, case-sensitive, and limited to the current slice:

```php
$slice = $source->slice()
    ->after('// example:start')
    ->before('// example:end');
```

Missing boundaries and attempts to expand a slice throw explicit exceptions rather than returning
an approximate result.

## Structural selectors

The available selectors follow each language's own vocabulary:

| Language | Selectors |
| --- | --- |
| PHP | `class()`, `method()`, `beforeNextClass()`, `beforeNextMethod()`, `beforeMethod()`, `afterMethod()` |
| JavaScript and TypeScript | PHP selectors plus `function()` |
| CSS | `rule()`, `atRule()` |
| Twig | `block()`, `macro()` |

Selectors compose to resolve scope and ambiguity:

```php
$slice = $source->slice()
    ->class('CheckoutController')
    ->method('connect');
```

CSS at-rules can optionally include their prelude:

```php
$slice = $source->slice()->atRule('media', '(width >= 48rem)');
```

## Projection

`source()` and `range()` expose the complete source and the selected half-open byte range:

```php
$source = $slice->source();
$range = $slice->range();

$source->content(); // complete source
$range->start;      // inclusive
$range->end;        // exclusive
```

This lets a downstream adapter parse or highlight the complete source first, then project its text
and annotations onto the selected range. `content()` remains the exact, unmodified source region.

## Package boundary

`CodeSlice` is the raw result of source extraction. Its complete source and byte range let a
consumer analyze the full context before projecting the selected region into its own model.

Code Slicer deliberately provides no HTML, SVG, Markdown, syntax tokens, themes, remote loaders, or
source rewriting.

## Documentation

- [Documentation home](docs/index.md)
- [Installation](docs/installation.md)
- [Getting started](docs/getting-started.md)
- [Selectors](docs/selectors.md)
- [Languages](docs/languages/index.md)

## Contributing

Contributions of all kinds are welcome. Visit the
[project on GitHub](https://github.com/altophp/code-slicer) to
[report a bug](https://github.com/altophp/code-slicer/issues/new),
[suggest a feature](https://github.com/altophp/code-slicer/issues/new), or
[open a pull request](https://github.com/altophp/code-slicer/pulls).

Before submitting code, run:

```bash
# Runs PHP CS Fixer, PHPStan, and PHPUnit
composer qa
```

Changes to public behavior should include tests and documentation.

## Support

ALTO Code Slicer is open source and independently maintained by
[Simon André](https://smnandre.dev). If it is useful to your work, you can
support its continued development through
[GitHub Sponsors](https://github.com/sponsors/smnandre).

Sharing the package or
[starring it on GitHub](https://github.com/altophp/code-slicer) also helps.

## License

ALTO Code Slicer is released by [ALTO PHP](https://altophp.com) under the
[MIT License](LICENSE).
