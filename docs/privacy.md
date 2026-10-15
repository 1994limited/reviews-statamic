# Privacy

## What's kept

For each review: the rating, title and text, the name and email address given (or the user account), the site it was written on, and, with Cargo, the order that verified it. Email addresses are never shown on the site.

**Not kept**: IP addresses and browser details. The rate limits use the IP address, but only in your cache, for as long as the limit lasts. "Helpful" votes are kept as a one-way hash of the voter's account, or of a random cookie (`reviews_voter`, for a year) for guests. Review request opt-outs are kept as a hash of the address.

Mention reviews in your privacy notice: what you keep, why, and for how long. If you use a captcha, name it too ([Spam](spam.md#captchas)).

## Requests for someone's data

Export everything about one person's reviews as JSON:

```bash
php please reviews:export someone@example.com --output=reviews.json
```

Forget them: their name and email are taken off their reviews (shown as "A customer"), and they're sent no more review requests:

```bash
php please reviews:forget someone@example.com
```

Add `--delete` to delete their reviews instead.

When a user is deleted in the Control Panel, their reviews are anonymised in the same way. Set `deleted_users` to `delete` in the config to delete them instead.

## Fair reviews

Laws in the UK (the Digital Markets, Competition and Consumers Act 2024), the EU and the US (the FTC's rule on consumer reviews, 2024) ban fake reviews and hiding negative ones. Reviews is built to keep you on the right side of them:

- There's **no setting to publish, hold or hide reviews by their rating**. Moderation is for spam, abuse and reviews that aren't about the product, whatever their stars.
- **Editors can't change what a reviewer wrote.** They can reply in public.
- **Verified purchases** are only marked from real, paid orders.
- **Review requests** go to every customer, not just the happy ones, and offer nothing in return.

How you moderate is up to you, and so is following the law where you are: these are no substitute for advice. The [CMA's guidance on fake reviews](https://www.gov.uk/government/publications/fake-reviews-cma208) is a good start in the UK.
