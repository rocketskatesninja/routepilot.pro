<script setup lang="ts">
import { postJson } from '@/lib/http';
import { MessageCircle, Send, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

const props = defineProps<{ action: string; company: string }>();

interface Msg {
    role: 'user' | 'assistant';
    content: string;
}

const open = ref(false);
const messages = ref<Msg[]>([
    { role: 'assistant', content: `Hi! 👋 I'm the ${props.company} assistant — ask me anything, or tell me what your pool needs.` },
]);
const input = ref('');
const sending = ref(false);
const token = ref<string | null>(localStorage.getItem('rp_chat_token'));
const scroller = ref<HTMLElement | null>(null);

async function scrollDown() {
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
}

function toggle() {
    open.value = !open.value;
    if (open.value) scrollDown();
}

async function send() {
    const text = input.value.trim();
    if (text === '' || sending.value) return;
    messages.value.push({ role: 'user', content: text });
    input.value = '';
    sending.value = true;
    await scrollDown();
    try {
        const res = await postJson(props.action, { message: text, visitor_token: token.value });
        const data = (await res.json()) as { reply?: string; visitor_token?: string };
        if (data.visitor_token) {
            token.value = data.visitor_token;
            localStorage.setItem('rp_chat_token', data.visitor_token);
        }
        messages.value.push({ role: 'assistant', content: data.reply || 'Sorry, something went wrong — please try the contact form.' });
    } catch {
        messages.value.push({ role: 'assistant', content: 'Sorry, I had trouble connecting. Please try the contact form below.' });
    } finally {
        sending.value = false;
        await scrollDown();
    }
}
</script>

<template>
    <div class="fixed bottom-5 right-5 z-50 print:hidden">
        <!-- panel -->
        <transition name="chat-pop">
            <div
                v-if="open"
                class="mb-3 flex h-[28rem] w-[20rem] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl"
            >
                <header class="flex items-center justify-between gap-2 bg-primary px-4 py-3 text-primary-foreground">
                    <span class="font-semibold">{{ company }}</span>
                    <button class="rounded p-1 hover:bg-white/15" aria-label="Close chat" @click="toggle"><X class="size-4" /></button>
                </header>
                <div ref="scroller" class="flex-1 space-y-3 overflow-y-auto p-3">
                    <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                        <div
                            class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm"
                            :class="m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                        >
                            {{ m.content }}
                        </div>
                    </div>
                    <div v-if="sending" class="text-xs text-muted-foreground">Typing…</div>
                </div>
                <form class="flex items-center gap-2 border-t border-border p-2" @submit.prevent="send">
                    <input
                        v-model="input"
                        placeholder="Type a message…"
                        class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <button
                        type="submit"
                        :disabled="sending"
                        class="rounded-lg bg-primary p-2 text-primary-foreground disabled:opacity-60"
                        aria-label="Send"
                    >
                        <Send class="size-4" />
                    </button>
                </form>
            </div>
        </transition>

        <!-- launcher -->
        <button
            class="flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xl transition hover:scale-105"
            :aria-label="open ? 'Close chat' : 'Open chat'"
            @click="toggle"
        >
            <component :is="open ? X : MessageCircle" class="size-6" />
        </button>
    </div>
</template>

<style scoped>
.chat-pop-enter-active,
.chat-pop-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}
.chat-pop-enter-from,
.chat-pop-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
}
</style>
