<?php

use Illuminate\Support\HtmlString;
use Xelqun\Forms\Components\Checkbox;
use Xelqun\Forms\Components\CheckboxList;
use Xelqun\Forms\Components\ColorPicker;
use Xelqun\Forms\Components\CustomField;
use Xelqun\Forms\Components\DatePicker;
use Xelqun\Forms\Components\DateTimePicker;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Hidden;
use Xelqun\Forms\Components\KeyValue;
use Xelqun\Forms\Components\Radio;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\Slider;
use Xelqun\Forms\Components\TagsInput;
use Xelqun\Forms\Components\Textarea;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Components\TimePicker;
use Xelqun\Forms\Components\Toggle;
use Xelqun\Forms\Components\ToggleButtons;
use Xelqun\Forms\Conditions\When;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Callout;
use Xelqun\Forms\Layouts\EmptyState;
use Xelqun\Forms\Layouts\Fieldset;
use Xelqun\Forms\Layouts\Flex;
use Xelqun\Forms\Layouts\FusedGroup;
use Xelqun\Forms\Layouts\Grid;
use Xelqun\Forms\Layouts\Group;
use Xelqun\Forms\Layouts\Icon;
use Xelqun\Forms\Layouts\Image;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\Layouts\Tab;
use Xelqun\Forms\Layouts\Tabs;
use Xelqun\Forms\Layouts\Text;
use Xelqun\Forms\Layouts\UnorderedList;

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
    'date time picker' => fn () => DateTimePicker::make('starts_at')->seconds(false)->minutesStep(15)->displayFormat('d M Y H:i')->disabledDates(['2026-01-01']),
    'time picker' => fn () => TimePicker::make('opens_at')->minDate('09:00'),
    'text input with every option' => fn () => TextInput::make('code')->password()->revealable()->mask('99-99')->datalist(['a'])->copyable()->prefixIcon('mail')->suffixIcon('check'),
    'select with groups' => fn () => Select::make('status')->options(['Open' => ['a' => 'A']])->disableOptionWhen(fn (): bool => true)->noOptionsMessage('None'),
    'checkbox list' => fn () => CheckboxList::make('perks')->options(['a' => 'A', 'b' => 'B'])->descriptions(['a' => 'First'])->columns(2)->searchable()->bulkToggleable(),
    'toggle buttons' => fn () => ToggleButtons::make('status')->options(['a' => 'A'])->colors(['a' => 'success'])->icons(['a' => 'check'])->inline(),
    'tags input' => fn () => TagsInput::make('tags')->suggestions(['a'])->splitKeys(['Tab', ' '])->reorderable()->tagPrefix('#'),
    'slider' => fn () => Slider::make('volume')->range(0, 10)->step(2)->tooltips()->default([2, 6]),
    'key value' => fn () => KeyValue::make('meta')->default(['a' => '1'])->reorderable(),
    'color picker' => fn () => ColorPicker::make('tint')->rgba()->default('rgba(1, 2, 3, 0.5)'),
    'multiple file upload' => fn () => FileUpload::make('gallery')->multiple()->maxFiles(4)->reorderable()->downloadable()->openable(),
    'avatar' => fn () => FileUpload::make('photo')->avatar(),
    'group' => fn () => Group::make([TextInput::make('name')])->columns(2),
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
    'flex' => fn () => Flex::make([TextInput::make('a'), TextInput::make('b')->grow(false)])->from('md'),
    'fused group' => fn () => FusedGroup::make([TextInput::make('city')->placeholder('City'), TextInput::make('zip')])->label('Location')->columns(2),
    'callout' => fn () => Callout::make('Heads up')->description('Read this')->warning()->footer([Text::make('More')]),
    'empty state' => fn () => EmptyState::make('Nothing yet')->description('Add one')->icon('inbox')->contained(false),
    'text' => fn () => Text::make('Note')->color('danger')->size('lg')->weight('bold')->fontFamily('mono')->tooltip('Why'),
    'badge text' => fn () => Text::make('New')->badge()->icon('star'),
    'html text' => fn () => Text::make(new HtmlString('<strong>Bold</strong>')),
    'icon' => fn () => Icon::make('star')->color('warning')->size('lg')->tooltip('Starred'),
    'image' => fn () => Image::make('/logo.png', 'Logo')->imageSize(40)->alignCenter(),
    'unordered list' => fn () => UnorderedList::make(['One', Text::make('Two')])->size('xs'),
    'field with slots' => fn () => TextInput::make('name')->afterLabel('Optional')->beforeContent(Icon::make('user'))->belowErrorMessage([Text::make('Help')]),
    'toggle with slots' => fn () => Toggle::make('active')->aboveContent('Above')->belowContent('Below'),
    'repeater' => fn () => Repeater::make('items')->itemLabel('Line')->columns(2)->minItems(1)->maxItems(3)->default([['qty' => 2]])->schema([
        TextInput::make('qty')->numeric()->default(1),
        TextInput::make('subtotal')->computed('qty * 2'),
    ]),
]);
