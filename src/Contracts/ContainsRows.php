<?php

namespace Xelqun\Forms\Contracts;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;

/**
 * A field whose state is a set of rows, each with its own components. Extend
 * Repeater rather than implementing this: the browser also needs the row
 * props Repeater sends (template, rowPlaceholder, rows and newRow).
 *
 * @internal Extend Repeater instead.
 *
 * @phpstan-require-extends Field
 */
interface ContainsRows
{
    /**
     * The components every row is built from, with placeholders in their paths.
     *
     * @return array<int, Component>
     */
    public function getTemplateComponents(): array;

    /**
     * @return array<int, string>
     */
    public function getRowKeys(): array;

    /**
     * Fresh component instances for one row.
     *
     * @return array<int, Component>
     */
    public function getRowComponents(string $rowKey): array;

    /**
     * @return array<string, array<int, Component>>
     */
    public function getRows(): array;

    /**
     * Stands for this field's row key in its template.
     */
    public function getRowPlaceholder(): string;

    /**
     * Default state for a row the user adds in the browser.
     *
     * @return array<string, mixed>
     */
    public function getNewRowState(): array;

    /**
     * Singular name of one row, used in validation messages.
     */
    public function getItemLabel(): string;

    /**
     * Fix the rows the browser posted before anything reads them: row keys
     * become part of state paths, so an unsafe one must not survive this.
     */
    public function normalizePostedRows(): void;

    /**
     * The model the fields of a row read relationships from, or null when
     * rows are not models. Gets the model of the level above.
     */
    public function getRowModel(Model $parent): ?Model;
}
