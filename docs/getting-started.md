# Getting started

Extract one method from a complete PHP source and print its original line
number. This gives you an excerpt that remains tied to its source when you
publish or highlight it.

## Extract a PHP method

### Prepare the script

After [installation](installation.md), create `extract.php` beside the
`vendor` directory. This example includes its input, so no separate source
file is needed:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Slicer\CodeSource;

$code = <<<'PHP'
<?php
final class Checkout
{
    public function complete(): string
    {
        return 'Order complete';
    }
}
PHP;

$slice = CodeSource::fromString($code, 'php')
    ->slice()
    ->class('Checkout')
    ->method('complete');

printf("Starts at line %d\n", $slice->startLine());
echo $slice->content(), "\n";
```

### Run and check the result

Run the script from the project directory:

```sh
php extract.php
```

The output is:

```text
Starts at line 4
    public function complete(): string
    {
        return 'Order complete';
    }
```

The class selector narrows the search to `Checkout`; the method selector
returns `complete` with its closing brace. Indentation and line numbers still
refer to the complete input. Each selection returns a new immutable slice.

Continue with [PHP selectors](languages/php.md) to select methods from files and include
their attached documentation.
