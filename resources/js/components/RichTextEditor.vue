<script setup lang="ts">
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Heading2, Italic, Link2, List, ListOrdered, Quote, Redo2, Underline, Undo2, UserRound } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';

/**
 * A small rich-text editor for emails people write (Email people, the company
 * signature). Only what the server keeps (App\Support\EmailHtml): paragraphs, bold,
 * italic, underline, two heading sizes, lists, quotes and links — anything else
 * pasted in is dropped here and again on the server.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string;
        /** Show the button that drops in {first name} (the email form; not the signature). */
        firstName?: boolean;
        label?: string;
        minHeight?: string;
    }>(),
    { firstName: false, label: 'Message', minHeight: '10rem' },
);
const emit = defineEmits<{ 'update:modelValue': [string] }>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
            horizontalRule: false,
            link: { openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto'], defaultProtocol: 'https' },
        }),
    ],
    editorProps: { attributes: { class: 'rich-text', 'aria-label': props.label, role: 'textbox', 'aria-multiline': 'true' } },
    onUpdate: ({ editor: e }) => emit('update:modelValue', e.getHTML()),
});

// A new value from outside (the form reset after sending, a pre-filled draft) replaces the text.
watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) editor.value.commands.setContent(value, { emitUpdate: false });
    },
);
onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    const e = editor.value;
    if (!e) return;
    const current = (e.getAttributes('link').href as string | undefined) ?? '';
    const url = window.prompt('Link to (leave empty to remove the link)', current || 'https://');
    if (url === null) return;
    const chain = e.chain().focus().extendMarkRange('link');
    if (url.trim() === '' || url.trim() === 'https://') chain.unsetLink().run();
    else chain.setLink({ href: url.trim() }).run();
}

const tools = [
    { label: 'Bold', icon: Bold, active: 'bold', run: () => editor.value?.chain().focus().toggleBold().run() },
    { label: 'Italic', icon: Italic, active: 'italic', run: () => editor.value?.chain().focus().toggleItalic().run() },
    { label: 'Underline', icon: Underline, active: 'underline', run: () => editor.value?.chain().focus().toggleUnderline().run() },
    { label: 'Heading', icon: Heading2, active: 'heading', run: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run() },
    { label: 'Bulleted list', icon: List, active: 'bulletList', run: () => editor.value?.chain().focus().toggleBulletList().run() },
    { label: 'Numbered list', icon: ListOrdered, active: 'orderedList', run: () => editor.value?.chain().focus().toggleOrderedList().run() },
    { label: 'Quote', icon: Quote, active: 'blockquote', run: () => editor.value?.chain().focus().toggleBlockquote().run() },
    { label: 'Link', icon: Link2, active: 'link', run: setLink },
];
</script>

<template>
    <div class="border-input bg-background focus-within:ring-ring/50 rounded-md border focus-within:ring-2">
        <div class="border-border flex flex-wrap items-center gap-0.5 border-b p-1" role="toolbar" :aria-label="`${label} formatting`">
            <button
                v-for="t in tools"
                :key="t.label"
                type="button"
                @mousedown.prevent
                class="hover:bg-muted rounded p-1.5 transition-colors"
                :class="editor?.isActive(t.active) ? 'bg-muted text-foreground' : 'text-muted-foreground'"
                :title="t.label"
                :aria-label="t.label"
                :aria-pressed="!!editor?.isActive(t.active)"
                @click="t.run"
            >
                <component :is="t.icon" class="size-4" />
            </button>
            <span class="bg-border mx-1 h-5 w-px" aria-hidden="true" />
            <button
                type="button"
                @mousedown.prevent
                class="text-muted-foreground hover:bg-muted rounded p-1.5"
                title="Undo"
                aria-label="Undo"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo2 class="size-4" />
            </button>
            <button
                type="button"
                @mousedown.prevent
                class="text-muted-foreground hover:bg-muted rounded p-1.5"
                title="Redo"
                aria-label="Redo"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo2 class="size-4" />
            </button>
            <button
                v-if="firstName"
                type="button"
                @mousedown.prevent
                class="text-muted-foreground hover:bg-muted ml-auto inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium"
                title="Each person sees their own first name here"
                @click="editor?.chain().focus().insertContent('{first name}').run()"
            >
                <UserRound class="size-3.5" /> First name
            </button>
        </div>
        <EditorContent :editor="editor" class="px-3 py-2 text-sm" :style="{ '--rich-min': minHeight }" />
    </div>
</template>

<style>
/* The editing area: roughly how the email will read. */
.rich-text {
    min-height: var(--rich-min, 10rem);
    outline: none;
    line-height: 1.6;
}
.rich-text p {
    margin: 0 0 0.6em;
}
.rich-text h2 {
    margin: 0.8em 0 0.4em;
    font-size: 1.15em;
    font-weight: 600;
}
.rich-text h3 {
    margin: 0.7em 0 0.3em;
    font-weight: 600;
}
.rich-text ul,
.rich-text ol {
    margin: 0 0 0.6em;
    padding-left: 1.4em;
}
.rich-text ul {
    list-style: disc;
}
.rich-text ol {
    list-style: decimal;
}
.rich-text blockquote {
    margin: 0 0 0.6em;
    padding-left: 0.75em;
    border-left: 3px solid hsl(var(--border));
    color: hsl(var(--muted-foreground));
}
.rich-text a {
    color: hsl(var(--primary));
    text-decoration: underline;
}
</style>
