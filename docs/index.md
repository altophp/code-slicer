# Alto Code Slicer

Alto Code Slicer extracts immutable ranges from source code while preserving
the original bytes, language, source name, and line numbers. It selects by
lines, literal boundaries, or supported language structures and leaves
rendering to the consuming application.

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromString("first\nsecond")->lines(2, 2);
echo $slice->content();
```

The result is `second`, still associated with original source line 2.

## Documentation

- [Installation](installation.md): install the package and verify a first selection.
- [Getting started](getting-started.md): extract a complete PHP method with its original line number.
- [Selectors](selectors.md): create sources, chain selections, inspect ranges, and handle failures.
- [Languages](languages/index.md): choose text or structural selectors for each supported source.

## Boundaries

A `CodeSource` owns the complete input. A `CodeSlice` identifies a half-open
byte range within that source, and every selector returns a new narrower slice.
Code Slicer does not highlight, render, rewrite, compile, or execute the
selected source.
