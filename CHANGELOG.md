# Changelog

## 1.0.0 (unreleased)

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
