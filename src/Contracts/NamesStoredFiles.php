<?php

namespace Xelqun\Forms\Contracts;

/**
 * An upload field that can keep each file's original name. HandlesUploads has
 * both methods, so a field that uses it only needs to declare this.
 *
 * @api
 */
interface NamesStoredFiles extends AcceptsUploads
{
    /**
     * Whether a stored file keeps its original name instead of a random one.
     */
    public function preservesFilenames(): bool;

    /**
     * Where to save each file's original name, or null to keep none.
     */
    public function getFileNamesStatePath(): ?string;
}
