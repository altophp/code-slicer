# Installation

## Requirements

Alto CodeSlicer requires PHP 8.4 or later, the tokenizer extension, Composer, and
`alto/language`.

## Install with Composer

```bash
composer require alto/code-slicer
```

Framework applications normally load Composer's autoloader. A standalone script can load it
directly:

```php
require __DIR__.'/vendor/autoload.php';
```

## Confirm the installation

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromString("first\nsecond")
    ->lines(2, 2);

echo $slice->content();
```

The script prints `second`.
