<?php

use Acme\Reviews\Rating;
use Acme\Reviews\Receipt;
use Acme\Reviews\Reviewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\Select;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Exceptions\FormDefinitionException;
use Xelqun\Forms\Form;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Forms\Tests\Fixtures\CarelessUploadForm;
use Xelqun\Forms\Tests\Fixtures\ChecklistField;
use Xelqun\Forms\Tests\Fixtures\ChecklistForm;
use Xelqun\Forms\Tests\Fixtures\NestedRelationshipForm;
use Xelqun\Forms\Tests\Fixtures\Order;
use Xelqun\Forms\Tests\Fixtures\OrderForm;
use Xelqun\Forms\Tests\Fixtures\PlainRatingForm;
use Xelqun\Forms\Tests\Fixtures\ReviewForm;
use Xelqun\Forms\Tests\Fixtures\SharedTokenForm;
use Xelqun\Forms\Tests\Fixtures\ThrowingPersistForm;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    ReviewForm::$hideRating = false;
});

afterEach(function (): void {
    Component::flushConfigurations();
    Component::flushMacros();
});

function receipt(): UploadedFile
{
    return UploadedFile::fake()->image('desk.png');
}

describe('search', function (): void {
    it('reaches a plugin field through the same endpoint as Select', function (): void {
        $this->searchForm(ReviewForm::make(), 'data.type', 'ada')
            ->assertOk()
            ->assertJsonPath('options', [['value' => 'ada', 'label' => 'Ada Lovelace']]);
    });

    it('reaches a plugin field inside a row of a plugin row field', function (): void {
        $this->searchForm(ChecklistForm::make(), 'data.steps.row-0.owner', 'ada')
            ->assertOk()
            ->assertJsonPath('options.0.value', 'ada');
    });

    it('does not treat a wildcard as a row', function (): void {
        $this->searchForm(ChecklistForm::make(), 'data.steps.*.owner', 'ada')->assertNotFound();
    });

    it('still refuses a field with no search contract', function (): void {
        $this->searchForm(ReviewForm::make(), 'data.customer', 'ada')->assertNotFound();
    });

    it('refuses a plugin field that is hidden or disabled', function (Closure $restrict): void {
        $form = new class extends Form
        {
            public static Closure $restrict;

            public function schema(): array
            {
                return [(self::$restrict)(Reviewer::make('person')->options(['ada' => 'Ada']))];
            }
        };
        $form::$restrict = $restrict;

        $this->searchForm($form::make(), 'data.person', 'ada')->assertNotFound();
    })->with([
        'hidden' => [fn (Field $field): Field => $field->hidden()],
        'disabled' => [fn (Field $field): Field => $field->disabled()],
    ]);
});

describe('uploads', function (): void {
    it('takes an upload for a plugin field and stores it on save', function (): void {
        $token = $this->uploadToForm(ReviewForm::make(), 'data.company', receipt())->assertOk()->json('token');

        $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'company' => $token]]);

        expect($order->company)->not->toBe($token);
        Storage::disk('public')->assertExists($order->company);
    });

    it('uses the upload rules of the plugin field', function (): void {
        $this->uploadToForm(ReviewForm::make(), 'data.company', UploadedFile::fake()->create('notes.txt', 1, 'text/plain'))
            ->assertUnprocessable();
    });

    it('still refuses uploads for a field with no upload contract', function (): void {
        $this->uploadToForm(ReviewForm::make(), 'data.customer', receipt())->assertUnprocessable();
    });

    it('refuses a token made for a different plugin field', function (): void {
        $token = $this->uploadToForm(ReviewForm::make(), 'data.items.a.attachment', receipt())->json('token');

        expect(fn () => ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'company' => $token]]))
            ->toThrow(ValidationException::class);
    });

    it('refuses a path that was never stored on the record', function (): void {
        expect(fn () => ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'company' => '../../.env']]))
            ->toThrow(ValidationException::class);
    });

    it('refuses a foreign token even when a plugin validates nothing', function (): void {
        $token = $this->uploadToForm(OrderForm::make(), 'data.items.a.attachment', receipt())->json('token');

        expect(fn () => CarelessUploadForm::make()->validate(['data' => ['file' => $token]]))
            ->toThrow(ValidationException::class);

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('refuses a token posted to the field it was made for and to a careless field', function (): void {
        $token = $this->uploadToForm(SharedTokenForm::make(), 'data.first', receipt())->json('token');

        expect(fn () => SharedTokenForm::make()->validate(['data' => ['first' => $token, 'second' => $token]]))
            ->toThrow(ValidationException::class);
    });

    it('promotes uploads of a plugin field inside a plugin row field', function (): void {
        $token = $this->uploadToForm(ChecklistForm::make(), 'data.steps.a.proof', receipt())->json('token');

        $data = ChecklistForm::make()->validate(['data' => ['steps' => ['a' => ['title' => 'Check', 'proof' => $token]]]]);

        Storage::disk('public')->assertExists($data['steps'][0]['proof']);
    });
});

