# JavaScript

Select named functions, classes, and methods while preserving their original source.
After [installation](../installation.md), load Composer's autoloader and save the input under the filename shown.

## JavaScript functions

**Source: `checkout.js`**

```javascript
export function formatPrice(amount) {
    return `${amount.toFixed(2)} EUR`;
}

export class Checkout {
    total(prices) {
        return prices.reduce((sum, price) => sum + price, 0);
    }

    reset() {
        this.prices = [];
    }
}
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.js')->slice()->function('formatPrice');
echo $slice->content();
```

**Result**

```javascript
export function formatPrice(amount) {
    return `${amount.toFixed(2)} EUR`;
}
```

Attached JSDoc is included when present. Braces inside strings and template literals do not
close the function early.

## JavaScript methods

Using the same file, narrow to the class before selecting its method:

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.js')->slice()->class('Checkout')->method('total');
echo $slice->content();
```

**Result**

```javascript
    total(prices) {
        return prices.reduce((sum, price) => sum + price, 0);
    }
```

`function()` selects functions outside classes. Use `method()` for class members, including
async methods, getters, generators, and private methods. A private method written `#reset()`
is selected with `method('reset')`.

`beforeMethod()`, `afterMethod()`, `beforeNextMethod()`, and `beforeNextClass()` also work
with JavaScript and TypeScript. See the [PHP boundary example](php.md#continue-after-a-method).

Selection uses the first matching name fully inside the current slice. Names
are case-sensitive. A missing declaration raises `SourceSymbolNotFound`;
check its name and the containing class or block before retrying.

See [selection rules](../selectors.md) or return to [Languages](index.md).
