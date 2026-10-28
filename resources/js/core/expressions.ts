import type { ExpressionNode } from '../schema/types';
import type { Getter } from './conditions';
import { compare, equals, isPlainObject, isTruthy, toNumber } from './values';

/**
 * Evaluate an expression AST produced by the PHP parser. There is no parser
 * in the browser, so nothing user-typed is ever executed.
 * Mirrors Xelqun\Forms\Expressions\ExpressionEvaluator.
 */
export function evaluateExpression(node: ExpressionNode, get: Getter): unknown {
    if ('lit' in node) {
        return node.lit;
    }

    if ('ref' in node) {
        return get(node.ref);
    }

    if ('fn' in node) {
        return call(node.fn, node.args, get);
    }

    const [first, second] = node.args;

    switch (node.op) {
        case '&&':
            return (
                isTruthy(evaluateExpression(first, get)) &&
                isTruthy(evaluateExpression(second, get))
            );
        case '||':
            return (
                isTruthy(evaluateExpression(first, get)) ||
                isTruthy(evaluateExpression(second, get))
            );
        case '!':
            return !isTruthy(evaluateExpression(first, get));
        case 'neg':
            return -toNumber(evaluateExpression(first, get)) || 0;
    }

    const left = evaluateExpression(first, get);
    const right = evaluateExpression(second, get);

    switch (node.op) {
        case '==':
            return equals(left, right);
        case '!=':
            return !equals(left, right);
        case '<':
            return compare(left, right) === -1;
        case '<=':
            return [-1, 0].includes(compare(left, right) as number);
        case '>':
            return compare(left, right) === 1;
        case '>=':
            return [0, 1].includes(compare(left, right) as number);
    }

    return arithmetic(node.op, toNumber(left), toNumber(right));
}

function arithmetic(operator: string, left: number, right: number): number {
    switch (operator) {
        case '+':
            return left + right;
        case '-':
            return left - right;
        case '*':
            return left * right;
        case '/':
            return right === 0 ? 0 : left / right;
        case '%':
            return right === 0 ? 0 : left % right;
    }

    throw new Error(`Unknown operator [${operator}].`);
}

function call(name: string, args: ExpressionNode[], get: Getter): unknown {
    if (name === 'if') {
        const condition = isTruthy(evaluateExpression(args[0], get));
        const branch = args[condition ? 1 : 2];

        return branch ? evaluateExpression(branch, get) : null;
    }

    const values = args.map((arg) => evaluateExpression(arg, get));

    if (name === 'round') {
        const precision = Math.trunc(toNumber(values[1] ?? 0));
        const number = toNumber(values[0] ?? 0);
        const factor = 10 ** precision;
        const sign = number < 0 ? -1 : 1;

        return (sign * Math.floor(Math.abs(number) * factor + 0.5)) / factor;
    }

    if (name === 'abs') {
        return Math.abs(toNumber(values[0] ?? 0));
    }

    const items = values.flatMap((value) => {
        if (Array.isArray(value)) {
            return value;
        }

        return isPlainObject(value) ? Object.values(value) : [value];
    });

    if (name === 'count') {
        return items.length;
    }

    const numbers = items.map(toNumber);

    switch (name) {
        case 'sum':
            return numbers.reduce((total, number) => total + number, 0);
        case 'avg':
            return numbers.length === 0
                ? 0
                : numbers.reduce((total, number) => total + number, 0) / numbers.length;
        case 'min':
            return numbers.length === 0 ? 0 : Math.min(...numbers);
        case 'max':
            return numbers.length === 0 ? 0 : Math.max(...numbers);
    }

    throw new Error(`Unknown function [${name}].`);
}
