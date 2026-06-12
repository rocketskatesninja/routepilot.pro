<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ImagePlus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Staged image upload. The chosen file is held in the parent form (v-model) and
 * is only uploaded when that form is saved — so the user can pick, preview, and
 * swap as many times as they like beforehand, and "Discard" reverts to the
 * currently-saved image. Pair with a server field that accepts the file on save.
 */
const props = withDefaults(
    defineProps<{
        modelValue: File | null;
        current?: string | null; // the already-saved photo URL, if any
        shape?: 'square' | 'circle';
    }>(),
    { current: null, shape: 'square' },
);
const emit = defineEmits<{ 'update:modelValue': [File | null] }>();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

watch(
    () => props.modelValue,
    (file) => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        }
        if (file) {
            previewUrl.value = URL.createObjectURL(file);
        }
    },
);
onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

// staged preview wins; else the saved image; else the placeholder
const shown = computed(() => previewUrl.value ?? props.current ?? null);
const staged = computed(() => previewUrl.value !== null);
const shapeClass = computed(() => (props.shape === 'circle' ? 'rounded-full' : 'rounded-md'));

const pick = () => fileInput.value?.click();

function onFile(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    emit('update:modelValue', file);
}

function discard() {
    emit('update:modelValue', null);
    if (fileInput.value) fileInput.value.value = '';
}
</script>

<template>
    <div class="flex items-center gap-3">
        <div :class="[shapeClass, 'relative flex size-16 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted/40']">
            <img v-if="shown" :src="shown" class="h-full w-full object-cover" alt="" />
            <ImagePlus v-else class="size-5 text-muted-foreground" />
        </div>
        <div class="flex flex-col items-start gap-1">
            <div class="flex gap-2">
                <Button type="button" size="sm" variant="outline" @click="pick">{{ shown ? 'Change photo' : 'Upload photo' }}</Button>
                <Button v-if="staged" type="button" size="sm" variant="ghost" @click="discard">Discard</Button>
            </div>
            <p v-if="staged" class="text-xs text-muted-foreground">New photo — applies when you save.</p>
        </div>
        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
    </div>
</template>
