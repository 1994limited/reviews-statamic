# Settings and configuration

Most things are set in the Control Panel, in **Tools → Addons → Reviews → Settings**. What's saved there wins over `config/reviews.php`, which holds the defaults and the few things that don't belong in the Control Panel (storage, keys and limits). Publish the config file to change it:

```bash
php artisan vendor:publish --tag=reviews-config
```

## Control Panel settings

| Tab | Setting | What it does |
| --- | --- | --- |
| Reviews | Collections | Entries in these collections take reviews. Empty for every collection with pages. |
| | Who can write reviews | Anyone (guests give a name and email), or signed-in users only. |
| | Only buyers can review products | With [Cargo](cargo.md). |
| | A title is required / Text is required | With text optional, a star rating alone is a review. |
| | Criteria | Ratings for particular things, such as Story and Writing, as well as the overall rating. Each has a label, a handle, and optionally the collections it's for. Reviewers may skip them. |
| | Show reviewers' names as | "Jo B." (the default) or the full name. |
| Moderation | Publish without checking | Never (the default), reviews by signed-in users, or reviews by verified buyers. |
| | Email new reviews to | Addresses told about each new review. |
| | Tell reviewers when it's approved / when you reply | [Emails](emails.md). |
| Photos | Allow photos, photos per review, asset container | [Photos](photos.md). Off by default. |
| Cargo | Review requests | [Cargo](cargo.md). Off by default. |

Reviews are never published, held back or hidden because of their star rating: there's deliberately no setting for it ([Privacy](privacy.md#fair-reviews)).

## `config/reviews.php`

| Option | Default | |
| --- | --- | --- |
| `driver` | `file` (`REVIEWS_DRIVER`) | `file` or `database`. [Storage drivers](drivers.md). |
| `drivers.file.directory` | `storage/reviews` | Where review files are kept. |
| `drivers.file.requests` | `storage/reviews-requests.json` | The file driver's record of review request emails. |
| `drivers.database.connection` | `null` (`REVIEWS_DB_CONNECTION`) | The database connection; null for the default. |
| `drivers.database.tables` | `reviews`, `review_votes`, `review_requests` | Table names. |
| `collections` | `[]` | As the setting. |
| `criteria` | `[]` | As the setting: `['handle' => 'value', 'label' => 'Value', 'collections' => ['products']]`. |
| `form.who` | `anyone` | `anyone` or `users`. |
| `form.title_limit`, `body_limit`, `name_limit` | 120, 5000, 60 | The longest title, review and name, in characters. |
| `form.body_minimum` | 0 | The shortest review text accepted. |
| `form.require_title`, `require_body` | false, true | As the settings. |
| `form.max_links` | 2 | Reviews with more links than this are refused. |
| `auto_approve` | `never` | `never`, `users` or `verified`. |
| `name_display` | `short` | `short` or `full`. |
| `photos.*` | off | [Photos](photos.md). |
| `spam.honeypot` | `website` | The hidden field's name. |
| `spam.minimum_seconds` | 3 | The time trap: forms sent sooner are a bot's. 0 turns it off. |
| `spam.captcha.*` | off | [Spam](spam.md#captchas). |
| `throttle.submit` | 5 | Reviews per visitor per ten minutes. |
| `throttle.votes` | 30 | "Helpful" votes per visitor per minute. |
| `per_page` | 10 | Not used by the tags (use `paginate="10"`); for your own code. |
| `schema.types` | `[]` | The schema.org type of each collection's entries: `['books' => 'Book']`. [Templates](templates.md#review-schema). |
| `schema.reviews` | 5 | How many reviews go in the schema. |
| `notifications.to`, `approved`, `replied` | `[]`, false, true | As the settings. |
| `cargo.*` | | [Cargo](cargo.md). |
| `deleted_users` | `anonymise` | When a user is deleted: `anonymise` their reviews or `delete` them. |
| `schedule` | true | Schedule `reviews:send-requests` hourly. False if you schedule it yourself. |
