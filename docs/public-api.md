# Public API

## `CodeSource`

Create a source with `CodeSource::fromFile()` or `CodeSource::fromString()`. Read its complete
content, language, name, and line count with `content()`, `language()`, `name()`, and `lineCount()`.
Pass a language slug such as `php`, or a `Language` object, to declare the language explicitly.

Create selections with `slice()` or `lines()`.

An explicitly declared PHP source can contain a complete tagged document or PHP code without an
opening tag.

## `CodeSlice`

Every selector returns a new immutable slice.

Text and line selectors:

- `lines()`
- `after()`
- `before()`

PHP selectors:

- `class()`
- `method()`
- `beforeNextClass()`
- `beforeNextMethod()`
- `beforeMethod()`
- `afterMethod()`

JavaScript and TypeScript also provide `function()`. CSS provides `rule()` and `atRule()`. Twig
provides `block()` and `macro()`.

Read the result with `content()`, `language()`, `sourceName()`, `startLine()`, `endLine()`, and
`lineCount()`. Use `source()` and `range()` when a downstream consumer needs the complete parsing
context and selected byte offsets.

## Selection rules

- Line numbers are one-based and inclusive.
- Byte ranges are zero-based, start-inclusive, and end-exclusive.
- Searches stay inside the current slice.
- Structural selectors use the first matching symbol in the current slice.
- Missing text, symbols, capabilities, and invalid ranges throw dedicated exceptions.
