export type Replacements = Record<string, string | number>;

/**
 * Text is written in English and that is its key, so a missing line reads fine.
 * Fill :placeholders the way Laravel does.
 */
export function translate(
    messages: Record<string, string> | undefined,
    text: string,
    replacements: Replacements = {},
): string {
    return Object.entries(replacements).reduce(
        // A function, so a "$&" in a file name isn't read as a pattern.
        (line, [name, value]) => line.replaceAll(`:${name}`, () => String(value)),
        messages?.[text] ?? text,
    );
}
