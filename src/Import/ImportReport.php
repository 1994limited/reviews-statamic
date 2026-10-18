<?php

namespace NineteenNinetyFour\Reviews\Import;

/**
 * What an import or sync did.
 */
class ImportReport
{
    public int $added = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    /** @var array<int, string> Reviews nothing on the site could be found for, described. */
    public array $unmatched = [];

    public int $failed = 0;

    public function toArray(): array
    {
        return [
            'added' => $this->added,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'unmatched' => array_values(array_unique($this->unmatched)),
            'failed' => $this->failed,
        ];
    }
}
