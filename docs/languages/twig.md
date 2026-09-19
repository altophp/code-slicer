# Twig selectors

Select a named block with `block()` or a macro with `macro()`. Code Slicer finds the matching
closing tag and returns the complete declaration, including multiline content.

The PHP selections below assume [installation](../installation.md) is complete
and Composer's autoloader is loaded. Save each input under the filename shown before running its PHP selection.
The examples pass `twig` explicitly so the template language is unambiguous.

## Select a multiline block

**Source: `checkout.html.twig`**

```twig
{% extends 'base.html.twig' %}

{% block title %}
    {% if cart.items is not empty %}
        Your order ({{ cart.items|length }} items)
    {% else %}
        Your cart is empty
    {% endif %}
{% endblock title %}

{% block body %}
    <section class="checkout">
        {% block summary %}
            <strong>{{ cart.total|number_format(2) }} EUR</strong>
        {% endblock %}
        <p>Review your order before paying.</p>
    </section>
{% endblock body %}

{% macro price(amount) %}
    <span>{{ amount|number_format(2) }} EUR</span>
{% endmacro %}
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.html.twig', 'twig')->slice()->block('title');
echo $slice->content();
```

**Result**

```twig
{% block title %}
    {% if cart.items is not empty %}
        Your order ({{ cart.items|length }} items)
    {% else %}
        Your cart is empty
    {% endif %}
{% endblock title %}
```

Both `{% endblock %}` and `{% endblock title %}` close the block. Its range starts on source
line 3 and ends on line 9. The caller only supplies `title`.

## Keep nested blocks inside their parent

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.html.twig', 'twig')->slice()->block('body');
echo $slice->content();
```

**Result**

```twig
{% block body %}
    <section class="checkout">
        {% block summary %}
            <strong>{{ cart.total|number_format(2) }} EUR</strong>
        {% endblock %}
        <p>Review your order before paying.</p>
    </section>
{% endblock body %}
```

The inner `endblock` closes `summary`. The parent selection continues through `endblock body`.
To select just the nested block, narrow again:

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.html.twig', 'twig')->slice()->block('body')->block('summary');
echo $slice->content();
```

**Result**

```twig
        {% block summary %}
            <strong>{{ cart.total|number_format(2) }} EUR</strong>
        {% endblock %}
```

## Select a macro

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('checkout.html.twig', 'twig')->slice()->macro('price');
echo $slice->content();
```

**Result**

```twig
{% macro price(amount) %}
    <span>{{ amount|number_format(2) }} EUR</span>
{% endmacro %}
```

## Shorthand blocks

A block with its value on the opening tag has no separate `endblock`.

**Source: `title.html.twig`**

```twig
{% extends 'base.html.twig' %}

{% block title 'Your order' %}

{% block body %}Review your order.{% endblock %}
```

**Selection**

```php
use Alto\Code\Slicer\CodeSource;

$slice = CodeSource::fromFile('title.html.twig', 'twig')->slice()->block('title');
echo $slice->content();
```

**Result**

```twig
{% block title 'Your order' %}
```

Twig comments attached immediately above a block or macro are included. Closing tags inside
comments or quoted expressions do not close the surrounding block.

`block('title')` addresses Twig `{% block title %}` syntax. Symfony UX `<twig:block name="title">`
and component tags currently use the [text and line selectors](index.md#text-and-lines), which do not
match nested elements structurally.

Selection uses the first matching name fully inside the current slice. Names
are case-sensitive. A missing declaration raises `SourceSymbolNotFound`;
check its name and the containing class or block before retrying.

See [selection rules](../selectors.md) or return to [Languages](index.md).
