function name(date: Date, options: Intl.DateTimeFormatOptions, locale?: string): string {
    return new Intl.DateTimeFormat(locale, options).format(date);
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

const TOKENS: Record<string, (date: Date, locale?: string) => string> = {
    d: (date) => pad(date.getDate()),
    j: (date) => String(date.getDate()),
    D: (date, locale) => name(date, { weekday: 'short' }, locale),
    l: (date, locale) => name(date, { weekday: 'long' }, locale),
    m: (date) => pad(date.getMonth() + 1),
    n: (date) => String(date.getMonth() + 1),
    M: (date, locale) => name(date, { month: 'short' }, locale),
    F: (date, locale) => name(date, { month: 'long' }, locale),
    Y: (date) => String(date.getFullYear()),
    y: (date) => String(date.getFullYear()).slice(-2),
    H: (date) => pad(date.getHours()),
    G: (date) => String(date.getHours()),
    h: (date) => pad(date.getHours() % 12 || 12),
    g: (date) => String(date.getHours() % 12 || 12),
    i: (date) => pad(date.getMinutes()),
    s: (date) => pad(date.getSeconds()),
    A: (date) => (date.getHours() < 12 ? 'AM' : 'PM'),
    a: (date) => (date.getHours() < 12 ? 'am' : 'pm'),
};

/**
 * Format a date with PHP's date tokens, so a format written in PHP reads the same
 * in the browser. A backslash escapes the next character.
 */
export function formatPhpDate(date: Date, format: string, locale?: string): string {
    return format.replace(/\\(.)|[a-zA-Z]/g, (token, escaped?: string) => {
        return escaped ?? TOKENS[token]?.(date, locale) ?? token;
    });
}
