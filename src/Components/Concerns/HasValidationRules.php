<?php

namespace Xelqun\Forms\Components\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnitEnum;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Support\StatePath;

/**
 * Named helpers for Laravel's validation rules. They add to rules(), so a field
 * can mix both.
 *
 * Rules that name another field take a name relative to this field's row or
 * group, or $.name for the form root, the same as $get().
 *
 * @api
 */
trait HasValidationRules
{
    /** @var array<int, Closure(Field): mixed> Rule builders, given the field they run on. */
    protected array $ruleMakers = [];

    /**
     * Add one rule, optionally only while a condition holds. Unlike rules(), a
     * regex here may contain "|".
     */
    public function rule(mixed $rule, bool|Closure $condition = true): static
    {
        return $this->ruleUsing(static fn (Field $field): mixed => $field->evaluate($condition) ? $rule : null);
    }

    /**
     * Build a rule on the field it runs on. Rows are clones of a template, so a
     * maker must use the field it is given, never $this.
     *
     * @param  Closure(Field): mixed  $maker  Returns the rule, or null for none.
     */
    protected function ruleUsing(Closure $maker): static
    {
        $this->ruleMakers[] = $maker;

        return $this;
    }

    /**
     * rules() followed by the helpers.
     *
     * @return array<int, mixed>
     */
    protected function getCustomRules(): array
    {
        $rules = $this->rules;

        foreach ($this->ruleMakers as $maker) {
            $rule = $maker($this);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param  string|array<int, string>|Closure  $fields  A comma-separated string or a list.
     * @param  array<int, mixed>  $values  Plain rule arguments after the fields.
     */
    private function fieldRule(string $rule, string|array|Closure $fields, array $values = []): static
    {
        return $this->ruleUsing(static function (Field $field) use ($rule, $fields, $values): array {
            $fields = $field->evaluate($fields);
            $names = is_string($fields) ? array_map(trim(...), explode(',', $fields)) : $fields;

            return [
                $rule,
                ...array_map(static fn (string $name): string => is_numeric($name) ? $name : StatePath::resolve($name, $field->getContainer()), $names),
                ...array_map(static fn (mixed $value): mixed => self::toRuleValue($field->evaluate($value)), $values),
            ];
        });
    }

    /**
     * Laravel casts these rule arguments to strings, so true would become "1" and
     * never match a checkbox. It reads "true", "false" and "null" back itself.
     */
    private static function toRuleValue(mixed $value): mixed
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            default => $value,
        };
    }

    /**
     * Like Laravel, a value PHP can read as a date is a date, and anything else
     * is a field. Write $.name for a field named like a date, such as "today".
     * A closure that gives no date turns the rule off.
     */
    private function dateRule(string $rule, string|Closure $date): static
    {
        return $this->ruleUsing(static function (Field $field) use ($rule, $date): ?array {
            $date = $field->evaluate($date);

            if (blank($date)) {
                return null;
            }

            return [$rule, strtotime($date) === false ? StatePath::resolve($date, $field->getContainer()) : $date];
        });
    }

    /**
     * @param  array<int, mixed>|string|Closure  $values
     */
    private function listRule(string $rule, array|string|Closure $values): static
    {
        return $this->ruleUsing(static fn (Field $field): array => [$rule, ...array_values((array) $field->evaluate($values))]);
    }

    public function after(string|Closure $date): static
    {
        return $this->dateRule('after', $date);
    }

    public function afterOrEqual(string|Closure $date): static
    {
        return $this->dateRule('after_or_equal', $date);
    }

    public function before(string|Closure $date): static
    {
        return $this->dateRule('before', $date);
    }

    public function beforeOrEqual(string|Closure $date): static
    {
        return $this->dateRule('before_or_equal', $date);
    }

    public function different(string|Closure $field): static
    {
        return $this->fieldRule('different', $field);
    }

    public function gt(string|Closure $field): static
    {
        return $this->fieldRule('gt', $field);
    }

