# TypeScript

Select named functions, classes, and methods while preserving their original source.
After [installation](../installation.md), load Composer's autoloader and save the input under the filename shown.

## TypeScript methods

**Source: `Checkout.ts`**

```typescript
export class Checkout {
    total(prices: number[]): number {
        return prices.reduce((sum, price) => sum + price, 0);
    }
}

export function parse<T>(raw: string): T {
    return JSON.parse(raw) as T;
}
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('Checkout.ts')->slice()->class('Checkout')->method('total');
echo $slice->content();
```

**Result**

```typescript
    total(prices: number[]): number {
        return prices.reduce((sum, price) => sum + price, 0);
    }
```

## TypeScript functions

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('Checkout.ts')->slice()->function('parse');
echo $slice->content();
```

**Result**

```typescript
export function parse<T>(raw: string): T {
    return JSON.parse(raw) as T;
}
```

The selected code retains generics, parameter types, and return types. Abstract methods,
interface members, and overload signatures without bodies are not selectable methods.
This is source selection; Code Slicer does not type-check or execute TypeScript.

`beforeMethod()`, `afterMethod()`, `beforeNextMethod()`, and `beforeNextClass()` also work
with JavaScript and TypeScript. See the [PHP boundary example](php.md#continue-after-a-method).

Selection uses the first matching name fully inside the current slice. Names
are case-sensitive. A missing declaration raises `SourceSymbolNotFound`;
check its name and the containing class or block before retrying.

See [selection rules](../selectors.md) or return to [Languages](index.md).
