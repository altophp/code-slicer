# Selectors

Use `CodeSource` to hold complete input and `CodeSlice` to select an immutable
range within it. Both belong to `Alto\Code\Slicer`. `Language` below means
`Alto\Language\Language`; exceptions belong to `Alto\Code\Slicer\Exception`.

All offsets count bytes, not Unicode characters. Line numbers are one-based
and refer to the complete source, even after several selections. No selector
writes a file or executes the selected code.

## Create a source

The factories return a `CodeSource` containing the complete input:

| Signature | Parameters and behavior | Exceptions |
| --- | --- | --- |
| `CodeSource::fromFile(string $path, Language\|string\|null $language = null): CodeSource` | Read `$path` immediately. With `null`, detect the language from its filename; detection may return `null`. An explicit language is a registered slug or `Language` object. | `SourceFileNotReadable` if the file cannot be read; `UnknownSourceLanguage` for an unknown explicit slug. |
| `CodeSource::fromString(string $code, Language\|string\|null $language = null, ?string $name = null): CodeSource` | Keep `$code` verbatim. `$name` is an optional label, not a file to read. With `null`, no language is inferred from the content or name. | `UnknownSourceLanguage` for an unknown explicit slug. |

A missing language still permits line and text selection. Structural selection
requires a supported language. Explicit PHP input accepts code with or without
an opening `<?php` tag.

Inspect the complete source through these methods:

| Signature | Result |
| --- | --- |
| `content(): string` | Original bytes, including original line endings. |
| `language(): ?Language` | Resolved language, or `null`. |
| `name(): ?string` | File path supplied to `fromFile()`, or label supplied to `fromString()`. |
| `lineCount(): int` | Number of source lines. Empty input has one source line; a final line ending does not add an extra line. |
| `slice(): CodeSlice` | A slice covering all bytes, from `0` through `strlen(content())` exclusively. |
| `lines(int $start, int $end): CodeSlice` | Inclusive source line range; excludes the last selected line's ending. Invalid or out-of-bounds lines throw `InvalidSourceRange`. |

## Select lines and text

Every method below belongs to `CodeSlice` and returns a new `CodeSlice`.
The original source and slice remain unchanged. Searches use the first,
case-sensitive occurrence fully contained in the current slice.

| Signature | Result and boundaries |
| --- | --- |
| `lines(int $start, int $end): CodeSlice` | Select inclusive original source lines. Their complete range must fit inside the current slice. |
| `after(string $text): CodeSlice` | Keep bytes after the matched text; exclude the match. |
| `before(string $text): CodeSlice` | Keep bytes before the matched text; exclude the match. |
| `fromLine(string $text): CodeSlice` | Start at the first line containing the text; include that line. |
| `throughLine(string $text): CodeSlice` | End at the first line containing the text; include that line. |
| `afterLine(string $text): CodeSlice` | Start after the first line containing the text. |
| `beforeLine(string $text): CodeSlice` | End before the first line containing the text. |

`lines()` throws `InvalidSourceRange` for invalid line numbers or a range
outside the current slice. Text selectors throw `SourceTextNotFound` when no
complete match is found. Empty search text throws `InvalidArgumentException`.

See [Text](languages/text.md) for complete HTML, SVG, YAML, Markdown, and environment-file examples.

## Select declarations

Structural selectors return the first matching declaration fully contained in
the current slice. Narrow to a containing class, at-rule, or block to resolve
repeated names. The declaration includes its closing boundary, original
indentation, and supported attached comments or attributes.

| Signature | Supported source | Selection |
| --- | --- | --- |
| `class(string $name): CodeSlice` | PHP, JavaScript, TypeScript | Named class. |
| `method(string $name): CodeSlice` | PHP, JavaScript, TypeScript | Named method. |
| `function(string $name): CodeSlice` | JavaScript, TypeScript | Named function outside a class. |
| `rule(string $selector): CodeSlice` | CSS | Complete rule matching the whole selector text, including a selector list. |
| `atRule(string $name, ?string $prelude = null): CodeSlice` | CSS | At-rule by name; optionally match its prelude, such as a media condition. |
| `block(string $name): CodeSlice` | Twig | Named block, including nested content or shorthand block syntax. |
| `macro(string $name): CodeSlice` | Twig | Named macro and its closing tag. |

Class, method, function, block, and macro names are case-sensitive. CSS rule
matching normalizes whitespace, but does not interpret selector equivalence.
At-rule names are case-insensitive and may be supplied without `@`; preludes
use normalized whitespace and otherwise exact matching.

These boundary selectors are available for PHP, JavaScript, and TypeScript:

| Signature | Selection |
| --- | --- |
| `beforeNextClass(): CodeSlice` | Current range before its first complete class declaration. |
| `beforeNextMethod(): CodeSlice` | Current range before its first complete method declaration. |
| `beforeMethod(string $name): CodeSlice` | Current range before the named method declaration. |
| `afterMethod(string $name): CodeSlice` | Current range after the named method declaration. |

Boundary selectors exclude the declaration they find and may return an empty
slice. All structural selectors throw `SourceSymbolNotFound` when no matching
symbol is available, or `UnsupportedSourceLanguage` when the language is
unknown or lacks that selector capability.

Language-specific limits and examples belong to [Languages](languages/index.md).
These selectors locate supported source structures; they are not compilers
or a guarantee that arbitrary malformed source can be interpreted.

## Inspect a slice

These accessors do not modify the selected range:

| Signature | Result |
| --- | --- |
| `content(): string` | Exact selected bytes; an empty slice returns `''`. |
| `source(): CodeSource` | Complete original source, not just the selected text. |
| `range(): SourceRange` | Half-open byte range into that source. |
| `language(): ?Language` | Source language. |
| `sourceName(): ?string` | Original source path or label. |
| `startLine(): int` | Source line containing the first selected byte, or the empty slice's position. |
| `endLine(): int` | Source line containing the last selected byte; for an empty slice, the same position as `startLine()`. |
| `lineCount(): int` | Number of spanned source lines, or `0` for an empty slice. |

A downstream highlighter can analyse `source()->content()` and then project
its result onto `range()`, preserving context around the excerpt.

## Source ranges

`SourceRange` is a readonly value with constructor
`__construct(int $start, int $end)`.

The public integer properties `start` and `end` are respectively inclusive
and exclusive. `start` must be non-negative and `end` must be at least `start`;
otherwise construction throws `InvalidSourceRange`. Equality represents an
empty range. A standalone range does not validate against a source length.

`contains(SourceRange $range): bool` returns whether both boundaries fit
inside the receiver, including an empty range at either boundary. Obtain
source-validated ranges from slices. Direct `CodeSlice` construction and
methods marked `@internal` are not public entry points.

## Handle failures

Use the exception category to decide whether to fix the request or select a
fallback:

| Exception | Base class | Caller action |
| --- | --- | --- |
| `SourceFileNotReadable` | `RuntimeException` | Check the input path and read permissions. |
| `UnknownSourceLanguage` | `InvalidArgumentException` | Correct the explicit language slug. |
| `UnsupportedSourceLanguage` | `LogicException` | Choose a supported structural selector or use text selection. |
| `InvalidSourceRange` | `InvalidArgumentException` | Check line numbers and current slice boundaries. |
| `SourceTextNotFound` | `RuntimeException` | Check the literal text and current range. |
| `SourceSymbolNotFound` | `RuntimeException` | Check the name, language, and containing scope. |
| `InvalidArgumentException` | PHP built-in | Supply non-empty search text. |

Return to [Getting started](getting-started.md) for a complete first example,
or [Languages](languages/index.md) to choose a structural selector.
