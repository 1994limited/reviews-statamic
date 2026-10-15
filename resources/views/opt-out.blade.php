<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('reviews::messages.opt_out.title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f6f6f4; color: #1f2937; margin: 0; padding: 4rem 1rem; }
        main { max-width: 32rem; margin: 0 auto; background: #fff; border-radius: .75rem; padding: 2rem; box-shadow: 0 1px 3px rgb(0 0 0 / .08); }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; }
        button { font: inherit; background: #1f2937; color: #fff; border: 0; border-radius: .5rem; padding: .65rem 1.1rem; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('reviews::messages.opt_out.title') }}</h1>
    @if ($done)
        <p>{{ __('reviews::messages.opt_out.done') }}</p>
    @else
        <p>{{ __('reviews::messages.opt_out.ask', ['email' => $email]) }}</p>
        <form method="post" action="{{ $action }}">
            @csrf
            <button type="submit">{{ __('reviews::messages.opt_out.button') }}</button>
        </form>
    @endif
</main>
</body>
</html>
