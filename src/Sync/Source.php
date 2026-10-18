<?php

namespace NineteenNinetyFour\Reviews\Sync;

use Carbon\CarbonImmutable;
use NineteenNinetyFour\Reviews\Core\Sync\Source as Platform;
use NineteenNinetyFour\Reviews\Core\Sync\Sources;

/**
 * A platform reviews are synced from, with the keys to fetch them.
 */
class Source
{
    public string $id = '';

    public string $type = '';

    public string $name = '';

    /** @var array<string, string> The platform's settings. Any can be an environment variable: "$JUDGEME_TOKEN". */
    public array $settings = [];

    public bool $enabled = true;

    /**
     * Count its reviews towards averages and schema. Only for a platform
     * that allows it (Feefo): Google's guidelines say reviews gathered from
     * other websites mustn't be marked up.
     */
    public bool $countInSummary = false;

    /** The entry its reviews of the business as a whole go on. */
    public ?string $businessEntry = null;

    /** Hours between syncs. */
    public int $frequency = 24;

    public ?CarbonImmutable $lastSynced = null;

    public ?string $lastError = null;

    public function platform(): ?Platform
    {
        return Sources::find($this->type);
    }

    /**
     * The settings with environment variables filled in, for fetching.
     *
     * @return array<string, string>
     */
    public function resolvedSettings(): array
    {
        return array_map(function ($value) {
            $value = (string) $value;

            return preg_match('/^\$([A-Z0-9_]+)$/', $value, $match) ? (string) env($match[1], '') : $value;
        }, $this->settings);
    }

    public function counts(): bool
    {
        return $this->countInSummary && ($this->platform()?->schemaAllowed() ?? false);
    }

    public function isDue(?CarbonImmutable $now = null): bool
    {
        if (! $this->enabled || $this->platform() === null) {
            return false;
        }

        return $this->lastSynced === null || $this->lastSynced->addHours(max(1, $this->frequency))->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    /**
     * What's stored: secrets as they're given, for the store to encrypt.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'settings' => $this->settings,
            'enabled' => $this->enabled,
            'count_in_summary' => $this->countInSummary,
            'business_entry' => $this->businessEntry,
            'frequency' => $this->frequency,
            'last_synced' => $this->lastSynced?->toIso8601String(),
            'last_error' => $this->lastError,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $id, array $data): static
    {
        $source = new static;
        $source->id = $id;
        $source->type = (string) ($data['type'] ?? '');
        $source->name = (string) ($data['name'] ?? '');
        $source->settings = array_map('strval', array_filter((array) ($data['settings'] ?? []), 'is_scalar'));
        $source->enabled = (bool) ($data['enabled'] ?? true);
        $source->countInSummary = (bool) ($data['count_in_summary'] ?? false);
        $source->businessEntry = is_string($data['business_entry'] ?? null) && $data['business_entry'] !== '' ? $data['business_entry'] : null;
        $source->frequency = max(1, (int) ($data['frequency'] ?? 24));
        $source->lastSynced = is_string($data['last_synced'] ?? null) ? CarbonImmutable::parse($data['last_synced']) : null;
        $source->lastError = is_string($data['last_error'] ?? null) ? $data['last_error'] : null;

        return $source;
    }
}
