import type { SchemaNode } from '../schema/types';
import { isPlainObject } from './values';

function isNodeList(value: unknown): value is SchemaNode[] {
    return (
        Array.isArray(value) &&
        value.length > 0 &&
        value.every((item) => isPlainObject(item) && typeof item.key === 'string')
    );
}

/**
 * Merge a partial node into an existing reactive node in place.
 *
 * Objects are merged key by key and node lists are merged by node key, so
 * existing node objects (and the components rendering them) are reused:
 * nothing re-mounts and focus and cursor position are kept.
 */
export function mergeNode(target: Record<string, any>, patch: Record<string, any>): void {
    for (const [key, value] of Object.entries(patch)) {
        const current = target[key];

        if (isNodeList(value) && Array.isArray(current)) {
            target[key] = mergeNodeList(current, value);
        } else if (isPlainObject(value) && isPlainObject(current) && !Array.isArray(value)) {
            mergeNode(current, value);
        } else {
            target[key] = value;
        }
    }
}

function mergeNodeList(current: SchemaNode[], incoming: SchemaNode[]): SchemaNode[] {
    const existing = new Map(current.map((node) => [node.key, node]));

    return incoming.map((node) => {
        const match = existing.get(node.key);

        if (!match) {
            return node;
        }

        mergeNode(match, node);

        return match;
    });
}

/**
 * Visit a node and every node below it, including repeater rows (but not templates).
 */
export function eachNode(nodes: SchemaNode[], callback: (node: SchemaNode) => void): void {
    for (const node of nodes) {
        callback(node);
        eachNode(node.children ?? [], callback);

        if (node.type === 'repeater' && isPlainObject(node.props?.rows)) {
            for (const rowNodes of Object.values(
                node.props!.rows as Record<string, SchemaNode[]>,
            )) {
                eachNode(rowNodes, callback);
            }
        }
    }
}
