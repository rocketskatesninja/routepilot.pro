<script setup lang="ts">
import { ImagePlus, X } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Staged multi-image upload (for report / visit photos). Files are held in the
 * parent form (v-model array) and only upload on save — add or remove individual
 * photos freely beforehand, each shown as a thumbnail.
 */
// `capture` opts the file input into a device camera (e.g. 'environment' = rear
// camera) so mobile opens the camera directly instead of the picker; omit for the
// normal picker (camera + library choice).
const props = withDefaults(defineProps<{ modelValue: File[]; capture?: 'environment' | 'user' }>(), {
    modelValue: () => [],
    capture: undefined,
});
const emit = defineEmits<{ 'update:modelValue': [File[]] }>();

const fileInput = ref<HTMLInputElement | null>(null);
const previews = ref<{ url: string }[]>([]);

watch(
    () => props.modelValue,
    (files) => {
        previews.value.forEach((p) => URL.revokeObjectURL(p.url));
        previews.value = files.map((file) => ({ url: URL.createObjectURL(file) }));
    },
    { immediate: true },
);
onBeforeUnmount(() => previews.value.forEach((p) => URL.revokeObjectURL(p.url)));

const pick = () => fileInput.value?.click();

function onFiles(e: Event) {
    const picked = Array.from((e.target as HTMLInputElement).files ?? []);
    if (picked.length) emit('update:modelValue', [...props.modelValue, ...picked]);
    if (fileInput.value) fileInput.value.value = '';
}

function removeAt(i: number) {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, idx) => idx !== i),
    );
}
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-2">
            <div v-for="(p, i) in previews" :key="i" class="relative size-20 overflow-hidden rounded-md border border-border">
                <img :src="p.url" class="h-full w-full object-cover" alt="" />
                <button
                    type="button"
                    class="absolute right-0.5 top-0.5 rounded-full bg-black/60 p-0.5 text-white hover:bg-black/80"
                    aria-label="Remove photo"
                    @click="removeAt(i)"
                >
                    <X class="size-3.5" />
                </button>
            </div>
            <button
                type="button"
                class="flex size-20 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border text-muted-foreground hover:bg-muted/40"
                @click="pick"
            >
                <ImagePlus class="size-5" />
                <span class="text-xs">Add</span>
            </button>
        </div>
        <!-- With capture (camera-first) drop `multiple`: mobile browsers ignore capture
             when multiple is also set. Tapping Add again captures another photo. -->
        <input ref="fileInput" type="file" accept="image/*" :capture="props.capture" :multiple="!props.capture" class="hidden" @change="onFiles" />
    </div>
</template>
