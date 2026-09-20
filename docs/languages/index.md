# Languages

Choose literal selectors for any text source or structural selectors for the
languages Code Slicer understands. Every selection preserves original bytes,
source identity, and line numbers.

`CodeSource::fromFile()` identifies a language from the filename. For an
in-memory source, pass the language explicitly, such as
`CodeSource::fromString($code, 'css')`. A missing language still permits line
and literal-text selection.

## Text and lines

[Text](text.md) covers line ranges and literal line boundaries for HTML, SVG, YAML, Markdown, environment files, and unknown source types.

HTML, SVG, YAML, Markdown, environment files, and unknown source types remain
ordinary text. Select an inclusive range of original lines with `lines()`, or
chain `after()` and `before()` around known literal boundaries. These selectors
do not balance nested tags, infer YAML indentation, or understand Markdown
sections.

```php
use Alto\Code\Slicer\CodeSource;

$source = CodeSource::fromString("Customer: Ada\nTotal: 42 EUR\nStatus: paid");
echo $source->slice()
    ->after('Total: ')
    ->before(' EUR')
    ->content();
```

Both delimiters are excluded. Each call searches for the first case-sensitive
match inside the current slice and returns a new narrower slice. The original
source and earlier slices remain unchanged. The output is `42`, from original
source line 2.

A missing literal raises `SourceTextNotFound`. Check spelling, whitespace,
line endings, and earlier selections before retrying. Empty search text raises
`InvalidArgumentException`. Invalid or expanding line ranges raise
`InvalidSourceRange`.

## Structural selectors

Use a structural selector when an excerpt should follow a named declaration.
It includes the matching closing boundary and preserves indentation.

| Source | Identifiers | Selectors | Guide |
| --- | --- | --- | --- |
| PHP | `php` | Classes and methods | [PHP](php.md) |
| JavaScript | `javascript`, `js` | Functions, classes, and methods | [JavaScript](js.md) |
| TypeScript | `typescript`, `ts` | Typed functions, classes, and methods | [TypeScript](ts.md) |
| CSS | `css` | Rules and at-rules | [CSS](css.md) |
| Twig | `twig` | Blocks and macros | [Twig](twig.md) |

Start with the complete source, then narrow to a containing class, rule,
at-rule, or block when a name is repeated.

## Selection behavior

Structural selectors return the first matching declaration fully contained in
the current slice. A missing symbol raises `SourceSymbolNotFound`; an unknown
or unsupported structural language raises `UnsupportedSourceLanguage`. Code
Slicer does not silently return the whole source.

These selectors locate supported source structures. They are not compilers,
formatters, or type checkers, and malformed input may not expose the expected
declaration. Read [Selectors](../selectors.md) for shared range, chaining, and
failure rules.