    public function gte(string|Closure $field): static
    {
        return $this->fieldRule('gte', $field);
    }

    public function lt(string|Closure $field): static
    {
        return $this->fieldRule('lt', $field);
    }

    public function lte(string|Closure $field): static
    {
        return $this->fieldRule('lte', $field);
    }

    public function same(string|Closure $field): static
    {
        return $this->fieldRule('same', $field);
    }

    /**
     * Needs a field named {name}_confirmation next to this one. Give that field
     * saved(false) when the form saves to a model.
     */
    public function confirmed(bool|Closure $condition = true): static
    {
        return $this->rule('confirmed', $condition);
    }

    /**
     * @param  string|array<int, string>|Closure  $fields
     */
    public function prohibits(string|array|Closure $fields): static
    {
        return $this->fieldRule('prohibits', $fields);
    }

    public function prohibitedIf(string|Closure $field, mixed $value): static
    {
        return $this->fieldRule('prohibited_if', $field, [$value]);
    }

    public function prohibitedUnless(string|Closure $field, mixed $value): static
    {
        return $this->fieldRule('prohibited_unless', $field, [$value]);
    }

    public function requiredIf(string|Closure $field, mixed $value): static
    {
        return $this->fieldRule('required_if', $field, [$value]);
    }

    public function requiredIfAccepted(string|Closure $field): static
    {
        return $this->fieldRule('required_if_accepted', $field);
    }

    public function requiredUnless(string|Closure $field, mixed $value): static
    {
        return $this->fieldRule('required_unless', $field, [$value]);
    }

    /**
     * @param  string|array<int, string>|Closure  $fields
     */
    public function requiredWith(string|array|Closure $fields): static
    {
        return $this->fieldRule('required_with', $fields);
    }

    /**
     * @param  string|array<int, string>|Closure  $fields
     */
    public function requiredWithAll(string|array|Closure $fields): static
    {
        return $this->fieldRule('required_with_all', $fields);
    }

    /**
     * @param  string|array<int, string>|Closure  $fields
     */
    public function requiredWithout(string|array|Closure $fields): static
    {
        return $this->fieldRule('required_without', $fields);
    }

    /**
     * @param  string|array<int, string>|Closure  $fields
     */
    public function requiredWithoutAll(string|array|Closure $fields): static
    {
        return $this->fieldRule('required_without_all', $fields);
    }

    /**
     * @param  array<int, mixed>|string|Closure  $values
     */
    public function startsWith(array|string|Closure $values): static
    {
        return $this->listRule('starts_with', $values);
    }

    /**
     * @param  array<int, mixed>|string|Closure  $values
     */
    public function endsWith(array|string|Closure $values): static
    {
        return $this->listRule('ends_with', $values);
    }

    /**
     * @param  array<int, mixed>|string|Closure  $values
     */
    public function doesntStartWith(array|string|Closure $values): static
    {
        return $this->listRule('doesnt_start_with', $values);
    }

    /**
     * @param  array<int, mixed>|string|Closure  $values
     */
    public function doesntEndWith(array|string|Closure $values): static
    {
        return $this->listRule('doesnt_end_with', $values);
    }

    /**
     * @param  Arrayable<array-key, mixed>|array<int, mixed>|string|Closure  $values
     */
    public function in(Arrayable|array|string|Closure $values): static
    {
        return $this->ruleUsing(static fn (Field $field): mixed => Rule::in($field->evaluate($values)));
    }

