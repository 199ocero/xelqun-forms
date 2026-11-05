<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Section;

class HiddenFieldsForm extends Form
{
    /** @var array<int, string> */
    public static array $ranHooks = [];

    public function schema(): array
    {
        return [
            Select::make('secret')->options(['a' => 'Top secret'])->searchable()->hidden(),
            Section::make('Admin')->hidden()->schema([
                Select::make('nested')->options(['b' => 'Also secret'])->searchable(),
            ]),
            Select::make('locked')->options(['c' => 'Locked'])->searchable()->disabled(),
            Select::make('shown')->options(['d' => 'Shown'])->searchable(),
            FileUpload::make('file')->disk('public')->hidden(),
            TextInput::make('adminOnly')->hidden()->afterStateUpdated(fn () => self::$ranHooks[] = 'adminOnly'),
            TextInput::make('open')->afterStateUpdated(fn () => self::$ranHooks[] = 'open'),
        ];
    }
}
