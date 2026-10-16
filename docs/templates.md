# Templates

Want it done for you? [Ready-made reviews](ready-made.md) are a finished section with default styles, in two tags. This page is for writing your own markup, with tags. The examples here are complete and tested: copy them into your entry's template (a book, a product, a course) and style them as you like. Each tag finds the entry the template is showing; pass `entry="…"` to use another.

What visitors wrote comes out of the tags **already escaped**, with their line breaks as `<br>`. In Antlers print it as it is (`{{ body }}`). In Blade print it with `{!! !!}`, as the examples do, or it's escaped twice.

## The average rating

```antlers
{{ reviews:summary }}
    {{ if has_reviews }}
        <p>
            <span aria-hidden="true">{{ stars }}{{ if full }}★{{ elseif half }}⯪{{ else }}☆{{ /if }}{{ /stars }}</span>
            {{ average }} out of 5, from {{ count }} {{ count == 1 ? 'review' : 'reviews' }}
        </p>
        <ul>
            {{ breakdown }}
                <li>{{ rating }} stars: {{ total }} ({{ percent }}%)</li>
            {{ /breakdown }}
        </ul>
    {{ else }}
        <p>No reviews yet.</p>
    {{ /if }}
{{ /reviews:summary }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews:summary>
    @if ($has_reviews)
        <p>
            <span aria-hidden="true">@foreach ($stars as $star){{ $star['full'] ? '★' : ($star['half'] ? '⯪' : '☆') }}@endforeach</span>
            {{ $average }} out of 5, from {{ $count }} {{ $count == 1 ? 'review' : 'reviews' }}
        </p>
        <ul>
            @foreach ($breakdown as $row)
                <li>{{ $row['rating'] }} stars: {{ $row['total'] }} ({{ $row['percent'] }}%)</li>
            @endforeach
        </ul>
    @else
        <p>No reviews yet.</p>
    @endif
</s:reviews:summary>
```

</details>

`{{ average }}` is to one decimal place; `average_exact` isn't rounded. `percent` is the average as a percentage of five stars (for a bar, or the width of a row of filled stars). Each of `stars` is `full`, `half` or `empty`. In `breakdown`, use `total`, not `count`, for how many reviews gave that rating: Antlers loops have a `count` of their own.

For just the numbers: `{{ reviews:average }}` and `{{ reviews:count }}`. For stars for any rating: `{{ reviews:stars :rating="average" }}…{{ /reviews:stars }}`.

## The reviews

```antlers
{{ reviews sort="newest" paginate="10" as="items" }}
    {{ items }}
        <article id="review-{{ id }}">
            <p aria-label="{{ rating }} out of 5">{{ stars }}{{ if full }}★{{ else }}☆{{ /if }}{{ /stars }}</p>
            {{ if title }}<h3>{{ title }}</h3>{{ /if }}
            <p>{{ body }}</p>
            <p>
                {{ name }}, {{ date format="j F Y" }}
                {{ if verified }}· Verified purchase{{ /if }}
            </p>
            {{ if photos }}
                {{ photos }}<img src="{{ url }}" alt="{{ alt }}" width="160">{{ /photos }}
            {{ /if }}
            {{ if has_reply }}
                <blockquote><strong>Our reply:</strong> {{ reply }}</blockquote>
            {{ /if }}
            {{ reviews:helpful }}<button>Helpful ({{ helpful }})</button>{{ /reviews:helpful }}
        </article>
    {{ /items }}

    {{ if no_results }}<p>Be the first to review this.</p>{{ /if }}

    {{ paginate }}
        {{ if prev_page }}<a href="{{ prev_page }}">Newer</a>{{ /if }}
        {{ if next_page }}<a href="{{ next_page }}">Older</a>{{ /if }}
    {{ /paginate }}
{{ /reviews }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews sort="newest" paginate="10" as="items">
    @foreach ($items as $review)
        <article id="review-{{ $review['id'] }}">
            <p aria-label="{{ $review['rating'] }} out of 5">@foreach ($review['stars'] as $star){{ $star['full'] ? '★' : '☆' }}@endforeach</p>
            @if ($review['title'])<h3>{!! $review['title'] !!}</h3>@endif
            <p>{!! $review['body'] !!}</p>
            <p>
                {!! $review['name'] !!}, {{ $review['date']->format('j F Y') }}
                @if ($review['verified'])· Verified purchase@endif
            </p>
            @foreach ($review['photos'] as $photo)
                <img src="{{ $photo['url'] }}" alt="{!! $photo['alt'] !!}" width="160">
            @endforeach
            @if ($review['has_reply'])
                <blockquote><strong>Our reply:</strong> {!! $review['reply'] !!}</blockquote>
            @endif
            <s:reviews:helpful :id="$review['id']"><button>Helpful ({{ $review['helpful'] }})</button></s:reviews:helpful>
        </article>
    @endforeach

    @if ($no_results)<p>Be the first to review this.</p>@endif

    @if ($paginate)
        @if ($paginate['prev_page'])<a href="{{ $paginate['prev_page'] }}">Newer</a>@endif
        @if ($paginate['next_page'])<a href="{{ $paginate['next_page'] }}">Older</a>@endif
    @endif
</s:reviews>
```

