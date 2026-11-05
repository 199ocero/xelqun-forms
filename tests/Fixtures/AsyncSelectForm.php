<?php

namespace Xelqun\Forms\Tests\Fixtures;

use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Form;

class AsyncSelectForm extends Form
{
    public function schema(): array
    {
        return [
            Select::make('tags')
                ->multiple()
                ->getSearchResultsUsing(fn (): array => ['a' => 'A'])
                ->getOptionLabelUsing(fn (string $value): ?string => $value === 'a' ? 'A' : null),
        ];
    }
}
