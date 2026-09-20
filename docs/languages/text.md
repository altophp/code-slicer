# Text

Use original line numbers for a fixed range, or literal source text for known
boundaries. Prefer a structural selector when a supported declaration has a
stable name. HTML, SVG, YAML, Markdown,
and environment files can all be sliced this way; no comment markers need to be added.

| Selector | Boundary behavior |
| --- | --- |
| `fromLine($text)` | Start at the first matching line, including it. |
| `throughLine($text)` | End at the first matching line, including its content. |
| `afterLine($text)` | Start after the first matching line. |
| `beforeLine($text)` | End before the first matching line. |

All four search case-sensitive substrings inside the current slice. They do not match nested
HTML elements, YAML mappings, or Markdown sections structurally. Use `method()`, `rule()`, or
`block()` when the source language provides them.

The PHP selections below assume [installation](../installation.md) is complete
and Composer's autoloader is loaded. Save each input under the filename shown before running its PHP selection.
Choose these selectors when you know the exact text or original line numbers.

## Select exact source lines

This standalone example selects one original source line without relying on
language detection:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\Code\Slicer\CodeSource;

$source = CodeSource::fromString("first\nsecond\nthird");
echo $source->lines(2, 2)->content(), "\n";
```

Save it beside `vendor`, then run it with PHP. It prints:

```text
second
```

Use `after()` and `before()` for boundaries within a line; use the line helpers
below when the matching lines themselves should be included or excluded.

## HTML: include a complete form

**Source: `checkout.html`**

```html
<main>
    <form action="/orders" method="post">
        <input name="email" type="email" required>
        <button type="submit">Confirm</button>
    </form>
    <p>Payment is collected on confirmation.</p>
</main>
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.html')->slice()
    ->fromLine('<form ')
    ->throughLine('</form>');
echo $slice->content();
```

**Result**

```html
    <form action="/orders" method="post">
        <input name="email" type="email" required>
        <button type="submit">Confirm</button>
    </form>
```

To keep only its contents, exclude the matching tag lines:

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.html')->slice()
    ->afterLine('<form ')
    ->beforeLine('</form>');
echo $slice->content();
```

**Result**

```html
        <input name="email" type="email" required>
        <button type="submit">Confirm</button>
```

## SVG: select a group

**Source: `confirmed.svg`**

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <title>Order confirmed</title>
    <g id="badge" fill="none" stroke="currentColor">
        <circle cx="12" cy="12" r="10"/>
        <path d="m7 12 3 3 7-7"/>
    </g>
</svg>
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('confirmed.svg')->slice()
    ->fromLine('<g id="badge"')
    ->throughLine('</g>');
echo $slice->content();
```

**Result**

```svg
    <g id="badge" fill="none" stroke="currentColor">
        <circle cx="12" cy="12" r="10"/>
        <path d="m7 12 3 3 7-7"/>
    </g>
```

This group has no nested `g`. With nested groups, `throughLine('</g>')` would stop at the first
closing line, so it cannot stand in for an element selector.

## YAML: select known settings

**Source: `checkout.yaml`**

```yaml
app:
  name: Checkout
  currency: EUR
services:
  App\Checkout:
    arguments:
      $taxRate: 1.2
      $currency: '%app.currency%'
receipt:
  subject: Your order is confirmed
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.yaml')->slice()
    ->fromLine('services:')
    ->beforeLine('receipt:');
echo $slice->content();
```

**Result**

```yaml
services:
  App\Checkout:
    arguments:
      $taxRate: 1.2
      $currency: '%app.currency%'
```

The keys are text boundaries. This selection does not infer YAML indentation or resolve a key path.

## Markdown: select a section between headings

**Source: `checkout.md`**

```markdown
# Checkout

## Installation

Install the package with Composer.

## Usage

Load your source and select a method.
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.md')->slice()
    ->fromLine('## Installation')
    ->beforeLine('## Usage');
echo $slice->content();
```

**Result**

```markdown
## Installation

Install the package with Composer.

```

The blank line before the next heading is preserved. Both heading texts are supplied explicitly.

## Environment files: select variable assignments

**Source: `.env.example`**

```dotenv
APP_ENV=dev
APP_NAME="Checkout demo"
APP_URL=http://localhost:8000
ASSET_URL=${APP_URL}/assets
MAILER_DSN=null://null
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('.env.example', 'dotenv')->slice()
    ->fromLine('APP_NAME=')
    ->throughLine('ASSET_URL=');
echo $slice->content();
```

**Result**

```dotenv
APP_NAME="Checkout demo"
APP_URL=http://localhost:8000
ASSET_URL=${APP_URL}/assets
```

The source is read as text. Environment variables are not loaded or expanded.

## Exact lines and text within a line

`lines(4, 8)` selects inclusive line numbers from the original file. `after('return ')` and
`before(';')` cut at exact text inside a line and exclude that text. These methods also work
when the source language is unknown.

Every selection retains the original bytes and source line numbers. Empty or multiline search
text is rejected by the line helpers; missing text throws `SourceTextNotFound`.

See the [public API](../selectors.md) or return to the [language guides](index.md).
