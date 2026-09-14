# Getting started

Start from the complete source so every slice can retain its original context and line numbers.

## Select a PHP method

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('src/Checkout.php')
    ->slice()
    ->class('Checkout')
    ->method('complete');

echo $slice->content();
echo $slice->startLine();
```

The class selector narrows the search scope. The method selector then returns the declaration,
including attached documentation and attributes.

For an in-memory PHP fragment, pass the language explicitly. Structural selectors accept PHP with
or without an opening tag:

```php
$slice = CodeSource::fromString(
    'final class Checkout {}',
    'php',
)->slice()->class('Checkout');
```

## Select exact lines

Line numbers are one-based, inclusive, and refer to the complete source:

```php
$slice = $source->lines(12, 18);
```

Line endings remain byte-for-byte identical to the source.

## Select between markers

Text selectors search only within the current slice:

```php
$slice = $source->slice()
    ->after("// example:start\n")
    ->before("\n// example:end");
```

Each selector returns a new immutable `CodeSlice`.

## Use the complete context downstream

```php
$completeSource = $slice->source()->content();
$range = $slice->range();

$range->start; // inclusive byte offset
$range->end;   // exclusive byte offset
```

A parser or highlighter can analyze the complete source, then project its result onto that range.
