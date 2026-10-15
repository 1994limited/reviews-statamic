# Installation

## Requirements

- PHP 8.3 or later, with GD if reviewers can add photos.
- Statamic 6. Statamic Pro for signed-in reviewers and Control Panel permissions.
- Optional: [Cargo](https://builtwithcargo.dev) 1.15 or later (before 2.0), for verified purchases.

## Installing

```bash
composer require 1994/reviews-statamic
```

That's all that's needed to start. Reviews are kept in YAML files in `storage/reviews` (see [Storage drivers](drivers.md) for the database), and every collection with pages can be reviewed until you choose some in the settings.

Then:

1. Choose which collections take reviews, and how moderation works, in **Tools → Addons → Reviews → Settings** ([Settings](configuration.md)).
2. Add the average rating, the reviews and the form to your entry template ([Templates](templates.md)).
3. Give your editors the **Reviews** permissions ([Moderation](moderation.md)).
4. Optionally add a captcha ([Spam](spam.md)), photos ([Photos](photos.md)) or, in a Cargo shop, review request emails ([Cargo](cargo.md)).

To change the config file's defaults, publish it:

```bash
php artisan vendor:publish --tag=reviews-config
```

## Licensing

Reviews is commercial software, sold through the [Statamic Marketplace](https://statamic.com/marketplace). It's free to try on local and development sites. Each public site needs a licence, bought from the Marketplace and added to your site in the usual way.

## Updating

```bash
composer update 1994/reviews-statamic
```

Read the [changelog](../CHANGELOG.md) first. Releases follow semantic versioning: nothing in a minor or patch release needs you to change your templates.

## Uninstalling

```bash
composer remove 1994/reviews-statamic
```

Reviews themselves are left where they were (`storage/reviews`, or the database tables) until you delete them.
