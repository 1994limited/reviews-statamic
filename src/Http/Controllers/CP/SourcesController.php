<?php

namespace NineteenNinetyFour\Reviews\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\Reviews\Core\Sync\Source as Platform;
use NineteenNinetyFour\Reviews\Core\Sync\Sources;
use NineteenNinetyFour\Reviews\Sync\Source;
use NineteenNinetyFour\Reviews\Sync\Sync;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Entry;
use Statamic\Http\Controllers\CP\CpController;
use Throwable;

/**
 * Reviews → Sync: the platforms reviews are synced from.
 */
class SourcesController extends CpController
{
    public function __construct(Request $request, protected Sync $sync)
    {
        parent::__construct($request);
    }

    public function index(): Response
    {
        $this->authorize('import reviews');

        return Inertia::render('reviews::Sources', [
            'sources' => collect($this->sync->all())->map(fn (Source $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'platform' => $source->platform()?->label() ?? $source->type,
                'enabled' => $source->enabled,
                'last_synced' => $source->lastSynced?->toIso8601String(),
                'last_error' => $source->lastError,
                'edit_url' => cp_route('reviews.sources.edit', $source->id),
                'run_url' => cp_route('reviews.sources.run', $source->id),
            ])->values()->all(),
            'platforms' => collect(Sources::all())->map(fn (Platform $platform, $key) => [
                'value' => $key,
                'label' => $platform->label(),
                'create_url' => cp_route('reviews.sources.create', ['type' => $key]),
            ])->values()->all(),
            'urls' => ['reviews' => cp_route('reviews.index', ['status' => 'all'])],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('import reviews');

        $type = (string) $request->query('type');
        abort_unless(Sources::find($type), 404);

        return $this->form(Source::fromArray('', ['type' => $type, 'name' => Sources::find($type)->label()]));
    }

    public function edit(string $source): Response
    {
        $this->authorize('import reviews');

        return $this->form($this->source($source));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('import reviews');

        $type = (string) $request->input('type');
        abort_unless(Sources::find($type), 404);

        return $this->persist(Source::fromArray('', ['type' => $type]), $request);
    }

    public function update(Request $request, string $source): RedirectResponse
    {
        $this->authorize('import reviews');

        return $this->persist($this->source($source), $request);
    }

    public function destroy(Request $request, string $source): RedirectResponse
    {
        $this->authorize('import reviews');

        $this->sync->delete($this->source($source), $request->boolean('with_reviews'));
        Toast::success(__('Source removed.'));

        return redirect()->to(cp_route('reviews.sources'));
    }

    public function run(string $source): RedirectResponse
    {
        $this->authorize('import reviews');

        try {
            $report = $this->sync->run($this->source($source));
            Toast::success(__('Synced: :added added, :updated updated, :unmatched matched nothing.', [
                'added' => $report->added,
                'updated' => $report->updated,
                'unmatched' => count(array_unique($report->unmatched)),
            ]));
        } catch (Throwable $e) {
            Toast::error($e->getMessage());
        }

        return redirect()->to(cp_route('reviews.sources'));
    }

    protected function persist(Source $source, Request $request): RedirectResponse
    {
        $platform = $source->platform();
        $posted = (array) $request->input('settings', []);
        $settings = [];

        foreach ($platform?->fields() ?? [] as $key => $field) {
            $value = trim((string) ($posted[$key] ?? ''));

            // A secret left empty keeps the one saved.
            if ($field['secret'] && $value === '' && isset($source->settings[$key])) {
                $value = $source->settings[$key];
            }

            $settings[$key] = $value;
        }

        $business = $request->input('business_entry');

        $source->name = trim((string) $request->input('name'));
        $source->settings = $settings;
        $source->enabled = $request->boolean('enabled');
        $source->countInSummary = $request->boolean('count_in_summary');
        $source->frequency = max(1, min(720, (int) $request->input('frequency', 24)));
        $source->businessEntry = is_string($business) && preg_match('/^[\w-]{1,64}$/', $business) ? $business : null;

        $errors = $this->sync->save($source);

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        Toast::success(__('Source saved.'));

        return redirect()->to(cp_route('reviews.sources'));
    }

    protected function form(Source $source): Response
    {
        $platform = $source->platform();

        return Inertia::render('reviews::Source', [
            'source' => [
                'id' => $source->id ?: null,
                'type' => $source->type,
                'name' => $source->name,
                'enabled' => $source->enabled,
                'count_in_summary' => $source->countInSummary,
                'frequency' => $source->frequency,
                'business_entry' => $source->businessEntry ?? '',
                'business_title' => $source->businessEntry ? Entry::find($source->businessEntry)?->get('title') : null,
                'last_synced' => $source->lastSynced?->toIso8601String(),
                'last_error' => $source->lastError,
                // Secrets aren't sent back to the browser: only whether there's one saved.
                'settings' => collect($platform?->fields() ?? [])->map(fn ($field, $key) => $field['secret'] && ! str_starts_with($source->settings[$key] ?? '', '$') ? '' : ($source->settings[$key] ?? ''))->all(),
                'saved_secrets' => collect($platform?->fields() ?? [])->filter(fn ($field, $key) => $field['secret'] && ($source->settings[$key] ?? '') !== '')->keys()->values()->all(),
            ],
            'platform' => [
                'label' => $platform?->label(),
                'fields' => collect($platform?->fields() ?? [])->map(fn ($field, $key) => ['handle' => $key] + $field)->values()->all(),
                'attribution' => $platform?->attribution()?->note,
                'schema_allowed' => $platform?->schemaAllowed() ?? false,
            ],
            'urls' => [
                'save' => $source->id ? cp_route('reviews.sources.update', $source->id) : cp_route('reviews.sources.store'),
                'destroy' => $source->id ? cp_route('reviews.sources.destroy', $source->id) : null,
                'index' => cp_route('reviews.sources'),
            ],
        ]);
    }

    protected function source(string $id): Source
    {
        $source = $this->sync->find($id);
        abort_unless($source, 404);

        return $source;
    }
}
