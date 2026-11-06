<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import {
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
    TagsInputRoot,
} from 'reka-ui';
import { computed, ref } from 'vue';
import { useField } from '../../composables/useField';
import { useSortable } from '../../composables/useSortable';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass } from '../../ui/classes';
import { moveItem } from '../../ui/options';
import XqFieldWrapper from '../XqFieldWrapper.vue';

const props = defineProps<{ node: SchemaNode }>();
const { value, error, placeholder, describedBy, required, disabled, id, onBlur } = useField<
    string[]
>(props.node);
const options = computed(() => props.node.props ?? {});
const suggestions = computed<string[]>(() => options.value.suggestions ?? []);
const splitKeys = computed<string[]>(() => options.value.splitKeys ?? []);

const tags = computed<string[]>({
    get: () => value.value ?? [],
    set: (next) => (value.value = next),
});

/** Enter always finishes a tag. Reka takes the other keys as one pattern, and a comma without one. */
const delimiter = computed(() => {
    const characters = splitKeys.value.filter((key) => key.length === 1);

    return characters.length
        ? new RegExp(`[${characters.map((key) => `\\${key}`).join('')}]`)
        : undefined;
});

function move(tag: string, offset: number): void {
    tags.value = moveItem(tags.value, tag, offset);
}

const tagList = ref<HTMLElement | null>(null);

useSortable(tagList, {
    draggable: '> [data-xq-chip]',
    enabled: () => !!options.value.reorderable && !disabled.value && tags.value.length > 1,
    onMove: (from, to) => move(tags.value[from], to - from),
});
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <TagsInputRoot
            v-model="tags"
            :disabled="disabled"
            :delimiter="delimiter ?? ''"
            :add-on-tab="splitKeys.includes('Tab')"
            add-on-paste
            add-on-blur
            :class="
                cn(
                    controlClass,
                    'min-h-9 gap-1 px-2 py-1 flex flex-wrap items-center focus-within:border-xq-ring focus-within:ring-[3px] focus-within:ring-xq-ring/25',
                    error && 'border-xq-destructive',
                )
            "
        >
            <span ref="tagList" class="gap-1 flex max-w-full flex-wrap">
                <TagsInputItem
                    data-xq-chip
                    v-for="(tag, index) in tags"
                    :key="tag"
                    :value="tag"
                    :class="[options.reorderable && 'cursor-grab touch-none']"
                    class="gap-1 py-0.5 pr-1 pl-2 text-xs font-medium inline-flex items-center rounded-xq-sm bg-xq-secondary text-xq-secondary-foreground data-[state=active]:ring-2 data-[state=active]:ring-xq-ring"
                >
                    <button
                        v-if="options.reorderable && index > 0"
                        type="button"
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="`Move ${tag} earlier`"
                        :disabled="disabled"
                        @click="move(tag, -1)"
                    >
                        <ChevronLeft class="size-3" />
                    </button>
                    <span v-if="options.tagPrefix">{{ options.tagPrefix }}</span>
                    <TagsInputItemText />
                    <span v-if="options.tagSuffix">{{ options.tagSuffix }}</span>
                    <button
                        v-if="options.reorderable && index < tags.length - 1"
                        type="button"
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="`Move ${tag} later`"
                        :disabled="disabled"
                        @click="move(tag, 1)"
                    >
                        <ChevronRight class="size-3" />
                    </button>
                    <TagsInputItemDelete
                        class="rounded-sm cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="`Remove ${tag}`"
                    >
                        <X class="size-3" />
                    </TagsInputItemDelete>
                </TagsInputItem>
            </span>
            <TagsInputInput
                :id="id"
                :placeholder="placeholder ?? 'Add a tag…'"
                :list="suggestions.length ? `${id}-list` : undefined"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="describedBy"
                class="h-7 min-w-24 px-1 text-sm flex-1 bg-transparent outline-none placeholder:text-xq-muted-foreground"
                @blur="onBlur"
            />
            <datalist v-if="suggestions.length" :id="`${id}-list`">
                <option v-for="suggestion in suggestions" :key="suggestion" :value="suggestion" />
            </datalist>
        </TagsInputRoot>
    </XqFieldWrapper>
</template>
