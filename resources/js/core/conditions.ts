import type { ConditionNode } from '../schema/types';
import { compare, equals, inList, isFilled } from './values';

export type Getter = (path: string) => unknown;

/**
 * Evaluate a declarative condition tree in the browser.
 * Mirrors Xelqun\Forms\Conditions\Condition::evaluateTree().
 */
export function evaluateCondition(node: ConditionNode, get: Getter): boolean {
    switch (node.op) {
        case 'and':
            return node.args.every((child) => evaluateCondition(child, get));
        case 'or':
            return node.args.some((child) => evaluateCondition(child, get));
        case 'not':
            return !evaluateCondition(node.args[0], get);
    }

    const actual = get(node.path);

    switch (node.op) {
        case 'filled':
            return isFilled(actual);
        case 'blank':
            return !isFilled(actual);
    }

    const expected = node.value;
    const list = Array.isArray(expected) ? expected : [expected];

    switch (node.op) {
        case 'eq':
            return equals(actual, expected);
        case 'neq':
            return !equals(actual, expected);
        case 'in':
            return inList(actual, list);
        case 'notIn':
            return !inList(actual, list);
        case 'gt':
            return compare(actual, expected) === 1;
        case 'gte':
            return [0, 1].includes(compare(actual, expected) as number);
        case 'lt':
            return compare(actual, expected) === -1;
        case 'lte':
            return [-1, 0].includes(compare(actual, expected) as number);
    }

    return false;
}
