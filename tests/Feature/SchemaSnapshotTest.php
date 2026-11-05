<?php

use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\CustomField;
use Xelqun\Forms\Components\DatePicker;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Hidden;
use Xelqun\Forms\Components\Radio;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\Textarea;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Fieldset;
use Xelqun\Forms\Layouts\Grid;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\Layouts\Tab;
use Xelqun\Forms\Layouts\Tabs;

/**
 * A snapshot diff is a change to the JSON contract: decide whether the
 * schema version ($xq) needs a bump before updating the snapshot.
 */
function nodeFor(object $component): string
{
    $form = new class extends Form
    {
        public static object $component;

        public function schema(): array
        {
            return [self::$component];
        }
    };
    $form::$component = $component;

    $schema = $form::make()->toSchema();

    return (string) json_encode(['state' => $schema['state'], 'components' => $schema['components']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

it('serializes each component to a stable node', function (object $component) {
    expect(nodeFor($component))->toMatchSnapshot();
})->with([
    'text input' => fn () => TextInput::make('budget')->numeric()->prefix('$')->minValue(0)->required()->hint('Yearly'),
    'email input' => fn () => TextInput::make('email')->email()->placeholder('you@example.com'),
    'display options' => fn () => TextInput::make('code')->readOnly()->autofocus()->hiddenLabel()->inlineLabel()->extraAttributes(['data-kind' => 'code']),
    'textarea' => fn () => Textarea::make('bio')->rows(5)->maxLength(500),
    'select' => fn () => Select::make('plan')->options(['hobby' => 'Hobby', 'pro' => 'Pro'])->default('pro'),
    'multiple select' => fn () => Select::make('tags')->options(['a' => 'A', 'b' => 'B'])->multiple(),
    'async select' => fn () => Select::make('user')->getSearchResultsUsing(fn () => [])->getOptionLabelUsing(fn () => null),
    'checkbox' => fn () => Checkbox::make('terms')->description('Read them first'),
    'toggle' => fn () => Toggle::make('active')->default(true),
    'radio' => fn () => Radio::make('size')->options(['s' => 'Small', 'l' => 'Large'])->inline(),
    'date picker' => fn () => DatePicker::make('starts_on')->minDate('2026-01-01'),
    'file upload' => fn () => FileUpload::make('avatar')->image()->maxSize(1024),
    'hidden' => fn () => Hidden::make('source')->default('web'),
    'custom field' => fn () => CustomField::make('rating')->component('star-rating')->props(['max' => 5]),
    'conditional field' => fn () => TextInput::make('company')->visibleWhen(When::eq('type', 'business'))->requiredWhen(When::filled('vat')),
    'computed field' => fn () => TextInput::make('total')->computed('round(sum(items.*.subtotal) * 1.12, 2)')->disabled(),
    'live field' => fn () => TextInput::make('title')->live(onBlur: true),
    'section' => fn () => Section::make('Details')->description('About you')->columns(2)->collapsible()->schema([TextInput::make('name')]),
    'grid' => fn () => Grid::make(['default' => 2, 'lg' => 4])->schema([TextInput::make('a'), TextInput::make('b')->columnSpanFull()]),
    'fieldset' => fn () => Fieldset::make('Address')->schema([TextInput::make('city')]),
    'tabs' => fn () => Tabs::make()->tabs([Tab::make('One')->schema([TextInput::make('a')]), Tab::make('Two')->schema([TextInput::make('b')])]),
    'repeater' => fn () => Repeater::make('items')->itemLabel('Line')->columns(2)->minItems(1)->maxItems(3)->default([['qty' => 2]])->schema([
        TextInput::make('qty')->numeric()->default(1),
        TextInput::make('subtotal')->computed('qty * 2'),
    ]),
]);
