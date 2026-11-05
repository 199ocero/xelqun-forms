<?php

namespace Xelqun\Forms\Contracts;

use Xelqun\Forms\Components\Field;

/**
 * A field the upload endpoint may store files for. Use the HandlesUploads trait
 * unless you have a reason not to: it implements this contract and validates
 * the state, so a posted path can't point at a file the record never had.
 *
 * The field's state holds one token or one stored path. On save, the token is
 * replaced by the stored path before the field's value is written anywhere.
 *
 * @api
 *
 * @phpstan-require-extends Field
 */
interface AcceptsUploads
{
    /**
     * Identifies this field across requests. An upload can only be used by the
     * field it was made for, so two fields with different rules need two scopes.
     */
    public function getUploadScope(): string;

    /**
     * Rules for the uploaded file itself, checked by the upload endpoint.
     *
     * @return array<int, mixed>
     */
    public function getUploadRules(): array;

    public function getDiskName(): string;

    public function getDirectory(): string;

    public function getVisibility(): string;
}