</details>

Only approved reviews are listed. Choose the order with `sort`: `newest` (the default), `oldest`, `highest`, `lowest` or `helpful`. Filter with `rating="5"` (or `rating="4|5"`), `verified="true"` and `photos="true"`. `limit="3"` shows a few without pages. With `paginate`, pages are `?reviews_page=2` (change it with `page_name`).

To let visitors choose the order, pass it in from the URL: `{{ reviews sort="{ get:sort ?? 'newest' }" }}`. Anything that isn't one of the sorts is read as `newest`.

The `id="review-…"` on each review is what links in emails point to.

## The form

```antlers
{{ reviews:form redirect="#review" }}
    {{ if success }}<p role="status">{{ success }}</p>{{ /if }}
    {{ if reason }}
        <p>{{ reason_message }}</p>
    {{ else }}
        {{ if has_errors }}
            <ul role="alert">{{ error_list }}<li>{{ value }}</li>{{ /error_list }}</ul>
        {{ /if }}

        <fieldset>
            <legend>Your rating</legend>
            {{ loop from="5" to="1" }}
                <label>
                    <input type="radio" name="rating" value="{{ value }}" required {{ if old:rating == value }}checked{{ /if }}>
                    {{ value }} {{ value == 1 ? 'star' : 'stars' }}
                </label>
            {{ /loop }}
        </fieldset>

        {{ criteria }}
            <label>{{ label }}
                <select name="{{ name }}">
                    <option value="">–</option>
                    {{ loop from="5" to="1" }}<option value="{{ value }}" {{ if old == value }}selected{{ /if }}>{{ value }}</option>{{ /loop }}
                </select>
            </label>
        {{ /criteria }}

        <label>Title <input name="title" value="{{ old:title }}" maxlength="{{ title_limit }}"></label>
        <label>Your review <textarea name="body" maxlength="{{ body_limit }}" required>{{ old:body }}</textarea></label>

        {{ if needs_name }}
            <label>Your name <input name="name" value="{{ old:name }}" required></label>
            <label>Your email (not shown) <input type="email" name="email" value="{{ old:email }}" required></label>
        {{ /if }}

        {{ if photos_enabled }}
            <label>Photos (up to {{ max_photos }}) <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        {{ /if }}

        <button type="submit">{{ editing ? 'Update your review' : 'Send your review' }}</button>
    {{ /if }}
{{ /reviews:form }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews:form redirect="#review">
    @if ($success)<p role="status">{!! $success !!}</p>@endif
    @if ($reason)
        <p>{!! $reason_message !!}</p>
    @else
        @if ($has_errors)
            <ul role="alert">@foreach ($error_list as $message)<li>{!! $message !!}</li>@endforeach</ul>
        @endif

        <fieldset>
            <legend>Your rating</legend>
            @foreach ([5, 4, 3, 2, 1] as $value)
                <label>
                    <input type="radio" name="rating" value="{{ $value }}" required @checked($old['rating'] == $value)>
                    {{ $value }} {{ $value == 1 ? 'star' : 'stars' }}
                </label>
            @endforeach
        </fieldset>

        @foreach ($criteria as $criterion)
            <label>{!! $criterion['label'] !!}
                <select name="{{ $criterion['name'] }}">
                    <option value="">–</option>
                    @foreach ([5, 4, 3, 2, 1] as $value)<option value="{{ $value }}" @selected($criterion['old'] == $value)>{{ $value }}</option>@endforeach
                </select>
            </label>
        @endforeach

        <label>Title <input name="title" value="{!! $old['title'] !!}" maxlength="{{ $title_limit }}"></label>
        <label>Your review <textarea name="body" maxlength="{{ $body_limit }}" required>{!! $old['body'] !!}</textarea></label>

        @if ($needs_name)
            <label>Your name <input name="name" value="{!! $old['name'] !!}" required></label>
            <label>Your email (not shown) <input type="email" name="email" value="{!! $old['email'] !!}" required></label>
        @endif

        @if ($photos_enabled)
            <label>Photos (up to {{ $max_photos }}) <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        @endif

        <button type="submit">{{ $editing ? 'Update your review' : 'Send your review' }}</button>
    @endif
</s:reviews:form>
```

