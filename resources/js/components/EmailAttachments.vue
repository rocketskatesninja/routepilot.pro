<script setup lang="ts">
import { Paperclip, TriangleAlert, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Files to send with an Email people message. The same limits as the server
 * (SendCampaignRequest): up to 5 files, 8 MB each, 10 MB together, common types.
 * Every recipient gets their own copy, so a big send warns about the mail
 * server's daily limits.
 */
const props = defineProps<{
    modelValue: File[];
    /** How many people the email goes to (for the size warning). */
    recipients: number;
    /** The server's message, if it refused one. */
    error?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [File[]] }>();

const MAX_FILES = 5;
const MAX_FILE = 8 * 1024 * 1024;
const MAX_TOTAL = 10 * 1024 * 1024;
const TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
const accept = TYPES.map((t) => `.${t}`).join(',');

const input = ref<HTMLInputElement | null>(null);
const notice = ref('');

const size = (bytes: number) => (bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const total = computed(() => props.modelValue.reduce((n, f) => n + f.size, 0));
// Each recipient is sent the files: past ~100 MB through the mail server, say so.
const sendVolume = computed(() => total.value * props.recipients);

function add(event: Event) {
    const picked = [...((event.target as HTMLInputElement).files ?? [])];
    const next = [...props.modelValue];
    const skipped: string[] = [];
    for (const file of picked) {
        const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
        if (!TYPES.includes(ext)) skipped.push(`${file.name} isn't a type you can attach`);
        else if (file.size > MAX_FILE) skipped.push(`${file.name} is over 8 MB`);
        else if (next.length >= MAX_FILES) skipped.push(`${file.name}: up to ${MAX_FILES} files`);
        else if (next.reduce((n, f) => n + f.size, 0) + file.size > MAX_TOTAL) skipped.push(`${file.name} would take it past 10 MB`);
        else next.push(file);
    }
    notice.value = skipped.join('. ');
    emit('update:modelValue', next);
    if (input.value) input.value.value = ''; // so the same file can be picked again after removing it
}

function remove(i: number) {
    notice.value = '';
    emit(
        'update:modelValue',
        props.modelValue.filter((_, j) => j !== i),
    );
}
</script>

<template>
    <div class="grid gap-1.5">
        <div class="flex items-center justify-between gap-2">
            <button
                type="button"
                class="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline disabled:pointer-events-none disabled:opacity-40"
                :disabled="modelValue.length >= MAX_FILES"
                @click="input?.click()"
            >
                <Paperclip class="size-4" /> Attach files
            </button>
            <span v-if="modelValue.length" class="text-muted-foreground text-xs"
                >{{ modelValue.length }} of {{ MAX_FILES }} · {{ size(total) }} of 10 MB</span
            >
        </div>
        <input ref="input" type="file" multiple :accept="accept" class="hidden" @change="add" />
        <ul v-if="modelValue.length" class="border-border divide-border divide-y rounded-md border text-sm">
            <li v-for="(file, i) in modelValue" :key="`${file.name}-${i}`" class="flex items-center gap-2 px-3 py-1.5">
                <Paperclip class="text-muted-foreground size-3.5 shrink-0" />
                <span class="min-w-0 flex-1 truncate" :title="file.name">{{ file.name }}</span>
                <span class="text-muted-foreground shrink-0 text-xs">{{ size(file.size) }}</span>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground shrink-0 rounded p-0.5"
                    :aria-label="`Remove ${file.name}`"
                    @click="remove(i)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
        </ul>
        <p v-if="notice" class="text-xs text-amber-700 dark:text-amber-400">{{ notice }}.</p>
        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        <p v-if="sendVolume > 100 * 1024 * 1024" class="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400">
            <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
            <span
                >Each of the {{ recipients }} people gets their own copy: about {{ size(sendVolume) }} through your mail server. Email providers cap
                how much you can send a day, so a send this big can stop partway.</span
            >
        </p>
        <p v-if="modelValue.length" class="text-muted-foreground text-xs">Files are kept with the email for 90 days, then cleared.</p>
    </div>
</template>
