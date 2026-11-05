<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;

class DeniedForm extends Form
{
    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return false;
    }

    public function schema(): array
    {
        return [TextInput::make('name')];
    }
}
