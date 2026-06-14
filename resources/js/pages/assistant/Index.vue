<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { postJson } from '@/lib/http';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Bot, Send } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

interface Message {
    role: string;
    content: string;
}

const props = defineProps<{
    messages: Message[];
    sessionId: number | null;
    suggestions: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assistant', href: '/assistant' }];

const messages = ref<Message[]>([...props.messages]);
const sessionId = ref<number | null>(props.sessionId);
const draft = ref('');
const sending = ref(false);
const log = ref<HTMLElement | null>(null);

// Strip the [[type:id:Name]] entity-link syntax down to the plain name.
const render = (content: string) => content.replace(/\[\[[a-z]+:\d+:([^\]]+)\]\]/g, '$1');

async function scrollDown() {
    await nextTick();
    if (log.value) {
        log.value.scrollTop = log.value.scrollHeight;
    }
}

async function send(text?: string) {
    const message = (text ?? draft.value).trim();
    if (message === '' || sending.value) {
        return;
    }
    draft.value = '';
    messages.value.push({ role: 'user', content: message });
    sending.value = true;
    await scrollDown();

    try {
        const res = await postJson('/assistant/send', { message, session_id: sessionId.value });
        const data = await res.json();
        if (res.ok) {
            sessionId.value = data.session_id;
            messages.value.push({ role: 'assistant', content: data.reply });
        } else {
            messages.value.push({ role: 'assistant', content: data.error ?? '⚠️ Something went wrong.' });
        }
    } catch {
        messages.value.push({ role: 'assistant', content: '⚠️ Could not reach the assistant.' });
    } finally {
        sending.value = false;
        await scrollDown();
    }
}
</script>

<template>
    <Head title="Assistant" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-4 p-4">
            <div ref="log" class="flex-1 space-y-4 overflow-y-auto">
                <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center text-center text-muted-foreground">
                    <Bot class="mb-3 size-10 opacity-60" />
                    <p class="mb-4">Ask me about chemistry, scheduling, or your customers.</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button
                            v-for="s in suggestions"
                            :key="s"
                            class="rounded-full border border-border px-3 py-1.5 text-sm transition-colors hover:bg-muted"
                            @click="send(s)"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-2 text-sm"
                        :class="m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
                    >
                        {{ render(m.content) }}
                    </div>
                </div>

                <div v-if="sending" class="flex justify-start">
                    <div class="rounded-2xl bg-muted px-4 py-2 text-sm text-muted-foreground">Thinking…</div>
                </div>
            </div>

            <form class="flex items-center gap-2" @submit.prevent="send()">
                <Input v-model="draft" placeholder="Message the assistant…" :disabled="sending" autocomplete="off" />
                <Button type="submit" size="icon" :disabled="sending || draft.trim() === ''">
                    <Send class="size-4" />
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
