import { describe, expect, it } from 'vitest';
import conditions from '../Fixtures/conformance/conditions.json';
import expressions from '../Fixtures/conformance/expressions.json';
import { evaluateCondition } from '../../resources/js/core/conditions';
import { evaluateExpression } from '../../resources/js/core/expressions';
import { getPath } from '../../resources/js/core/paths';
import type { ConditionNode, ExpressionNode } from '../../resources/js/schema/types';

/**
 * Same fixtures as tests/Unit/ConformanceTest.php: PHP and the browser must agree.
 */
describe('conditions conformance', () => {
    it.each(conditions.map((testCase) => [testCase.name, testCase]))('%s', (_, testCase) => {
        const get = (path: string) => getPath(testCase.state, path);

        expect(evaluateCondition(testCase.condition as ConditionNode, get)).toBe(testCase.expected);
    });
});

describe('expressions conformance', () => {
    it.each(expressions.map((testCase) => [testCase.name, testCase]))('%s', (_, testCase) => {
        const get = (path: string) => getPath(testCase.state, path);

        expect(evaluateExpression(testCase.ast as ExpressionNode, get)).toBe(testCase.expected);
    });
});
