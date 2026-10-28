/**
 * Value semantics shared with Xelqun\Forms\Support\Values (PHP).
 * Keep both files in sync; tests/Fixtures/conformance proves they agree.
 */
const NUMERIC_PATTERN = /^\s*[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?\s*$/;

export function isNumeric(value: unknown): boolean {
    if (typeof value === 'number') {
        return Number.isFinite(value);
    }

    return typeof value === 'string' && NUMERIC_PATTERN.test(value);
}

export function toNumber(value: unknown): number {
    if (typeof value === 'boolean') {
        return value ? 1 : 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string' && isNumeric(value)) {
        return Number(value.trim());
    }

    return 0;
}

export function isFilled(value: unknown): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    if (typeof value === 'string') {
        return value.trim() !== '';
    }

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    if (isPlainObject(value)) {
        return Object.keys(value).length > 0;
    }

    return true;
}

export function isTruthy(value: unknown): boolean {
    if (value === null || value === undefined || value === false || value === '') {
        return false;
    }

    if (Array.isArray(value)) {
        return value.length > 0;
    }

    if (isPlainObject(value)) {
        return Object.keys(value).length > 0;
    }

    if (typeof value === 'number') {
        return value !== 0 && !Number.isNaN(value);
    }

    return true;
}

export function toBoolean(value: unknown): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (isNumeric(value)) {
        return toNumber(value) !== 0;
    }

    if (typeof value === 'string') {
        return ['true', 'on', 'yes'].includes(value.trim().toLowerCase());
    }

    return isTruthy(value);
}

function isBlank(value: unknown): boolean {
    return value === null || value === undefined || value === '';
}

export function equals(left: unknown, right: unknown): boolean {
    if (isBlank(left) || isBlank(right)) {
        return isBlank(left) && isBlank(right);
    }

    if (typeof left === 'boolean' || typeof right === 'boolean') {
        return toBoolean(left) === toBoolean(right);
    }

    if (isNumeric(left) && isNumeric(right)) {
        return toNumber(left) === toNumber(right);
    }

    if (isCollection(left) || isCollection(right)) {
        return (
            isCollection(left) &&
            isCollection(right) &&
            JSON.stringify(left) === JSON.stringify(right)
        );
    }

    return toText(left) === toText(right);
}

/**
 * Returns -1, 0 or 1, or null when the values cannot be compared.
 */
export function compare(left: unknown, right: unknown): -1 | 0 | 1 | null {
    if (!isFilled(left) || !isFilled(right) || isCollection(left) || isCollection(right)) {
        return null;
    }

    const [a, b] =
        isNumeric(left) && isNumeric(right)
            ? [toNumber(left), toNumber(right)]
            : [toText(left), toText(right)];

    return a < b ? -1 : a > b ? 1 : 0;
}

export function inList(value: unknown, list: unknown[]): boolean {
    if (Array.isArray(value)) {
        return value.some((item) => inList(item, list));
    }

    return list.some((candidate) => equals(value, candidate));
}

export function toText(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (typeof value === 'number' || typeof value === 'string') {
        return String(value);
    }

    return JSON.stringify(value);
}

export function isPlainObject(value: unknown): value is Record<string, any> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isCollection(value: unknown): boolean {
    return Array.isArray(value) || isPlainObject(value);
}
