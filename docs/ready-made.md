# Ready-made reviews

Don't want to write the markup and styles yourself? Reviews comes with a finished reviews section: the average and star breakdown, the reviews (with sorting, pages and "helpful" buttons), and the form. Add two tags and it's done:

```antlers
{{# In your layout's <head> #}}
{{ reviews:styles }}

{{# Wherever the reviews go, in the entry's template #}}
{{ reviews:display }}
```

<details>
<summary>Blade</summary>

```blade
{{-- In your layout's <head> --}}
<s:reviews:styles />

{{-- Wherever the reviews go, in the entry's template --}}
<s:reviews:display />
```

</details>

It works without JavaScript and on statically cached pages (the form is in `{{ nocache }}` already), is usable by keyboard and screen readers, and fits phones.

The styles are built to match your site rather than bring a look of their own: they take your site's font, size and text colour, work out the borders, muted text and empty stars from that text colour (so dark sites work too), and leave anything you've styled yourself alone. If that's not quite right, every colour, size and gap is a CSS variable you can set.

## The tags

| Tag | |
| --- | --- |
| `{{ reviews:display }}` | The whole section: heading, average and breakdown beside the reviews, then the form. |
| `{{ reviews:display_summary }}` | The average, how many of each star, and each criterion's average. |
| `{{ reviews:display_list }}` | The reviews, with a choice of order, pages and "helpful" buttons. |
| `{{ reviews:display_form }}` | The form. |
| `{{ reviews:styles }}` | A `<link>` to the default stylesheet. |
| `{{ reviews:styles_url }}` | Just the stylesheet's address. |

Use the parts separately to lay them out yourself: the summary in a sidebar, say. Each is wrapped in its own `<div class="reviews-ui">`, so the styles apply.

Parameters, on any of the `display` tags:

| Parameter | |
| --- | --- |
| `entry` | The entry, if not the one the template is showing. |
| `heading` | The section's heading ("Reviews"). `display` only. |
| `sort` | The order before a visitor chooses one: `helpful` (the default), `newest`, `highest` or `lowest`. |
| `per_page` | Reviews per page (`per_page` in the config: 10). |
| `form` | `false` to leave the form out. `display` only. |

Visitors choose the order with the menu above the reviews, and see one rating's reviews from the links in the breakdown; both go in the address (`?sort=newest`, `?rating=5`).

## The stylesheet

`{{ reviews:styles }}` adds:

```html
<link rel="stylesheet" href="https://example.com/!/reviews/reviews.css?v=…">
```

The address is the addon's own, and the `?v=` changes when the file does, so browsers cache it for a year and pick up a new version after you update Reviews. To load it some other way (in a CSS build, or from a CDN), `{{ reviews:styles_url }}` is the address on its own, and the file itself is `vendor/1994/reviews-statamic/resources/css/reviews.css`.

## Changing the look

There are three ways, from least to most work.

### 1. Set the variables

Everything is drawn from `--reviews-*` variables. Set any of them in your own CSS, on `.reviews-ui` for the reviews or `:root` for everywhere:

```css
.reviews-ui {
    --reviews-star: #e11d48;
    --reviews-radius: 0;
    --reviews-button-bg: #111827;
    --reviews-button-text: #fff;
    --reviews-heading-font: "Playfair Display", serif;
}
```

