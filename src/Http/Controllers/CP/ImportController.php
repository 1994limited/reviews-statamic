<?php

namespace NineteenNinetyFour\Reviews\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\Reviews\Import\ImportOptions;
use NineteenNinetyFour\Reviews\Import\Imports;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Reviews → Import: upload another platform's export, see what would be
 * added, then add it.
 */
class ImportController extends CpController
{
    /** The largest file the Control Panel takes; bigger ones go through `php please reviews:import`. */
    public const MAX_KILOBYTES = 20480;

    public function __construct(Request $request, protected Imports $imports)
    {
        parent::__construct($request);
    }

    public function index(): Response
    {
        $this->authorize('import reviews');

        return $this->page();
    }

    public function preview(Request $request): Response|RedirectResponse
    {
        $this->authorize('import reviews');

        $request->validate(['file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES]]);
        $this->forgetOld();

        $token = Str::random(24);
        $contents = (string) file_get_contents($request->file('file')->getRealPath());
        $options = $this->options($request);
        $format = $this->format($request);
        $result = $this->imports->read($contents, $format);

        // Kept until it's imported, or for a day: the file, and exactly what
        // was previewed, so the import does what the preview said.
        if ($result->format) {
            $this->keep($token, $contents, ['format' => $format, 'options' => (array) $options, 'user' => (string) User::current()?->id()]);
        }

        return $this->page([
            'token' => $result->format ? $token : null,
            'filename' => $request->file('file')->getClientOriginalName(),
            'format' => $result->format?->label(),
            'error' => $result->format ? null : ($result->skipped[0]['reason'] ?? __('The file couldn’t be read.')),
            'skipped' => count($result->skipped),
            'counts' => $result->format ? $this->imports->preview($result->reviews, $options) : null,
        ], $request);
    }

    public function run(Request $request): Response
    {
        $this->authorize('import reviews');

        $token = (string) $request->input('token');
        abort_unless(preg_match('/^[A-Za-z0-9]{24}$/', $token) && File::exists($this->path($token)) && File::exists($this->path($token, 'json')), 404);

        $kept = (array) json_decode((string) File::get($this->path($token, 'json')), true);

        // Only whoever previewed it can import it.
        abort_unless(($kept['user'] ?? null) === (string) User::current()?->id(), 403);

        try {
            $result = $this->imports->read((string) File::get($this->path($token)), $kept['format'] ?? null);
            $report = $this->imports->import($result->reviews, new ImportOptions(...(array) ($kept['options'] ?? [])));
        } finally {
            File::delete([$this->path($token), $this->path($token, 'json')]);
        }

        return $this->page(['done' => $report->toArray() + ['skipped' => count($result->skipped)]]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function keep(string $token, string $contents, array $meta): void
    {
        File::ensureDirectoryExists(dirname($this->path($token)), 0700);

        foreach ([$this->path($token) => $contents, $this->path($token, 'json') => (string) json_encode($meta)] as $path => $data) {
            File::put($path, $data);
            @chmod($path, 0600);
        }
    }

    /**
     * Previews nobody imported are deleted after a day: they hold people's
     * names and email addresses.
     */
    protected function forgetOld(): void
    {
        foreach (File::glob(dirname($this->path('x')).'/*') ?: [] as $file) {
            if (filemtime($file) < time() - 86400) {
                File::delete($file);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function page(array $state = [], ?Request $request = null): Response
    {
        $business = $request?->input('business_entry');

        return Inertia::render('reviews::Import', [
            'formats' => collect(Imports::formats())->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all(),
            'sites' => Site::multiEnabled() ? Site::all()->map(fn ($site) => ['value' => $site->handle(), 'label' => $site->name()])->values()->all() : [],
            'options' => [
                'format' => $request?->input('format') ?: '',
                'counted' => $request?->boolean('counted') ?? false,
                'status' => $request?->input('status') === 'pending' ? 'pending' : 'keep',
                'include_unpublished' => $request?->boolean('include_unpublished') ?? false,
                'business_entry' => is_string($business) && $business !== '' ? $business : '',
                'site' => $request?->input('site') ?: '',
            ],
            'business_title' => is_string($business) && $business !== '' ? Entry::find($business)?->get('title') : null,
            'state' => (object) $state,
            'urls' => [
                'preview' => cp_route('reviews.import.preview'),
                'run' => cp_route('reviews.import.run'),
                'index' => cp_route('reviews.import'),
                'reviews' => cp_route('reviews.index', ['status' => 'all']),
            ],
            'max_kilobytes' => self::MAX_KILOBYTES,
        ]);
    }

    protected function options(Request $request): ImportOptions
    {
        $business = $request->input('business_entry');
        $site = $request->input('site');

        return new ImportOptions(
            counted: $request->boolean('counted'),
            status: $request->input('status') === 'pending' ? 'pending' : 'keep',
            includeUnpublished: $request->boolean('include_unpublished'),
            businessEntry: is_string($business) && preg_match('/^[\w-]{1,64}$/', $business) ? $business : null,
            site: is_string($site) && Site::get($site) ? $site : null,
        );
    }

    protected function format(Request $request): ?string
    {
        $format = $request->input('format');

        return is_string($format) && array_key_exists($format, Imports::formats()) ? $format : null;
    }

    protected function path(string $token, string $extension = 'txt'): string
    {
        return storage_path('framework/reviews-import/'.$token.'.'.$extension);
    }
}
