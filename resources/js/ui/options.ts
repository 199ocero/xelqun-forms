import type { SelectOption } from '../schema/types';

/**
 * The most options a list draws at once. Thousands would freeze the page, and
 * nobody scrolls that far, so the rest are found by typing.
 */
export const OPTION_LIMIT = 100;

export interface OptionGroup {
    label: string | null;
    options: SelectOption[];
}

/**
 * Consecutive options of one group, in the order the server sent them. Options
 * with no group form a group with no label.
 */
export function groupOptions(options: SelectOption[]): OptionGroup[] {
    const groups: OptionGroup[] = [];

    for (const option of options) {
        const label = option.group ?? null;
        const last = groups[groups.length - 1];

        if (last && last.label === label) {
            last.options.push(option);
        } else {
            groups.push({ label, options: [option] });
        }
    }

    return groups;
}

/**
 * The list with one item moved, or the same list when it can't move that way.
 */
export function moveItem<T>(items: T[], item: T, offset: number): T[] {
    const from = items.indexOf(item);
    const to = from + offset;

    if (from === -1 || to < 0 || to >= items.length) {
        return items;
    }

    const moved = [...items];

    moved.splice(from, 1);
    moved.splice(to, 0, item);

    return moved;
}
