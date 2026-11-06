import type { ClientRule, FieldValidation } from '../schema/types';
import { isPlainObject } from './values';

/**
 * Laravel's rules, for the browser. Each one may let through what the server
 * rejects, and must never reject what the server accepts: the server has the
 * last word, and a wrong "no" here is a form nobody can submit. Keep
 * tests/Fixtures/conformance/validation.json passing on both sides.
 */

/** PHP's trim(). JavaScript's also takes spaces PHP treats as text. */
function phpTrim(text: string): string {
    const spaces = ' \t\n\r\0\x0B';
    let start = 0;
    let end = text.length;

    while (start < end && spaces.includes(text[start])) {
        start++;
    }

    while (end > start && spaces.includes(text[end - 1])) {
        end--;
    }

    return text.slice(start, end);
}

const length = (text: string) => Array.from(text).length;

function isBlank(value: unknown): boolean {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value === 'string') {
        return phpTrim(value) === '';
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return isPlainObject(value) && Object.keys(value).length === 0;
}

function isNumeric(text: string): boolean {
    return /^[ \t\n\r\v\f]*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?[ \t\n\r\v\f]*$/.test(text);
}

/**
 * An app may or may not trim strings before validating, so a length only fails
 * if it fails either way: the raw text for a minimum, the trimmed text for a maximum.
 */
function passesString(rule: ClientRule, text: string): boolean {
    const longest = length(text);
    const shortest = length(phpTrim(text));
    const limit = rule.value ?? 0;

    return rule.rule === 'min'
        ? longest >= limit
        : rule.rule === 'max'
          ? shortest <= limit
          : shortest <= limit && limit <= longest;
}

function passes(rule: ClientRule, value: unknown): boolean {
    const text = String(value);

    switch (rule.rule) {
        case 'accepted':
            return [true, 1, 'yes', 'on', '1', 'true'].includes(value as never);
        case 'email':
            // Anything else an address can look like is Laravel's call: quotes and spaces are valid.
            return /.@./s.test(text);
        case 'numeric':
            return isNumeric(text);
        case 'integer':
            return /^[ \t\n\r\v\f]*[+-]?\d+[ \t\n\r\v\f]*$/.test(text);
        case 'size':
            return passesString(rule, text);
        case 'min':
        case 'max':
            if (rule.type === 'string') {
                return passesString(rule, text);
            }

            if (!isNumeric(text)) {
                return true;
            }

            return rule.rule === 'min'
                ? Number(text) >= (rule.value ?? 0)
                : Number(text) <= (rule.value ?? 0);
        default:
            return true;
    }
}

/**
 * The message of the first rule this value breaks, in the order the server
 * checks them, or null if none.
 */
export function firstFailure(
    validation: FieldValidation,
    value: unknown,
    required: boolean,
): string | null {
    for (const rule of validation.rules) {
        const isRequiredRule = rule.rule === 'required' || rule.rule === 'accepted';

        if (isRequiredRule) {
            if (required && (rule.rule === 'accepted' ? !passes(rule, value) : isBlank(value))) {
                return rule.message;
            }

            continue;
        }

        // Laravel skips other rules on an empty value.
        if (!isBlank(value) && !passes(rule, value)) {
            return rule.message;
        }
    }

    return null;
}
