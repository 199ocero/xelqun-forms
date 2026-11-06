<?php

namespace Xelqun\Forms\Contracts;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Component;

/**
 * A component that also has work to do before its record is saved, for example
 * setting a foreign key on it. persist() still runs after the save.
 *
 * @api
 *
 * @phpstan-require-extends Component
 */
interface PersistsBeforeSave extends PersistsItself
{
    /**
     * Runs inside the transaction, before $record is saved, so a new record has
     * no key yet. Set attributes on $record here and they are saved with it.
     * It runs under the same rules as persist().
     */
    public function persistBeforeSave(Model $record, mixed $state): void;
}
