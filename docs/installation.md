# Installation

Code Slicer requires PHP 8.4 or later and the Tokenizer extension. Composer
installs its `alto/language` dependency automatically.

## Install

Run this command in your project directory:

```sh
composer require alto/code-slicer
```

## Verify the installation

Create `check.php` beside the `vendor` directory:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromString("first\nsecond")->lines(2, 2);
echo $slice->content(), "\n";
```

Run `php check.php`. The result should be:

```text
second
```

Framework applications usually load Composer's autoloader already. Standalone
scripts need the `require` statement shown above.

Continue with [Getting started](getting-started.md) to extract a PHP method.
