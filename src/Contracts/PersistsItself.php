<?php

namespace Xelqun\Forms\Contracts;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Field;

/**
 * A field that writes its own value instead of setting a column on the record,
 * for example to a related table. To read it back, override Field::fillState().
 *
 * @api
 *
 * @phpstan-require-extends Field
 */
interface PersistsItself
{
    /**
     * Whether this field writes its own value. When false it is saved like any
     * other field, as a column named after the field.
     */
    public function shouldPersistItself(): bool;

    /**
     * Write the value, inside the transaction and after $record is saved, so the
     * record has its key. Throwing rolls the whole save back.
     *
     * It only runs when the value was submitted. A hidden field is skipped, so
     * never treat a missing value as a reason to clear anything.
     *
     * Inside a relationship repeater, $record is the row's model. A repeater with
     * no relationship has no model for a row, so the value stays in the row.
     */
    public function persist(Model $record, mixed $state): void;
}
