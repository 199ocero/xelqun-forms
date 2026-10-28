import { isPlainObject } from './values';

export const ROOT = 'data';
export const ROW_PLACEHOLDER = '{row}';

/**
 * Read a value by dot path. Paths containing "*" return a flat list,
 * matching Xelqun\Forms\Support\StatePath::get().
 */
export function getPath(state: unknown, path: string): any {
    const segments = path.split('.');

    if (!segments.includes('*')) {
        let current: any = state;

        for (const segment of segments) {
            if (current === null || typeof current !== 'object') {
                return undefined;
            }

            current = current[segment];
        }

        return current;
    }

    return collect(state, segments);
}

function collect(value: unknown, segments: string[]): unknown[] {
    if (segments.length === 0) {
        return [value];
    }

    const [segment, ...rest] = segments;

    if (value === null || typeof value !== 'object') {
        return [];
    }

    if (segment === '*') {
        return Object.values(value as object).flatMap((item) => collect(item, rest));
    }

    if (!(segment in (value as object))) {
        return [];
    }

    return collect((value as Record<string, unknown>)[segment], rest);
}

/**
 * Write a value by dot path, creating objects along the way.
 */
export function setPath(state: Record<string, any>, path: string, value: unknown): void {
    const segments = path.split('.');
    const last = segments.pop()!;
    let current: any = state;

    for (const segment of segments) {
        if (current[segment] === null || typeof current[segment] !== 'object') {
            current[segment] = {};
        }

        current = current[segment];
    }

    current[last] = value;
}

export function deletePath(state: Record<string, any>, path: string): void {
    const segments = path.split('.');
    const last = segments.pop()!;
    const parent = getPath(state, segments.join('.'));

    if (parent !== null && typeof parent === 'object') {
        delete parent[last];
    }
}

/**
 * Whether two paths touch the same state ("*" and "{row}" match any segment).
 */
export function overlaps(first: string, second: string): boolean {
    const a = first.split('.');
    const b = second.split('.');
    const length = Math.min(a.length, b.length);

    for (let index = 0; index < length; index++) {
        const left = a[index];
        const right = b[index];

        if (
            left !== right &&
            left !== '*' &&
            right !== '*' &&
            left !== ROW_PLACEHOLDER &&
            right !== ROW_PLACEHOLDER
        ) {
            return false;
        }
    }

    return true;
}

export function startsWithPath(path: string, prefix: string): boolean {
    return path === prefix || path.startsWith(`${prefix}.`);
}

/**
 * Deep-copy a value and replace every "{row}" placeholder in its strings.
 */
export function replaceRow<T>(value: T, rowKey: string): T {
    return JSON.parse(JSON.stringify(value).split(ROW_PLACEHOLDER).join(rowKey)) as T;
}

export function clone<T>(value: T): T {
    return value === undefined ? value : JSON.parse(JSON.stringify(value));
}

export { isPlainObject };
