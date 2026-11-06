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
    const line = messages?.[text] ?? text;
    const names = Object.keys(replacements).sort((first, second) => second.length - first.length);

    if (names.length === 0) {
        return line;
    }

    // One pass, longest name first, so :to can't match inside :total and a value
    // holding ":name" isn't filled in again. A function, so "$&" isn't a pattern.
    return line.replace(new RegExp(`:(${names.join('|')})`, 'g'), (_, name: string) =>
        String(replacements[name]),
    );
}
