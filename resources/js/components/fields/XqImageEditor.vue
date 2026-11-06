<script setup lang="ts">
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { FlipHorizontal, FlipVertical, RotateCcw, RotateCw, Undo2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useTranslator } from '../../composables/context';
import { toolbarButtonClass } from '../../ui/classes';
import XqButton from '../../ui/XqButton.vue';
import XqDialog from '../../ui/XqDialog.vue';

const props = defineProps<{
    src: string;
    name: string;
    aspectRatios?: Array<string | null> | null;
    fillColor?: string | null;
}>();
const emit = defineEmits<{ save: [file: File]; cancel: [] }>();
const t = useTranslator();
const open = ref(true);
const failed = ref(false);
const image = ref<HTMLImageElement | null>(null);
let cropper: Cropper | null = null;
let scaleX = 1;
let scaleY = 1;

const ratios = computed(() => props.aspectRatios ?? []);

function toNumber(ratio: string | null): number {
    const [width, height] = (ratio ?? '').split(':').map(Number);

    return width > 0 && height > 0 ? width / height : NaN;
}

function start(): void {
    cropper = new Cropper(image.value!, {
        viewMode: 1,
        autoCropArea: 1,
        aspectRatio: ratios.value.length ? toNumber(ratios.value[0]) : NaN,
    });
}

function flip(axis: 'x' | 'y'): void {
    if (axis === 'x') {
        scaleX = -scaleX;
        cropper?.scaleX(scaleX);
    } else {
        scaleY = -scaleY;
        cropper?.scaleY(scaleY);
    }
}

function close(): void {
    open.value = false;
    emit('cancel');
}

function save(): void {
    // A stored file on another origin taints the canvas, unless that origin allows it.
    try {
        cropper
            ?.getCroppedCanvas({ fillColor: props.fillColor ?? 'transparent' })
            .toBlob((blob) => {
                if (blob) {
                    emit('save', new File([blob], props.name, { type: blob.type }));
                } else {
                    failed.value = true;
                }
            });
    } catch {
        failed.value = true;
    }
}

onBeforeUnmount(() => cropper?.destroy());
</script>

<template>
    <XqDialog
        :open="open"
        :title="t('Edit image')"
        :description="name"
        width="2xl"
        @update:open="$event || close()"
    >
        <div class="gap-3 grid">
            <div class="max-h-[60vh] overflow-hidden">
                <img
                    ref="image"
                    :src="src"
                    :alt="name"
                    crossorigin="anonymous"
                    class="block max-w-full"
                    @load="start"
                    @error="failed = true"
                />
            </div>
            <p v-if="failed" role="alert" class="text-xs font-medium text-xq-destructive">
                {{ t('The image could not be edited.') }}
            </p>
            <div class="gap-1 flex flex-wrap items-center">
                <button
                    type="button"
                    :class="toolbarButtonClass"
                    :aria-label="t('Rotate left')"
                    @click="cropper?.rotate(-90)"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="toolbarButtonClass"
                    :aria-label="t('Rotate right')"
                    @click="cropper?.rotate(90)"
                >
                    <RotateCw class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="toolbarButtonClass"
                    :aria-label="t('Flip horizontally')"
                    @click="flip('x')"
                >
                    <FlipHorizontal class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="toolbarButtonClass"
                    :aria-label="t('Flip vertically')"
                    @click="flip('y')"
                >
                    <FlipVertical class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="toolbarButtonClass"
                    :aria-label="t('Reset')"
                    @click="cropper?.reset()"
                >
                    <Undo2 class="size-4" aria-hidden="true" />
                </button>
                <div v-if="ratios.length" class="gap-1 ms-auto flex flex-wrap">
                    <XqButton
                        v-for="ratio in ratios"
                        :key="ratio ?? 'free'"
                        type="button"
                        variant="outline"
                        size="sm"
                        :data-xq-ratio="ratio ?? 'free'"
                        @click="cropper?.setAspectRatio(toNumber(ratio))"
                    >
                        {{ ratio ?? t('Free') }}
                    </XqButton>
                </div>
            </div>
            <div class="gap-2 flex justify-end">
                <XqButton type="button" variant="outline" size="sm" @click="close">
                    {{ t('Cancel') }}
                </XqButton>
                <XqButton type="button" size="sm" data-xq-image-save @click="save">
                    {{ t('Save') }}
                </XqButton>
            </div>
        </div>
    </XqDialog>
</template>
