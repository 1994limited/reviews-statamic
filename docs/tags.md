# Tags

Every tag finds its entry from `entry="…"`, or else the entry the template is showing (its `id`). What reviewers wrote comes out escaped; print it as it is in Antlers, and with `{!! !!}` in Blade. Each tag works in Blade as `<s:reviews…>`.

## `{{ reviews }}`

The entry's approved reviews. A pair, looping over them; or with `as="items"`, once, with the reviews in `items` and pagination alongside.

| Parameter | |
| --- | --- |
| `entry` | The entry's ID. |
| `sort` | `newest` (default), `oldest`, `highest`, `lowest`, `helpful`. |
| `rating` | Only these ratings: `5`, or `4\|5`. |
| `verified` | `true` for verified purchases only. |
| `photos` | `true` for reviews with photos only. |
| `site` | `current` for reviews written on the current site only. All sites by default. |
| `limit` | At most this many. |
| `paginate` | This many per page, with `paginate` variables. |
| `page_name` | The query string for the page (`reviews_page`). |
| `as` | The variable the reviews are in. |

Each review:

| Variable | |
| --- | --- |
| `id` | The review's ID. |
| `rating`, `stars` | Out of five, and five stars (`full`, `half`, `empty`, `type`, `number`). |
| `ratings` | Each criterion rated: `handle`, `label`, `rating`, `stars`. |
| `title`, `body` | Escaped; `body` keeps line breaks as `<br>`. |
| `name` | As the settings show names ("Jo B."). Escaped. |
| `verified` | Bought from you (Cargo). |
| `date`, `updated` | Dates; use the `format` modifier. |
| `reply`, `has_reply`, `reply_date` | The site's public reply. |
| `helpful` | How many found it helpful. |
| `photos` | Approved photos: `url`, `id`, `alt`, `width`, `height`. |
| `is_mine` | Written by the signed-in visitor. |

With `paginate` (and `as`): `paginate:total_items`, `total_pages`, `current_page`, `prev_page`, `next_page` and `links` (`page`, `url`, `current`). `no_results` and `total_results` too.

## `{{ reviews:summary }}`

| Variable | |
| --- | --- |
| `count`, `has_reviews`, `no_reviews` | How many approved reviews. |
| `average`, `average_exact` | To one decimal place, and exact. Empty with no reviews. |
| `percent` | The average out of 100 (4.5 is 90). |
| `stars` | Five stars for the average. |
| `breakdown` | 5 to 1: `rating`, `total`, `percent`. |
| `criteria` | Each criterion: `handle`, `label`, `average`, `percent`, `stars`. |

Parameters: `entry`, `site`.

## `{{ reviews:count }}`, `{{ reviews:average }}`

The numbers alone.

## `{{ reviews:stars rating="4.5" }}`

Five stars for any rating: each with `full`, `half`, `empty`, `type` and `number`.

## `{{ reviews:form }}`

The review form. Parameters: `entry`, `redirect`, `error_redirect`, and any HTML attribute (`class`, `id`).

| Variable | |
| --- | --- |
| `can_review`, `cannot_review` | Whether the visitor may review it. |
| `reason`, `reason_message` | Why not: `sign_in`, `buyers_only`, `unavailable`. |
| `signed_in`, `user_name` | |
| `needs_name`, `needs_email` | Guests give their name and email. |
| `buyers_only` | Only buyers may review it (Cargo). |
| `criteria` | `handle`, `label`, `name` (the field's name), `old`, `error`. |
| `old` | What they sent last time, or their own review: `old:rating`, `old:title`, `old:body`, `old:name`, `old:email`. Escaped. |
| `errors`, `error_list`, `has_errors` | `errors:body` is the body's error; `error_list` all of them. Escaped. |
| `success` | The thank-you message. |
| `editing`, `review` | A signed-in reviewer's own review, to change. `review:pending` while it waits. |
| `existing_photos` | Their review's photos: `index`, `url`, `pending`. A checkbox `remove_photos[]` with the `index` removes one. |
| `photos_enabled`, `max_photos`, `max_photo_size` | |
| `title_limit`, `body_limit`, `name_limit` | For `maxlength`. |
| `captcha` | The captcha, to place it yourself. Otherwise it's added at the end of the form. |

## `{{ reviews:helpful }}`

A "helpful" button's form, inside `{{ reviews }}` (or with `id="…"`). Parameters: `redirect`.

## `{{ reviews:delete }}`

Deleting the signed-in visitor's own review, inside `{{ reviews }}` where `is_mine`.

## `{{ reviews:success }}`, `{{ reviews:errors }}`

The message after sending a review or a vote, and the errors (a pair: `field`, `message`; single: the first).

## `{{ reviews:schema }}`

Review schema for the entry. Parameters: `type`, `id`, `reviews`. See [Templates](templates.md#review-schema).

## `{{ reviews:can_review }}`

Whether the visitor may review the entry.
