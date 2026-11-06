<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

class ScopedTag extends Tag
{
    protected $table = 'tags';

    protected static function booted(): void
    {
        static::addGlobalScope('public', fn (Builder $query) => $query->where('group', 'public'));
    }
}
