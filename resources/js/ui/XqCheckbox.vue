<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '../lib/utils';

const props = defineProps<{ id?: string; disabled?: boolean; class?: HTMLAttributes['class'] }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <CheckboxRoot
        :id="id"
        v-model="model"
        data-slot="xq-checkbox"
        :disabled="disabled"
        :class="
            cn(
                'peer size-4 shadow-xs shrink-0 cursor-pointer rounded-[4px] border border-xq-input bg-xq-background transition-shadow outline-none focus-visible:ring-[3px] focus-visible:ring-xq-ring/30 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-xq-destructive data-[state=checked]:border-xq-primary data-[state=checked]:bg-xq-primary data-[state=checked]:text-xq-primary-foreground',
                props.class,
            )
        "
    >
        <CheckboxIndicator class="flex items-center justify-center text-current">
            <Check class="size-3.5" :stroke-width="3" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
