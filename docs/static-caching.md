# Static caching

Reviews works on statically cached pages. What it needs from you:

**Wrap the form in `{{ nocache }}`.** The form itself works on a cached page (Statamic keeps its CSRF token fresh, and the time trap allows for it), but what's in it is personal: the thank-you message, errors, what they typed, a signed-in reviewer's own review. Without `{{ nocache }}`, the first visitor's version would be cached for everyone.

```antlers
{{ nocache }}
    {{ reviews:form }}
        …
    {{ /reviews:form }}
{{ /nocache }}
```

<details>
<summary>Blade</summary>

```blade
@nocache('partials.review-form')
```

with the form in `resources/views/partials/review-form.blade.php`.

</details>

The same goes for `{{ reviews:success }}`, and for `is_mine` in a list.

**Everything else clears itself.** When a review is approved, rejected, changed, replied to, voted helpful or deleted, Reviews clears the entry's cached pages through Statamic's own invalidation, so your [invalidation rules](https://statamic.dev/static-caching#invalidation) (a listing page that shows ratings, say) are followed too. The average rating is cached until one of the entry's reviews changes, so a busy page reads it once. A "helpful" vote changes only the count beside its button, so with a queue worker, votes clear the entry's pages once for every vote in the next five minutes, not for each.

Sorting and pages use query strings (`?sort=helpful`, `?reviews_page=2`). With `ignore_query_strings` on, every sort and page would be cached as the first one: leave it off on pages with reviews, or turn query strings back on for them.
