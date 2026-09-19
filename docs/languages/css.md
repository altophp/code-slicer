# CSS selectors

Select a complete rule with `rule()`, or an at-rule with `atRule()`. Closing braces are
matched automatically, including nested rule bodies. Use these selectors on a
CSS source when line numbers may change but the rule you want has a stable
selector or at-rule name.

The examples below assume [Code Slicer is installed](../installation.md) and
Composer's autoloader is loaded. Save the input as `checkout.css` in the working
directory before running the PHP selections. The `.css` extension identifies
the language automatically.

## Select a rule

**Source: `checkout.css`**

```css
.checkout {
    display: grid;
    gap: 1rem;
}

@media (width >= 48rem) {
    .checkout {
        grid-template-columns: 2fr 1fr;
    }
}
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.css')->slice()->rule('.checkout');
echo $slice->content();
```

**Result**

```css
.checkout {
    display: grid;
    gap: 1rem;
}
```

The first matching rule in the current slice is selected. Attached CSS comments are included
when present. Selector lists must be supplied in full: `.button, .button--ghost` selects that
list; `.button` alone does not. Whitespace in the selector is normalized for matching.

## Select a rule inside a media query

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.css')->slice()
    ->atRule('media', '(width >= 48rem)')
    ->rule('.checkout');
echo $slice->content();
```

**Result**

```css
    .checkout {
        grid-template-columns: 2fr 1fr;
    }
```

Narrowing to the media query selects the second `.checkout` rule without relying on line numbers.

## Select the whole media query

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.css')->slice()->atRule('media');
echo $slice->content();
```

**Result**

```css
@media (width >= 48rem) {
    .checkout {
        grid-template-columns: 2fr 1fr;
    }
}
```

The at-rule name omits `@`. Its second argument is optional: `atRule('media')` finds the first
media query; supplying `(width >= 48rem)` also matches its condition. `atRule('keyframes', 'pulse')`
selects named keyframes. At-rules without bodies, such as `@import`, end at their semicolon.

A missing rule or at-rule raises `SourceSymbolNotFound`. Check the full selector
list and any containing media query; narrowing the slice may exclude a match.

See [selection rules](../selectors.md) or choose another [language](index.md).
