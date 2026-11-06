import { toComponent } from '../composables/registry';
import type { ComponentRegistry, ComponentResolver } from '../composables/registry';
import XqAction from './XqAction.vue';
import XqActionGroup from './XqActionGroup.vue';
import XqCheckboxField from './fields/XqCheckboxField.vue';
import XqRadioField from './fields/XqRadioField.vue';
import XqRepeaterField from './fields/XqRepeaterField.vue';
import XqSelectField from './fields/XqSelectField.vue';
import XqTextareaField from './fields/XqTextareaField.vue';
import XqTextInputField from './fields/XqTextInputField.vue';
import XqToggleField from './fields/XqToggleField.vue';
import XqActions from './layouts/XqActions.vue';
import XqBlock from './layouts/XqBlock.vue';
import XqCallout from './layouts/XqCallout.vue';
import XqEmptyState from './layouts/XqEmptyState.vue';
import XqFieldset from './layouts/XqFieldset.vue';
import XqFlex from './layouts/XqFlex.vue';
import XqFusedGroup from './layouts/XqFusedGroup.vue';
import XqGrid from './layouts/XqGrid.vue';
import XqIconNode from './layouts/XqIconNode.vue';
import XqImageNode from './layouts/XqImageNode.vue';
import XqSection from './layouts/XqSection.vue';
import XqTabsLayout from './layouts/XqTabsLayout.vue';
import XqTextNode from './layouts/XqTextNode.vue';
import XqUnorderedList from './layouts/XqUnorderedList.vue';
import XqWizard from './layouts/XqWizard.vue';

/**
 * Built-in renderers keyed by schema node type. Heavier fields are code-split.
 */
export function defaultComponents(): Record<string, ComponentResolver> {
    return {
        'text-input': XqTextInputField,
        textarea: XqTextareaField,
        select: XqSelectField,
        checkbox: XqCheckboxField,
        toggle: XqToggleField,
        radio: XqRadioField,
        'date-picker': () => import('./fields/XqDatePickerField.vue'),
        'date-time-picker': () => import('./fields/XqDatePickerField.vue'),
        'time-picker': () => import('./fields/XqDatePickerField.vue'),
        'file-upload': () => import('./fields/XqFileUploadField.vue'),
        'checkbox-list': () => import('./fields/XqCheckboxListField.vue'),
        'toggle-buttons': () => import('./fields/XqToggleButtonsField.vue'),
        'tags-input': () => import('./fields/XqTagsInputField.vue'),
        slider: () => import('./fields/XqSliderField.vue'),
        'key-value': () => import('./fields/XqKeyValueField.vue'),
        'color-picker': () => import('./fields/XqColorPickerField.vue'),
        'rich-editor': () => import('./fields/XqRichEditorField.vue'),
        'markdown-editor': () => import('./fields/XqMarkdownEditorField.vue'),
        'code-editor': () => import('./fields/XqCodeEditorField.vue'),
        repeater: XqRepeaterField,
        builder: XqRepeaterField,
        section: XqSection,
        grid: XqGrid,
        fieldset: XqFieldset,
        tabs: XqTabsLayout,
        action: XqAction,
        'action-group': XqActionGroup,
        actions: XqActions,
        wizard: XqWizard,
        block: XqBlock,
        flex: XqFlex,
        'fused-group': XqFusedGroup,
        callout: XqCallout,
        'empty-state': XqEmptyState,
        text: XqTextNode,
        icon: XqIconNode,
        image: XqImageNode,
        'unordered-list': XqUnorderedList,
    };
}

export function createRegistry(custom: Record<string, ComponentResolver> = {}): ComponentRegistry {
    const registry: ComponentRegistry = new Map();

    for (const [type, resolver] of Object.entries({ ...defaultComponents(), ...custom })) {
        registry.set(type, toComponent(resolver));
    }

    return registry;
}
