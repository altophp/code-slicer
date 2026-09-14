# Alto CodeSlicer documentation

Alto CodeSlicer extracts immutable ranges from complete source code while preserving the original
bytes, language, source name, and line numbers.

## Documentation

- [Installation](installation.md) covers requirements and Composer.
- [Getting started](getting-started.md) builds a slice with line, text, and structural selectors.
- [Public API](public-api.md) defines the supported entry points and selection rules.

## Mental model

`CodeSource` owns the complete input. `CodeSlice` is a half-open byte range over that source.
Selectors return narrower slices without modifying either object.

```text
CodeSource
  -> slice()
  -> lines(), after(), before(), or a structural selector
  -> CodeSlice
  -> content(), source(), range(), and original line numbers
```
