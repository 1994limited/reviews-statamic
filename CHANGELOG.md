# Changelog

## 1.1.1 (unreleased)

### Fixed
- **"Helpful" counts can't go backwards.** Saving a review that was loaded before someone voted (a reply, say) no longer puts the old count back, with either driver. The file driver now changes one review at a time, re-reading it from disk, so two requests can't overwrite each other's votes.
- **Averages can't stick at an old value.** A page working out an entry's numbers as one of its reviews changed could cache the old numbers for good; each change now starts afresh.
- **Photos move safely on approval.** They're copied, the review is saved, and only then are the old copies removed, so a failed save can't leave a photo public or lost.
- **Signed-in reviewers are shown by their account's name**, whatever a form sends, as the docs say.
- **The file driver's review-request log** is read with a lock, so a request is never sent twice, or to someone who opted out, while the log is being written.

### Safety
- **The form no longer reveals who has reviewed what.** A guest's second review of an entry from the same email address is thanked as a first would be (and not kept), instead of saying they've already reviewed it. Two arriving at once can't both be kept.

### Docs
- Guest "helpful" votes are by cookie: one per person as far as that goes without an account.

## 1.1.0 (2026-10-16)

### Added
- **Ready-made reviews** (`{{ reviews:display }}`): a finished section with the average and star breakdown, the reviews (sorting, a rating filter, pages, "helpful" buttons, replies) and the form, in one tag, or in parts (`display_summary`, `display_list`, `display_form`). Works without JavaScript and on statically cached pages; the views can be published and edited.
- **Default styles** (`{{ reviews:styles }}`) that take the site's own font, size and colours, follow a site's dark mode without a setting of their own, with every colour, size and gap a `--reviews-*` CSS variable. In a cascade layer, so any CSS of the site's own wins. Publish them to edit (`--tag=reviews-css`), or link to them yourself (`{{ reviews:styles_url }}`).
- **Review schema in an SEO addon's own schema** (`{{ reviews:schema_data }}`): the rating and reviews as JSON, or as properties that keep the JSON valid with or without reviews, for fields such as SEO Pro's JSON-LD Schema field. `Schema::parts()` for the same in PHP.
- **The translations can be published** (`--tag=reviews-translations`).

## 1.0.0 (2026-10-16)

The first release.

### Added
- **A review form for any entry** (`{{ reviews:form }}`): an overall rating out of five, optional ratings for criteria you set, a title, the review, and a name and email for guests. Works without JavaScript. Signed-in reviewers can change or delete their review.
- **Who can review**: anyone, signed-in users only, and (with Cargo) only customers who bought the product. Only published, live entries in the collections you choose.
- **Moderation** in Content → Reviews: pending, approved, rejected and spam, search and filters, bulk actions, editors' notes and a public reply. Publish reviews by signed-in users or verified buyers straight away, if you like. Permissions for viewing, moderating, replying and deleting.
- **The reviews** (`{{ reviews }}`): sorted by newest, oldest, highest, lowest or most helpful; filtered by rating, verified purchases and photos; paginated.
- **The numbers** (`{{ reviews:summary }}`, `reviews:average`, `reviews:count`, `reviews:stars`): the average, count, each star's share and each criterion's average, cached until a review changes.
- **"Was this helpful?"** votes, once per person.
- **Review schema** (`{{ reviews:schema }}`): JSON-LD with AggregateRating and the latest reviews, for any schema.org type, and an `@id` to join your own schema.
- **Photos**, off by default: re-saved as JPEGs without their metadata, private until the review is approved, then moved into an asset container.
- **Emails**: new reviews to editors; approvals and replies to reviewers. Queued.
- **Cargo**: verified purchases, reviews from buyers only, and review request emails a set time after an order, with an opt-out.
- **Storage** in flat files (indexed by the Stache) or the database, and `reviews:transfer` between them.
- **Privacy**: `reviews:export` and `reviews:forget`; reviews anonymised when their user is deleted.
- **A dashboard widget** of reviews waiting, and a **Reviews field** showing an entry's rating in the Control Panel.
- **Settings** in the Control Panel. Every tag works in Antlers and Blade.
- **Events** and a PHP API.

### Safety
- **Spam**: a honeypot, a time trap that works on statically cached pages, a link limit, and rate limits on every form. Optional captchas: Cloudflare Turnstile, hCaptcha, reCAPTCHA v2 and v3, or your own.
- **Everything a reviewer wrote is escaped** in templates, the Control Panel and emails, and can't break out of review schema.
- **No IP addresses are kept.** Votes and opt-outs are kept as hashes.
- **Redirects stay on your site.**
- **Photos are size-checked before they’re opened**, so a small file can’t unpack into an image that exhausts memory.
- **Fair reviews**: nothing publishes or hides reviews by their rating, and editors can't change a reviewer's words.