    /**
     * @param  Arrayable<array-key, mixed>|array<int, mixed>|string|Closure  $values
     */
    public function notIn(Arrayable|array|string|Closure $values): static
    {
        return $this->ruleUsing(static fn (Field $field): mixed => Rule::notIn($field->evaluate($values)));
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function enum(string $enum): static
    {
        return $this->rule(Rule::enum($enum));
    }

    public function multipleOf(int|float|Closure $value): static
    {
        return $this->ruleUsing(static fn (Field $field): array => ['multiple_of', $field->evaluate($value)]);
    }

    public function regex(string|Closure $pattern): static
    {
        return $this->ruleUsing(static fn (Field $field): array => ['regex', $field->evaluate($pattern)]);
    }

    public function notRegex(string|Closure $pattern): static
    {
        return $this->ruleUsing(static fn (Field $field): array => ['not_regex', $field->evaluate($pattern)]);
    }

    public function activeUrl(bool|Closure $condition = true): static
    {
        return $this->rule('active_url', $condition);
    }

    public function alpha(bool|Closure $condition = true): static
    {
        return $this->rule('alpha', $condition);
    }

    public function alphaDash(bool|Closure $condition = true): static
    {
        return $this->rule('alpha_dash', $condition);
    }

    public function alphaNum(bool|Closure $condition = true): static
    {
        return $this->rule('alpha_num', $condition);
    }

    public function ascii(bool|Closure $condition = true): static
    {
        return $this->rule('ascii', $condition);
    }

    public function filled(bool|Closure $condition = true): static
    {
        return $this->rule('filled', $condition);
    }

    public function hexColor(bool|Closure $condition = true): static
    {
        return $this->rule('hex_color', $condition);
    }

    public function ip(bool|Closure $condition = true): static
    {
        return $this->rule('ip', $condition);
    }

    public function ipv4(bool|Closure $condition = true): static
    {
        return $this->rule('ipv4', $condition);
    }

    public function ipv6(bool|Closure $condition = true): static
    {
        return $this->rule('ipv6', $condition);
    }

    public function json(bool|Closure $condition = true): static
    {
        return $this->rule('json', $condition);
    }

    public function macAddress(bool|Closure $condition = true): static
    {
        return $this->rule('mac_address', $condition);
    }

    public function nullable(bool|Closure $condition = true): static
    {
        return $this->rule('nullable', $condition);
    }

    public function prohibited(bool|Closure $condition = true): static
    {
        return $this->rule('prohibited', $condition);
    }

    public function string(bool|Closure $condition = true): static
    {
        return $this->rule('string', $condition);
    }

    public function ulid(bool|Closure $condition = true): static
    {
        return $this->rule('ulid', $condition);
    }

    public function uuid(bool|Closure $condition = true): static
    {
        return $this->rule('uuid', $condition);
    }

    /**
     * The value must not be taken in a table. The table defaults to this field's
     * model and the column to its name. The record being edited is ignored if it
     * lives in that table, and in a row or related group that means only the
     * record it was filled from, so a forged row key can't exclude someone else's
     * row. On another table, use $ignorable to name the record to skip.
     *
     * Global scopes don't apply: use scopedUnique() for those. The check sees the
     * posted value, before dehydrateStateUsing().
     *
     * @param  string|Closure|null  $table  A table or model class.
     * @param  Closure|null  $modifyRuleUsing  Gets $rule, a Unique rule.
     */
    public function unique(string|Closure|null $table = null, string|Closure|null $column = null, Model|Closure|null $ignorable = null, bool $ignoreRecord = true, ?Closure $modifyRuleUsing = null): static
    {
        return $this->ruleUsing(static function (Field $field) use ($table, $column, $ignorable, $ignoreRecord, $modifyRuleUsing): mixed {
            $table = $field->getRuleTable($table);
            $rule = Rule::unique($table, $field->evaluate($column) ?? $field->getName());
            $ignored = $field->getIgnoredRecord($ignorable, $ignoreRecord, $table);

            if ($ignored !== null) {
                $rule->ignore($ignored);
            }

            return $modifyRuleUsing === null ? $rule : ($field->evaluate($modifyRuleUsing, ['rule' => $rule]) ?? $rule);
        });
    }

    /**
     * Like unique(), but through the model's query, so its global scopes apply.
     *
     * @param  class-string<Model>|Closure|null  $model
     * @param  Closure|null  $modifyQueryUsing  Gets $query.
     */
    public function scopedUnique(string|Closure|null $model = null, string|Closure|null $column = null, Model|Closure|null $ignorable = null, bool $ignoreRecord = true, ?Closure $modifyQueryUsing = null): static
    {
        return $this->ruleUsing(static fn (Field $field): Closure => function (string $attribute, mixed $value, Closure $fail) use ($field, $model, $column, $ignorable, $ignoreRecord, $modifyQueryUsing): void {
            $query = $field->getRuleQuery($model, $modifyQueryUsing);
            $ignored = $field->getIgnoredRecord($ignorable, $ignoreRecord, $query->getModel()::class);

            if ($ignored !== null) {
                $query->whereKeyNot($ignored->getKey());
            }

            if ($query->whereIn($field->evaluate($column) ?? $field->getName(), array_filter((array) $value, is_scalar(...)))->exists()) {
                $fail($field->getFailureMessage('unique'));
            }
        });
    }

    /**
     * The value must exist in a table. Global scopes don't apply: use
     * scopedExists() for those.
     *
     * @param  string|Closure|null  $table  A table or model class. Defaults to this field's model.
     * @param  Closure|null  $modifyRuleUsing  Gets $rule, an Exists rule.
     */
    public function exists(string|Closure|null $table = null, string|Closure|null $column = null, ?Closure $modifyRuleUsing = null): static
    {
        return $this->ruleUsing(static function (Field $field) use ($table, $column, $modifyRuleUsing): mixed {
            $rule = Rule::exists($field->getRuleTable($table), $field->evaluate($column) ?? $field->getName());

            return $modifyRuleUsing === null ? $rule : ($field->evaluate($modifyRuleUsing, ['rule' => $rule]) ?? $rule);
        });
    }

    /**
     * Like exists(), but through the model's query, so its global scopes apply.
     * On a field with several values, each one has to exist.
     *
     * @param  class-string<Model>|Closure|null  $model
     * @param  Closure|null  $modifyQueryUsing  Gets $query.
     */
    public function scopedExists(string|Closure|null $model = null, string|Closure|null $column = null, ?Closure $modifyQueryUsing = null): static
    {
        return $this->ruleUsing(static fn (Field $field): Closure => function (string $attribute, mixed $value, Closure $fail) use ($field, $model, $column, $modifyQueryUsing): void {
            $column = $field->evaluate($column) ?? $field->getName();
            $values = array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', (array) $value);
            $found = $field->getRuleQuery($model, $modifyQueryUsing)->whereIn($column, $values)->pluck($column)->map(strval(...))->all();

            if (array_diff($values, $found) !== []) {
                $fail($field->getFailureMessage('exists'));
            }
        });
    }

    /**
     * @internal
     */
    public function getRuleTable(string|Closure|null $table): string
    {
        return $this->evaluate($table) ?? $this->getForm()->getModelInstanceFor($this)::class;
    }

    /**
     * @internal
     *
     * @param  class-string<Model>|Closure|null  $model
     * @return Builder<Model>
     */
    public function getRuleQuery(string|Closure|null $model, ?Closure $modifyQueryUsing): Builder
    {
        $model = $this->evaluate($model) ?? $this->getForm()->getModelInstanceFor($this)::class;
        $query = (new $model)->newQuery();

        return $modifyQueryUsing === null ? $query : ($this->evaluate($modifyQueryUsing, ['query' => $query]) ?? $query);
    }

    /**
     * The record's id means nothing in another table, so it only counts in its own.
     *
     * @internal
     *
     * @param  string  $table  A table or model class.
     */
    public function getIgnoredRecord(Model|Closure|null $ignorable, bool $ignoreRecord, string $table): ?Model
    {
        $ignored = $this->evaluate($ignorable);

        if ($ignored !== null || ! $ignoreRecord) {
            return $ignored;
        }

        $record = $this->getForm()->getRecordFor($this);
        $tableName = is_subclass_of($table, Model::class) ? (new $table)->getTable() : Str::afterLast($table, '.');

        return $record?->getTable() === $tableName ? $record : null;
    }
}
