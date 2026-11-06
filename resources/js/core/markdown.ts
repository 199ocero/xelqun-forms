export interface TextEdit {
    text: string;
    selectionStart: number;
    selectionEnd: number;
}

/**
 * Put marks around the selection, keeping it selected.
 */
export function wrapSelection(
    text: string,
    start: number,
    end: number,
    before: string,
    after: string,
    placeholder = '',
): TextEdit {
    const inner = text.slice(start, end) || placeholder;

    return {
        text: `${text.slice(0, start)}${before}${inner}${after}${text.slice(end)}`,
        selectionStart: start + before.length,
        selectionEnd: start + before.length + inner.length,
    };
}

/**
 * Start every selected line with a prefix. "#" numbers them, for an ordered list.
 */
export function prefixLines(text: string, start: number, end: number, prefix: string): TextEdit {
    const lineStart = text.lastIndexOf('\n', start - 1) + 1;
    const nextBreak = text.indexOf('\n', end);
    const lineEnd = nextBreak === -1 ? text.length : nextBreak;
    const lines = text
        .slice(lineStart, lineEnd)
        .split('\n')
        .map((line, index) => (prefix === '#' ? `${index + 1}. ${line}` : `${prefix}${line}`))
        .join('\n');

    return {
        text: `${text.slice(0, lineStart)}${lines}${text.slice(lineEnd)}`,
        selectionStart: lineStart,
        selectionEnd: lineStart + lines.length,
    };
}

/**
 * Add a block on lines of its own.
 */
export function insertBlock(text: string, position: number, block: string): TextEdit {
    const before = text.slice(0, position);
    const lead =
        before === '' || before.endsWith('\n\n') ? '' : before.endsWith('\n') ? '\n' : '\n\n';
    const inserted = `${lead}${block}\n`;

    return {
        text: `${before}${inserted}${text.slice(position)}`,
        selectionStart: position + inserted.length,
        selectionEnd: position + inserted.length,
    };
}