describe('persistence', function (): void {
    it('lets a plugin field save its own data', function (): void {
        $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'stars' => 4]]);

        expect($order->review->stars)->toBe(4)
            ->and(array_key_exists('stars', $order->getAttributes()))->toBeFalse();
    });

    it('reads it back when the form is filled', function (): void {
        $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'stars' => 4]]);

        expect(ReviewForm::make($order->fresh())->getData()['stars'])->toBe(4);
    });

    it('does not call it for a field the server hides', function (): void {
        $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'stars' => 4]]);
        ReviewForm::$hideRating = true;

        ReviewForm::make($order->fresh())->save(['data' => ['customer' => 'Grace', 'stars' => 1]]);

        expect($order->fresh()->review->stars)->toBe(4);
    });

    it('calls it with the row model for a field in a relationship row', function (): void {
        $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'items' => [
            'a' => ['product' => 'Desk', 'qty' => 1, 'price' => 2, 'stars' => 3],
        ]]]);

        $item = $order->items()->sole();

        expect($item->review->stars)->toBe(3)
            ->and($item->product)->toBe('Desk');
    });

    it('leaves the related data of a row alone when its value was not posted', function (): void {
        $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'items' => [
            'a' => ['product' => 'Desk', 'qty' => 1, 'price' => 2, 'stars' => 3],
        ]]]);
        $item = $order->items()->sole();

        ReviewForm::make($order->fresh())->save(['data' => ['customer' => 'Ada', 'items' => [
            'record-'.$item->id => ['product' => 'Chair', 'qty' => 1, 'price' => 2],
        ]]]);

        expect($item->fresh()->product)->toBe('Chair')
            ->and($item->fresh()->review->stars)->toBe(3);
    });

    it('saves a field that declines as a plain column', function (): void {
        $order = PlainRatingForm::make()->save(['data' => ['customer' => 'Ada', 'total' => 3]]);

        expect((int) $order->fresh()->total)->toBe(3);
    });

    it('rolls the record back when a plugin fails to save', function (): void {
        expect(fn () => ThrowingPersistForm::make()->save(['data' => ['customer' => 'Ada', 'note' => 'x']]))
            ->toThrow(RuntimeException::class, 'The plugin failed.');

        expect(Order::query()->count())->toBe(0);
    });

    it('does not save the rows of a nested relationship repeater yet', function (): void {
        $order = NestedRelationshipForm::make()->save(['data' => ['customer' => 'Ada', 'items' => [
            'a' => ['product' => 'Desk', 'qty' => 1, 'price' => 2, 'reviews' => ['x' => ['stars' => '2']]],
        ]]]);

        expect($order->items()->sole()->reviews()->count())->toBe(0);
    });

    it('persists a repeater itself only when it has a relationship', function (): void {
        $repeater = Repeater::make('items');

        expect($repeater->shouldPersistItself())->toBeFalse()
            ->and($repeater->relationship()->shouldPersistItself())->toBeTrue();
    });
});

describe('row fields', function (): void {
    it('expands the rows of a plugin row field', function (): void {
        $form = ChecklistForm::make();

        expect($this->formNode($form, 'data.steps')['containsRows'])->toBeTrue()
            ->and($this->formNode($form, 'data.steps.row-0.title')['type'])->toBe('text-input');
    });

    it('sends no row flag for fields without rows', function (): void {
        expect($this->formNode(ReviewForm::make(), 'data.customer'))->not->toHaveKey('containsRows');
    });

    it('validates and flattens its rows', function (): void {
        $data = ChecklistForm::make()->validate(['data' => ['steps' => ['a' => ['title' => 'One']]]]);

        expect($data['steps'])->toBe([['title' => 'One']]);
    });

    it('drops rows whose keys could reach other rows', function (): void {
        $form = ChecklistForm::make();

        $form->setData(['steps' => ['a.b' => ['title' => 'x'], 'ok' => ['title' => 'y']]]);

        expect(array_keys($form->getData()['steps']))->toBe(['ok']);
    });

    it('gives a nested row field its own placeholder', function (): void {
        $form = new class extends Form
        {
            public function schema(): array
            {
                return [ChecklistField::make('outer')->schema([ChecklistField::make('inner')->schema([TextInput::make('a')])])];
            }
        };

        $schema = $form::make()->toSchema();

        expect($schema['components'][0]['props']->rowPlaceholder)->toBe('{row}')
            ->and($schema['components'][0]['props']->template[0]['props']->rowPlaceholder)->toBe('{row:1}');
    });
});

describe('forms that cannot reach the server', function (): void {
    it('rejects an anonymous form with a server-only field', function (Field $field): void {
        expect(fn () => Form::make([$field])->toSchema())->toThrow(FormDefinitionException::class);
    })->with([
        'plugin search' => fn () => Reviewer::make('person'),
        'plugin upload' => fn () => Receipt::make('receipt'),
        'a field that says so' => fn () => new class('thing') extends Field
        {
            public function getType(): string
            {
                return 'acme/thing';
            }

            public function needsServer(): bool
            {
                return true;
            }
        },
    ]);

    it('allows a plugin field that needs nothing from the server', function (): void {
        expect(Form::make([Rating::make('stars')])->toSchema()['token'])->toBeNull();
    });

    it('allows a local search that needs no server', function (): void {
        expect(Form::make([Select::make('a')->options(['x' => 'X'])->searchable()])->toSchema()['token'])->toBeNull();
    });
});

