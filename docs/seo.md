# Review schema and SEO addons

Search engines can show an entry's star rating in their results when the page has review schema: JSON-LD saying what the entry is, its average rating and some of its reviews. Reviews makes it from the approved reviews.

On its own, `{{ reviews:schema }}` is all you need (see [Templates](templates.md#review-schema)). This page is for sites where an SEO addon prints schema for the same entries, as most do.

## Why it matters

If your SEO addon prints a `Product` for a page and Reviews prints another, search engines see **two separate products**: one with the price and no rating, one with a rating and nothing else. That can stop the stars showing, and show as warnings in Search Console. The fix is to make them one thing, in one of two ways.

## Two ways to join them

### Share an `@id`

Schema with the same `@id` is merged into one thing. If your SEO addon gives the entry's schema an `@id`, give Reviews' the same one:

```antlers
{{ reviews:schema id="{{ permalink }}#product" }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews:schema :id="$permalink.'#product'" />
```

</details>

Look at the page's source to see the `@id` your SEO addon uses (or whether it sets one at all).

### Put the rating in the SEO addon's schema

Better, where the SEO addon lets you write your own schema: add the rating and reviews to it, so there's one block. `{{ reviews:schema_data }}` is just that part, as JSON, without a script tag:

| | Output |
| --- | --- |
| `{{ reviews:schema_data }}` | `{"aggregateRating": {…}, "review": […]}` |
| `{{ reviews:schema_data format="properties" }}` | `"aggregateRating": {…}, "review": […],` |
| `{{ reviews:schema_data only="aggregate" }}` | `{"@type": "AggregateRating", …}` |

`format="properties"` is the one to use inside JSON you write: the properties without their braces, each ending in a comma, or nothing at all before an entry has an approved review. Put it straight after an opening `{` and the JSON is valid either way. Add `reviews="0"` to leave the reviews out and keep only the average.

Then leave `{{ reviews:schema }}` off those pages, so the rating isn't there twice.

In PHP, for an addon that takes schema from code, `app(\NineteenNinetyFour\Reviews\Schema::class)->parts($entry)` returns the same as an array (or null without reviews).

## SEO Pro

[SEO Pro](https://statamic.com/addons/statamic/seo-pro) has a **Schema** field in its JSON-LD section, in each collection's section defaults and on each entry, which it reads as Antlers with the page's data. Reviews' tag goes straight into it. (These steps were checked with SEO Pro 7.15.)

1. Allow the tag in Antlers written in fields, in `config/statamic/antlers.php` (publish Statamic's config first if you haven't):

    ```php
    'allowedContentTags' => [
        '@default',
        'reviews:schema_data*',
    ],
    ```

    The `*` is needed even for this one tag.

2. In **Tools → SEO Pro → Section Defaults**, open the collection that takes reviews (products, say), and in **JSON-LD → Schema** write:

    ```json
    {
        {{ reviews:schema_data format="properties" }}
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "{{ title }}",
        "url": "{{ permalink }}"
    }
    ```

    Use the type that suits the collection (`Book`, `Course`, `Recipe`…), and add whatever else you'd like in it: an image, a brand, offers.

3. Remove `{{ reviews:schema }}` from the collection's template, if it's there.

Open an entry with approved reviews, view its source, and check SEO Pro's JSON-LD has the `aggregateRating`; an entry without reviews has the same JSON-LD without it. Google's [Rich Results Test](https://search.google.com/test/rich-results) shows whether the stars can appear.

An entry's own Schema field, if filled in, replaces the section default for that entry: add the tag there too.

## Other SEO addons

The same two ways work with any SEO addon: share an `@id`, or put `{{ reviews:schema_data format="properties" }}` wherever it lets you write your own JSON-LD in Antlers. If it reads that field as Antlers in content, allow the tag as in step 1 for SEO Pro.

Using an SEO addon that isn't here yet? Tell us at [hello@1994.co.uk](mailto:hello@1994.co.uk), and we'll add steps for it.
