<script setup lang="ts">
import {
    ColorAreaArea,
    ColorAreaRoot,
    ColorAreaThumb,
    ColorSliderRoot,
    ColorSliderThumb,
    ColorSliderTrack,
    colorToString,
    convertToHsb,
    isValidColor,
    parseColor,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import type { Color } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { useField } from '../../composables/useField';
import { cn } from '../../lib/utils';
import type { SchemaNode } from '../../schema/types';
import { controlClass, popoverClass } from '../../ui/classes';
import XqInput from '../../ui/XqInput.vue';
import XqFieldWrapper from '../XqFieldWrapper.vue';
import { useTranslator } from '../../composables/context';

const t = useTranslator();
const props = defineProps<{ node: SchemaNode }>();
const { value, error, placeholder, describedBy, required, disabled, id, onBlur } = useField<
    string | null
>(props.node);
const format = computed<string>(() => props.node.props?.format ?? 'hex');

function toHsb(input: string | null | undefined): Color {
    return convertToHsb(parseColor(input && isValidColor(input) ? input : '#000000'));
}

function toText(color: Color): string {
    if (format.value === 'rgba') {
        return colorToString({ ...color, alpha: Math.min(color.alpha, 0.999) }, 'rgb').replace(
            /, 0\.999\)$/,
            ', 1)',
        );
    }

    const opaque = { ...color, alpha: 1 };

    return colorToString(opaque, format.value === 'hex' ? 'hex' : (format.value as 'rgb' | 'hsl'));
}

/** Kept as HSB, because hex and rgb forget the hue of a black or grey color. */
const color = ref<Color>(toHsb(value.value));

watch(value, (next) => {
    if (next !== toText(color.value)) {
        color.value = toHsb(next);
    }
});

function pick(next: Color): void {
    color.value = next;
    value.value = toText(next);
}

function type(text: string | number | null | undefined): void {
    value.value = text ? String(text) : null;
}
</script>

<template>
    <XqFieldWrapper :node="node" :id="id" :error="error" :required="required">
        <PopoverRoot @update:open="(open) => !open && onBlur()">
            <div class="gap-2 flex items-center">
                <PopoverTrigger as-child>
                    <button
                        type="button"
                        :disabled="disabled"
                        :aria-label="
                            t('Choose a color, currently :value', { value: value ?? t('none') })
                        "
                        :class="
                            cn(
                                controlClass,
                                'size-10 shrink-0 cursor-pointer',
                                error && 'border-xq-destructive',
                            )
                        "
                        :style="{ backgroundColor: value ?? undefined }"
                    />
                </PopoverTrigger>
                <XqInput
                    :id="id"
                    :model-value="value"
                    :placeholder="placeholder ?? undefined"
                    :disabled="disabled"
                    :aria-invalid="error ? true : undefined"
                    :aria-describedby="describedBy"
                    class="font-mono"
                    @update:model-value="type"
                    @blur="onBlur"
                />
            </div>
            <PopoverPortal>
                <PopoverContent
                    align="start"
                    :side-offset="4"
                    :class="cn(popoverClass, 'w-60 gap-3 p-3 grid')"
                >
                    <ColorAreaRoot
                        v-slot="{ style }"
                        :model-value="color"
                        color-space="hsb"
                        x-channel="saturation"
                        y-channel="brightness"
                        @update:color="pick"
                    >
                        <ColorAreaArea
                            :style="style"
                            class="h-36 relative w-full touch-none rounded-xq-md"
                        >
                            <ColorAreaThumb
                                class="size-4 border-white shadow-md rounded-full border-2 outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/40"
                            />
                        </ColorAreaArea>
                    </ColorAreaRoot>
                    <ColorSliderRoot
                        :model-value="color"
                        color-space="hsb"
                        channel="hue"
                        class="h-4 relative flex w-full touch-none items-center"
                        @update:color="pick"
                    >
                        <ColorSliderTrack class="h-2.5 relative w-full rounded-full" />
                        <ColorSliderThumb
                            class="size-4 border-white shadow-md block rounded-full border-2 outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/40"
                        />
                    </ColorSliderRoot>
                    <ColorSliderRoot
                        v-if="format === 'rgba'"
                        :model-value="color"
                        color-space="hsb"
                        channel="alpha"
                        class="h-4 relative flex w-full touch-none items-center"
                        @update:color="pick"
                    >
                        <ColorSliderTrack class="h-2.5 relative w-full rounded-full" />
                        <ColorSliderThumb
                            class="size-4 border-white shadow-md block rounded-full border-2 outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/40"
                        />
                    </ColorSliderRoot>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </XqFieldWrapper>
</template>
