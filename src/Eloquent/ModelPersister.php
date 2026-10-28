<?php

namespace Xelqun\Forms\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Form;

/**
 * Writes validated form data to a model and its relationships.
 */
final class ModelPersister
{
    /**
     * @template TModel of Model
     *
     * @param  Form<TModel>  $form
     * @param  array<string, mixed>  $data  Validated, row-keyed data.
     * @return TModel
     */
    public function save(Form $form, array $data): Model
    {
        return DB::transaction(function () use ($form, $data): Model {
            $record = $form->getRecord() ?? $form->getModelInstance();
            $relationships = [];
            $attributes = [];

            foreach ($form->getComponents() as $component) {
                $this->collectTopLevel($component, $data, $attributes, $relationships);
            }

            $record->forceFill($form->mutateBeforeSave($attributes))->save();

            foreach ($relationships as $repeater) {
                $this->syncHasMany($record, $repeater, (array) ($data[$repeater->getName()] ?? []));
            }

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Repeater>  $relationships
     */
    private function collectTopLevel(Component $component, array $data, array &$attributes, array &$relationships): void
    {
        if ($component instanceof Repeater && $component->getRelationshipName() !== null) {
            if (array_key_exists($component->getName(), $data)) {
                $relationships[] = $component;
            }

            return;
        }

        if ($component instanceof Field) {
            if (array_key_exists($component->getName(), $data)) {
                $value = $data[$component->getName()];
                $attributes[$component->getName()] = $component instanceof Repeater ? array_values((array) $value) : $value;
            }

            return;
        }

        foreach ($component->getChildComponents() as $child) {
            $this->collectTopLevel($child, $data, $attributes, $relationships);
        }
    }

    /**
     * Create, update and delete related rows. Rows keyed "record-{id}" update
     * an existing related model, but only one that belongs to this record.
     *
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function syncHasMany(Model $record, Repeater $repeater, array $rows): void
    {
        $relationship = $repeater->getRelationshipInstance($record);
        $existing = $relationship->get()->keyBy(fn (Model $model): string => (string) $model->getKey());
        $kept = [];
        $order = 0;

        foreach ($rows as $rowKey => $row) {
            $attributes = array_filter(
                (array) $row,
                fn (string $key): bool => $this->isPlainAttribute($repeater, $key),
                ARRAY_FILTER_USE_KEY,
            );

            if ($repeater->getOrderColumn() !== null) {
                $attributes[$repeater->getOrderColumn()] = $order++;
            }

            $recordKey = str_starts_with((string) $rowKey, Repeater::RECORD_KEY_PREFIX)
                ? substr((string) $rowKey, strlen(Repeater::RECORD_KEY_PREFIX))
                : null;

            if ($recordKey !== null && $existing->has($recordKey)) {
                $existing->get($recordKey)->forceFill($attributes)->save();
                $kept[] = $recordKey;

                continue;
            }

            $relationship->make()->forceFill($attributes)->save();
        }

        $existing->except($kept)->each(fn (Model $model) => $model->delete());
    }

    private function isPlainAttribute(Repeater $repeater, string $name): bool
    {
        $isPlain = false;

        Form::eachField($repeater->getTemplateComponents(), function (Field $field) use ($name, &$isPlain): void {
            if ($field->getName() === $name && ! $field instanceof Repeater) {
                $isPlain = true;
            }
        });

        return $isPlain;
    }
}
