<?php

namespace NineteenNinetyFour\Reviews\Import;

/**
 * How imported or synced reviews are added.
 */
class ImportOptions
{
    public function __construct(
        /** Count them towards averages and schema, as this site's own. */
        public bool $counted = false,
        /** "keep" what the platform had on show (approved), or hold every one as "pending". */
        public string $status = 'keep',
        /** Add reviews the platform wasn't showing, as rejected, so they're on record. */
        public bool $includeUnpublished = false,
        /** The entry reviews of the business as a whole go on. Without one, they're skipped. */
        public ?string $businessEntry = null,
        /** The site they were written on. */
        public ?string $site = null,
        /** The sync source they come from. */
        public ?string $sourceId = null,
        /** Update reviews added before (a sync), rather than leaving them. */
        public bool $update = false,
    ) {
        $this->status = $status === 'pending' ? 'pending' : 'keep';
    }
}
