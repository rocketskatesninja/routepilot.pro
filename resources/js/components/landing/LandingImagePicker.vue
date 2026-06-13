<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ImagePlus, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ url?: string | null; label?: string }>();
const emit = defineEmits<{ uploaded: [{ path: string; url: string }] }>();

const input = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const error = ref('');

function cookie(name: string): string {
    if (typeof document === 'undefined') {
        return '';
    }
    const m = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[2]) : '';
}

async function onPick(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) {
        return;
    }
    uploading.value = true;
    error.value = '';
    try {
        const fd = new FormData();
        fd.append('image', file);
        const res = await fetch('/company/landing/image', {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': cookie('XSRF-TOKEN'), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            credentials: 'same-origin',
            body: fd,
        });
        if (!res.ok) {
            throw new Error('upload failed');
        }
        const data = (await res.json()) as { path: string; url: string };
        emit('uploaded', data);
    } catch {
        error.value = 'Upload failed — try again.';
    } finally {
        uploading.value = false;
        if (input.value) {
            input.value.value = '';
        }
    }
}
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="size-16 shrink-0 overflow-hidden rounded-md border border-border bg-muted/40">
            <img v-if="url" :src="url" alt="" class="h-full w-full object-cover" />
            <div v-else class="flex h-full w-full items-center justify-center"><ImagePlus class="size-5 text-muted-foreground" /></div>
        </div>
        <div>
            <Button type="button" variant="outline" size="sm" :disabled="uploading" @click="input?.click()">
                <Loader2 v-if="uploading" class="mr-1 size-4 animate-spin" />{{ url ? 'Replace' : 'Upload' }} {{ label || 'image' }}
            </Button>
            <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
        </div>
        <input ref="input" type="file" accept="image/*" class="hidden" @change="onPick" />
    </div>
</template>