describe('validation and translation', function (): void {
    it('runs the rule of the plugin with its translated message', function (): void {
        try {
            ReviewForm::make()->validate(['data' => ['customer' => 'Ada', 'stars' => 9]]);

            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            expect($exception->errors()['data.stars'])->toBe(['Stars must be between 1 and 5 stars.']);
        }
    });

    it('loads the translations of a plugin', function (): void {
        Lang::addLines(['reviews.receipt_caption' => 'Magdagdag ng litrato.'], 'fil', 'acme-reviews');
        app()->setLocale('fil');

        expect($this->formNode(ReviewForm::make(), 'data.company')['props']->caption)->toBe('Magdagdag ng litrato.');
    });

    it('loads the translations of the package', function (): void {
        Lang::addLines(['messages.repeater.item' => 'Linya', 'messages.repeater.add' => 'Idagdag ang :item'], 'fil', 'xelqun-forms');
        app()->setLocale('fil');

        $props = $this->formNode(ChecklistForm::make(), 'data.steps')['props'];

        expect($props->itemLabel)->toBe('Linya')
            ->and($props->addLabel)->toBe('Idagdag ang linya');
    });

    it('keeps the English text of the package', function (): void {
        $props = $this->formNode(ChecklistForm::make(), 'data.steps')['props'];

        expect($props->itemLabel)->toBe('Item')
            ->and($props->addLabel)->toBe('Add item')
            ->and(__('xelqun-forms::messages.uploads.not_accepted'))->toBe('This field does not accept uploads.');
    });
});

describe('configureUsing', function (): void {
    it('configures every new instance of a class', function (): void {
        TextInput::configureUsing(fn (TextInput $input) => $input->maxLength(10));

        expect($this->formNode(Form::make([TextInput::make('a')]), 'data.a')['props']->maxLength)->toBe(10);
    });

    it('applies to subclasses, and not to siblings', function (): void {
        Field::configureUsing(fn (Field $field) => $field->hint('From Field'));
        TextInput::configureUsing(fn (TextInput $input) => $input->maxLength(10));

        $node = fn (Field $field): array => $this->formNode(Form::make([$field]), 'data.'.$field->getName());

        expect($node(TextInput::make('a'))['hint'])->toBe('From Field')
            ->and($node(TextInput::make('a'))['props']->maxLength)->toBe(10)
            ->and($node(Rating::make('b'))['hint'])->toBe('From Field')
            ->and($node(Rating::make('b'))['props'])->not->toHaveProperty('maxLength');
    });

    it('runs after setUp, and the caller still has the last word', function (): void {
        Field::configureUsing(fn (Field $field) => $field->hint('Configured'));

        $form = Form::make([TextInput::make('a')->hint('Mine')]);

        expect($this->formNode($form, 'data.a')['hint'])->toBe('Mine');
    });

    it('configures layouts', function (): void {
        Section::configureUsing(fn (Section $section) => $section->description('Configured'));

        expect(Form::make([Section::make('A')])->toSchema()['components'][0]['props']->description)->toBe('Configured');
    });

    it('does not configure rows a second time', function (): void {
        $calls = 0;
        TextInput::configureUsing(function () use (&$calls): void {
            $calls++;
        });

        $form = ChecklistForm::make();
        $form->toSchema();

        expect($calls)->toBe(1);
    });
});

describe('macros', function (): void {
    it('adds a method to existing fields', function (): void {
        Field::macro('shout', fn (): static => $this->hint('LOUD'));

        $form = Form::make([TextInput::make('a')->shout()]);

        expect($this->formNode($form, 'data.a')['hint'])->toBe('LOUD');
    });

});

describe('test helpers', function (): void {
    it('checks a field type is vendor-prefixed', function (): void {
        $this->assertNamespacedField(ReviewForm::make(), 'data.stars');

        expect(fn () => $this->assertNamespacedField(ReviewForm::make(), 'data.customer'))
            ->toThrow(AssertionFailedError::class);
    });

    it('fails clearly for a node that does not exist', function (): void {
        expect(fn () => $this->formNode(ReviewForm::make(), 'data.nope'))
            ->toThrow(AssertionFailedError::class);
    });

    it('sends live updates', function (): void {
        $this->updateForm(OrderForm::make(), ['data.customer'], ['customer' => 'Ada'])
            ->assertOk()
            ->assertJsonPath('$xq', 1);
    });
});

it('still gives plain Eloquent save behaviour to fields without the contract', function (): void {
    $order = ReviewForm::make()->save(['data' => ['customer' => 'Ada', 'type' => 'ada']]);

    expect($order)->toBeInstanceOf(Model::class)
        ->and($order->type)->toBe('ada');
});