</details>

The tag adds what the form needs (the entry, CSRF, the spam checks) around your fields. It works without JavaScript: a plain post, back to the page with a message (`success`) or the errors and what they typed. Field names to use:

| Field | |
| --- | --- |
| `rating` | Required, 1 to 5. |
| `ratings[handle]` | Each criterion, 1 to 5, optional. `{{ criteria }}` gives each one's `name`. |
| `title` | Optional unless the settings require it. |
| `body` | The review. Required unless the settings say a rating alone is enough. |
| `name`, `email` | Guests only (`needs_name`). Signed-in reviewers use their account. |
| `photos[]` | When photos are on ([Photos](photos.md)). |

When a visitor can't review, `reason` says why: `sign_in`, `buyers_only` (Cargo), or `unavailable` (the entry doesn't take reviews), and `reason_message` says it in words. A signed-in reviewer who has reviewed the entry gets their review back in the form (`editing`), to change it; a changed review goes back to an editor unless it'd be published straight away.

`redirect` sends them somewhere else afterwards (only within your site); `error_redirect` sends errors somewhere else.

**On a statically cached page**, wrap the form in `{{ nocache }}`: see [Static caching](static-caching.md).

## "Was this helpful?"

Inside the list, `{{ reviews:helpful }}` is a form with a button; `{{ helpful }}` is the count. One vote per person: their account, or a cookie for guests. It works without JavaScript.

```antlers
{{ reviews:helpful }}<button>Helpful ({{ helpful }})</button>{{ /reviews:helpful }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews:helpful :id="$review['id']"><button>Helpful ({{ $review['helpful'] }})</button></s:reviews:helpful>
```

</details>

## Deleting your own review

A signed-in reviewer can delete their review. Inside the list:

```antlers
{{ if is_mine }}{{ reviews:delete }}<button>Delete my review</button>{{ /reviews:delete }}{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@if ($review['is_mine'])<s:reviews:delete :id="$review['id']"><button>Delete my review</button></s:reviews:delete>@endif
```

</details>

## Messages

The form's own `success` and errors cover it. Elsewhere on the page (after a vote, say), `{{ reviews:success }}` is the message, and `{{ reviews:errors }}…{{ /reviews:errors }}` the errors (`field`, `message`).

## Review schema

Search engines can show an entry's star rating in results from review schema (schema.org JSON-LD). Add it anywhere in the entry's template:

```antlers
{{ reviews:schema type="Book" }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews:schema type="Book" />
```

</details>

It has the entry's name and address, its average rating and number of reviews, and its latest five reviews. It's left out until an entry has an approved review. Nothing a reviewer wrote can break out of the script tag.

- `type`: the schema.org type, such as `Product`, `Book`, `Course`, `Recipe`, `LocalBusiness` or `SoftwareApplication`. Set it per collection with `schema.types` in the config instead. Without either, Cargo's products are `Product` and anything else `Thing`.
- `id`: an `@id`, such as `{{ permalink }}#product`, to join the rating to schema you already print for the entry (your SEO addon's, say), so search engines read them as one thing.
- `reviews="0"`: the average only.

### With an SEO addon

If an SEO addon prints schema for your entries too, join the two so search engines read them as one thing: see [Review schema and SEO addons](seo.md), with steps for SEO Pro.

Google shows stars only for some types, and not for reviews a business publishes about itself (a `LocalBusiness` or `Organization` reviewing itself on its own site). Product, book, course and recipe reviews are fine.

## Antlers or Blade

Every tag works in Blade as `<s:reviews…>`. Variables are the same, as `$name` inside the tag; in a list, each review is an array (`$review['title']`). Remember `{!! !!}` for what reviewers wrote.
