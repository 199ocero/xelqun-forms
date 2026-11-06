const TOKENS: Record<string, RegExp> = {
    '9': /\d/,
    a: /[a-zA-Z]/,
    '*': /[a-zA-Z0-9]/,
};

/**
 * Fit a value to a mask: 9 is a digit, a is a letter, * is either, and anything
 * else is typed for the user. It stops where the value runs out, so a separator
 * only appears once something follows it and backspace can remove it.
 */
export function applyMask(value: string, mask: string): string {
    let output = '';
    let source = 0;

    for (const character of mask) {
        const token = TOKENS[character];

        if (!token) {
            if (source >= value.length) {
                break;
            }

            output += character;
            source += value[source] === character ? 1 : 0;

            continue;
        }

        while (source < value.length && !token.test(value[source])) {
            source++;
        }

        if (source >= value.length) {
            break;
        }

        output += value[source++];
    }

    return output;
}
