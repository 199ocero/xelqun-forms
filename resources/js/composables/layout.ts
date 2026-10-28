import type { ColumnSpan } from '../schema/types';

const BREAKPOINTS = ['default', 'sm', 'md', 'lg', 'xl'] as const;

function suffix(breakpoint: string): string {
    return breakpoint === 'default' ? '' : `-${breakpoint}`;
}

/**
 * CSS variables for a responsive grid, read by the .xq-grid class.
 */
export function gridStyle(
    columns: Partial<Record<string, number>> | null | undefined,
): Record<string, string> {
    const style: Record<string, string> = {};

    for (const breakpoint of BREAKPOINTS) {
        const count = columns?.[breakpoint];

        if (count) {
            style[`--xq-cols${suffix(breakpoint)}`] = String(count);
        }
    }

    return style;
}

/**
 * CSS variables for a cell's column span, read by the .xq-cell class.
 * A plain number applies from the "md" breakpoint, where grids gain columns.
 */
export function spanStyle(span: ColumnSpan | undefined): Record<string, string> {
    if (span === null || span === undefined) {
        return {};
    }

    const spans =
        typeof span === 'object'
            ? span
            : span === 'full'
              ? { default: 'full' as const }
              : { md: span };
    const style: Record<string, string> = {};

    for (const breakpoint of BREAKPOINTS) {
        const value = spans[breakpoint];

        if (value !== undefined) {
            style[`--xq-span${suffix(breakpoint)}`] =
                value === 'full' ? '1 / -1' : `span ${value} / span ${value}`;
        }
    }

    return style;
}
