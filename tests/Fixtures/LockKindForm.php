<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\State\Get;

/**
 * "reviewer" stays locked on every pass, but how it is locked changes once
 * "role" is back to its server value.
 */
class LockKindForm extends Form
{
    public function schema(): array
    {
        return [
            TextInput::make('reviewer')->default('Server')->dehydrated()
                ->hidden(fn (Get $get): bool => $get('role') === 'admin')
                ->disabled(fn (Get $get): bool => $get('role') === 'member'),
            TextInput::make('role')->default('member')->disabled(),
        ];
    }
}
