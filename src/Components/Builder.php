<?php

namespace Xelqun\Forms\Components;

use Closure;
use LogicException;
use stdClass;
use Xelqun\Forms\Components\Builder\Block;
use Xelqun\Forms\Form;

/**
 * Rows of different kinds, chosen by the user. Each row is {type, data}, and the
 * saved value is a list of those, so a JSON column with an array cast holds it.
 * Only a repeater's rows in one column work here, not relationship().
 *
 * @api
 */
class Builder extends Repeater
{
    protected bool|Closure $hasBlockPreviews = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blocks([]);
    }

    public function getType(): string
    {
        return 'builder';
    }

    public function schema(array $components): static
    {
        throw new LogicException('A Builder takes its fields through blocks().');
    }

    /**
     * The blocks to choose from. A string key groups blocks under that heading.
     *
     * @param  array<int|string, Block|array<int, Block>>  $blocks
     */
    public function blocks(array $blocks): static
    {
        $flat = [];

        foreach ($blocks as $group => $items) {
            foreach (is_array($items) ? $items : [$items] as $block) {
                $flat[] = is_string($group) ? $block->inGroup($group) : $block;
            }
        }

        return parent::schema([Hidden::make('type'), ...$flat]);
    }

    /**
     * Show each block's preview(), with a button to edit it.
     */
    public function blockPreviews(bool|Closure $condition = true): static
    {
        $this->hasBlockPreviews = $condition;

        return $this;
    }

    public function hasBlockPreviews(): bool
    {
        return (bool) $this->evaluate($this->hasBlockPreviews);
    }

    public function relationship(?string $name = null, ?string $orderColumn = null): static
    {
        throw new LogicException('A Builder keeps its rows in one column, so it has no relationship().');
    }

    /**
     * @return array<int, Block>
     */
    public function getBlocks(): array
    {
        return array_values(array_filter($this->childComponents, fn (Component $component): bool => $component instanceof Block));
    }

    public function getBlock(string $name): ?Block
    {
        foreach ($this->getBlocks() as $block) {
            if ($block->getName() === $name) {
                return $block;
            }
        }

        return null;
    }

    public function mount(Form $form, ?Component $parent, string $container, string $position, ?string $containerPattern = null): static
    {
        parent::mount($form, $parent, $container, $position, $containerPattern);

        foreach ($this->getBlocks() as $block) {
            $block->showsPreview($this->hasBlockPreviews());
            $form->ensureUniqueStatePaths($block->getChildComponents());
        }

        return $this;
    }

    /**
     * Blocks may reuse a name between them, so mount() checks each on its own.
     */
    public function getTemplateComponents(): array
    {
        return [];
    }

    /**
     * Keeps array keys, because a row's nodes need the same keys as its template.
     */
    protected function getRowTemplates(string $rowKey): array
    {
        $type = $this->getForm()->getStateValue("{$this->getStatePath()}.{$rowKey}.type");

        return array_filter(
            $this->childComponents,
            fn (Component $component): bool => ! $component instanceof Block || $component->getName() === $type,
        );
    }

    protected function makeEmptyRow(mixed $rowSource): array
    {
        return ['type' => is_array($rowSource) ? ($rowSource['type'] ?? null) : null, 'data' => []];
    }

    public function toPlainRows(array $rows): array
    {
        return array_map(function (mixed $row): mixed {
            $block = is_string($row['type'] ?? null) ? $this->getBlock($row['type']) : null;

            return ['data' => [], ...$this->toPlainRow($row, $block === null ? [] : [$block])];
        }, array_values($rows));
    }

    /**
     * A block the server hides for this user keeps its stored rows: the browser
     * never saw their values, so they can only go out as the server has them.
     */
    public function getOutputRows(): array
    {
        return [...parent::getOutputRows(), ...$this->getHiddenStoredRows()];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getHiddenStoredRows(): array
    {
        $filled = (array) $this->getForm()->getInitialValue($this->getStatePath());
        $rows = [];

        foreach ((array) $this->getState() as $key => $row) {
            $type = is_array($row) ? ($row['type'] ?? null) : null;

            if (is_string($type) && $this->getBlock($type)?->isHiddenOnServer() && ($filled[$key]['type'] ?? null) === $type) {
                $rows[$key] = $filled[$key];
            }
        }

        return $rows;
    }

    public function getNewRowState(): array
    {
        return [];
    }

    /**
     * @return array{type: string, data: object}
     */
    private function getNewBlockState(Block $block): array
    {
        return ['type' => $block->getName(), 'data' => (object) ($this->getNewStateOf([$block])['data'] ?? [])];
    }

    public function getValidationRules(): array
    {
        return [...parent::getValidationRules(), function (string $attribute, mixed $value, Closure $fail): void {
            $counts = [];
            $stored = $this->getHiddenStoredRows();

            foreach ((array) $this->getState() as $key => $row) {
                $type = is_array($row) ? ($row['type'] ?? null) : null;
                $block = is_string($type) ? $this->getBlock($type) : null;

                if ($block === null || ($block->isHiddenOnServer() && ! isset($stored[$key]))) {
                    $fail(__('xelqun-forms::messages.builder.invalid_block', ['attribute' => $this->getValidationAttribute()]));

                    return;
                }

                $counts[$type] = ($counts[$type] ?? 0) + 1;

                if ($counts[$type] > ($block->getMaxItems() ?? PHP_INT_MAX)) {
                    $fail(__('xelqun-forms::messages.builder.too_many', ['block' => $block->getLabel(), 'max' => $block->getMaxItems()]));

                    return;
                }
            }
        }];
    }

    /**
     * @return array<int, mixed>
     */
    private function getBlockOptions(): array
    {
        $blocks = [];
        $type = $this->childComponents[0]->toNode();

        foreach ($this->getBlocks() as $block) {
            $node = $block->toNode();

            if ($node === null || $node['hidden']) {
                continue;
            }

            $blocks[] = [
                'name' => $block->getName(),
                'label' => $block->getLabel(),
                'icon' => $block->getIcon(),
                'group' => $block->getGroup(),
                'maxItems' => $block->getMaxItems(),
                'template' => [$type, $node, ...array_map(fn (Component $action): ?array => $action->toNode(), $this->itemActions)],
                'newRow' => $this->getNewBlockState($block),
            ];
        }

        return $blocks;
    }

    protected function getProps(): array
    {
        return [
            ...parent::getProps(),
            'template' => [],
            'newRow' => new stdClass,
            'blocks' => $this->getBlockOptions(),
            'previews' => $this->hasBlockPreviews(),
        ];
    }
}
