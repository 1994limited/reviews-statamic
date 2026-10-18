<?php

namespace NineteenNinetyFour\Reviews\Sync;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Statamic\Facades\YAML;

/**
 * Where sync sources are kept: beside the reviews, in a YAML file (the file
 * driver) or a table (the database driver). Never in the addon's settings,
 * which are often committed to git. Secret keys are encrypted with the app
 * key, unless they're environment variables.
 */
class SourceStore
{
    /**
     * @return array<string, Source>
     */
    public function all(): array
    {
        $sources = [];

        foreach ($this->read() as $id => $data) {
            $sources[(string) $id] = Source::fromArray((string) $id, $this->decrypt((array) $data));
        }

        uasort($sources, fn (Source $a, Source $b) => strcasecmp($a->name, $b->name));

        return $sources;
    }

    public function find(string $id): ?Source
    {
        return $this->all()[$id] ?? null;
    }

    public function save(Source $source): void
    {
        $data = $this->encrypt($source);

        if ($this->database()) {
            $now = now();
            $table = $this->table();
            $exists = $table->where('id', $source->id)->exists();

            $exists
                ? $this->table()->where('id', $source->id)->update(['data' => json_encode($data), 'updated_at' => $now])
                : $this->table()->insert(['id' => $source->id, 'data' => json_encode($data), 'created_at' => $now, 'updated_at' => $now]);

            return;
        }

        $all = $this->read();
        $all[$source->id] = $data;
        $this->write($all);
    }

    public function delete(Source $source): void
    {
        if ($this->database()) {
            $this->table()->where('id', $source->id)->delete();

            return;
        }

        $all = $this->read();
        unset($all[$source->id]);
        $this->write($all);
    }

    /**
     * @return array<string, mixed>
     */
    protected function encrypt(Source $source): array
    {
        $data = $source->toArray();
        $fields = $source->platform()?->fields() ?? [];

        foreach ($data['settings'] as $key => $value) {
            if (($fields[$key]['secret'] ?? false) && $value !== '' && ! str_starts_with($value, '$')) {
                $data['settings'][$key] = 'encrypted:'.Crypt::encryptString($value);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function decrypt(array $data): array
    {
        foreach ((array) ($data['settings'] ?? []) as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'encrypted:')) {
                try {
                    $data['settings'][$key] = Crypt::decryptString(substr($value, 10));
                } catch (DecryptException) {
                    // Encrypted with another app key: enter it again.
                    $data['settings'][$key] = '';
                }
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(): array
    {
        if ($this->database()) {
            return $this->table()->get()->mapWithKeys(fn ($row) => [$row->id => (array) json_decode($row->data, true)])->all();
        }

        $path = $this->path();

        return File::exists($path) ? (array) YAML::parse(File::get($path)) : [];
    }

    /**
     * @param  array<string, mixed>  $sources
     */
    protected function write(array $sources): void
    {
        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), YAML::dump($sources));
    }

    protected function database(): bool
    {
        return config('reviews.driver') === 'database';
    }

    protected function table()
    {
        return DB::connection(config('reviews.drivers.database.connection') ?: null)->table(config('reviews.drivers.database.tables.sources', 'review_sources'));
    }

    protected function path(): string
    {
        return (string) config('reviews.drivers.file.sources', storage_path('reviews-sources.yaml'));
    }
}