| Variable | Default | |
| --- | --- | --- |
| `--reviews-font` | `inherit` | The font. Your site's, unless you set one. |
| `--reviews-heading-font` | `inherit` | Headings and the big average. |
| `--reviews-font-size` | `inherit` | |
| `--reviews-small` | `0.875em` | Smaller text: dates, counts, helpful buttons. |
| `--reviews-heading-size` | `1.5em` | The section and form headings. |
| `--reviews-line-height` | `inherit` | |
| `--reviews-text` | `currentColor` | The text colour. Your site's. |
| `--reviews-muted` | the text colour at 65% | Dates, counts and notes. |
| `--reviews-border` | the text colour at 18% | Lines between reviews, around the form and fields. |
| `--reviews-surface` | the text colour at 4% | Behind the form and replies. |
| `--reviews-field` | `transparent` | Behind form fields. |
| `--reviews-button-bg` | `transparent` | Buttons are outlined in the text colour until you give them a fill. |
| `--reviews-button-text` | `inherit` | |
| `--reviews-button-border` | `currentColor` | |
| `--reviews-star` | `#f5a524` | Filled stars and the breakdown's bars. |
| `--reviews-star-empty` | the text colour at 20% | Empty stars and bars. |
| `--reviews-verified` | `#2f7d4a` | "Verified purchase". |
| `--reviews-error` | `#c0392b` | Form errors. |
| `--reviews-focus` | `Highlight` | The keyboard focus ring: the system's highlight colour. |
| `--reviews-radius` | `0.5rem` | Corners. |
| `--reviews-gap` | `1.5rem` | Space between reviews, and inside the form. |
| `--reviews-star-size` | `1.1em` | Stars in the summary and reviews. |
| `--reviews-star-input-size` | `2rem` | The stars to click in the form. |
| `--reviews-max-width` | `none` | The section's widest. |

### 2. Write your own CSS on top

All the default styles are in a cascade layer called `reviews`, with selectors that count for nothing, so **any CSS of yours wins**, without `!important` or long selectors. Every element has a class starting `reviews-ui__` to aim at:

```css
.reviews-ui__review { padding-block: 2rem; }
.reviews-ui__verified { font-weight: 700; }
.reviews-ui__form { box-shadow: 0 10px 30px rgb(0 0 0 / 0.08); }
```

The classes are `reviews-ui__summary`, `__average`, `__average-number`, `__stars`, `__star` (`--full`, `--half`), `__bars`, `__bar-row`, `__bar`, `__bar-fill`, `__criteria`, `__toolbar`, `__select`, `__list`, `__review`, `__review-head`, `__review-title`, `__review-criteria`, `__photos`, `__by`, `__verified`, `__reply`, `__reply-label`, `__actions`, `__pages`, `__empty`, `__message` (`--error`), `__form`, `__heading`, `__field`, `__label`, `__row`, `__input`, `__rating`, `__rating-stars`, `__button` (`--quiet`), `__link-button`, `__muted` and `__columns`. [Publish the views](#4-change-the-markup) to see where each one is.

**With Tailwind**, which keeps its own styles in layers too, say where the reviews' layer goes, so Tailwind's reset doesn't undo it and your utility classes still beat it. Put this line before `@import "tailwindcss";`:

```css
@layer theme, base, reviews, components, utilities;
```

### 3. Edit the stylesheet itself

To start from the default styles and change them however you like, copy them into your site:

```bash
php artisan vendor:publish --tag=reviews-css
```

That puts them in `resources/css/reviews.css`. Import it into your own CSS build (and drop `{{ reviews:styles }}`), or move it to `public/` and link to it yourself. It's yours from then on: updates to Reviews don't change it, so check the [changelog](../CHANGELOG.md) for new classes now and then.

Or leave out `{{ reviews:styles }}` altogether and style the classes from scratch.

### 4. Change the markup

The views are Antlers templates. Publish them to edit:

```bash
php artisan vendor:publish --tag=reviews-views
```

They're in `resources/views/vendor/reviews/display/`: `section`, `summary`, `list` and `form`. Each uses only the tags in [Tags](tags.md), so anything in [Templates](templates.md) can go in them. Delete a file to go back to the addon's own.

When you want full control, build your own from [Templates](templates.md) instead: the ready-made views are a starting point, not a requirement.

## Wording and translation

The views' words are in the addon's translations (the `display` keys). Publish them to change the wording, or to translate them:

```bash
php artisan vendor:publish --tag=reviews-translations
```
