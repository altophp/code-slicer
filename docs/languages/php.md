# PHP selectors

Select classes and methods by name. The range includes the declaration, its attached docblock
and attributes, and its complete body. Indentation remains exactly as written in the file.

The PHP selections below assume [installation](../installation.md) is complete
and Composer's autoloader is loaded. Save the input as `Checkout.php` in the working directory before running the
PHP selections. The `.php` filename identifies the source language.

## Select a method

**Source: `Checkout.php`**

```php
<?php

final class Checkout
{
    /**
     * Apply VAT to the order subtotal.
     */
    public function total(array $prices): float
    {
        return array_sum($prices) * 1.2;
    }

    public function reset(): void
    {
        $this->prices = [];
    }

    private array $prices = [];
}
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('Checkout.php')->slice()->class('Checkout')->method('total');
echo $slice->content();
```

**Result**

```php partial
    /**
     * Apply VAT to the order subtotal.
     */
    public function total(array $prices): float
    {
        return array_sum($prices) * 1.2;
    }
```

`class('Checkout')` limits the search when multiple classes have a method named `total`.
The returned range starts on source line 5 and ends on line 11.

## Exclude this method's docblock

For the same file, select the method and skip its docblock's closing line:

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('Checkout.php')->slice()
    ->method('total')
    ->after("*/\n");
echo $slice->content();
```

**Result**

```php partial
    public function total(array $prices): float
    {
        return array_sum($prices) * 1.2;
    }
```

`after()` searches literal text, including the LF line ending used in this input. This works for the docblock shown here; it is not a
universal docblock-removal option, and missing text throws `SourceTextNotFound`.

## Continue after a method

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('Checkout.php')->slice()->class('Checkout')
    ->afterMethod('total')
    ->method('reset');
echo $slice->content();
```

**Result**

```php partial
    public function reset(): void
    {
        $this->prices = [];
    }
```

`beforeMethod('total')` keeps the range before that declaration. `beforeNextMethod()`
stops at the first method in the current slice; `beforeNextClass()` does the same for a class.
These boundary selectors exclude the declaration they find.

PHP structural selection supports classes and methods. The `function()` selector is currently
provided for JavaScript and TypeScript only.

Selection uses the first matching name fully inside the current slice. Names
are case-sensitive. A missing declaration raises `SourceSymbolNotFound`;
check its name and the containing class or block before retrying.

See [selection rules](../selectors.md) or return to [Languages](index.md).
