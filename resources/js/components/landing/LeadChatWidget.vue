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
    <div class="fixed right-5 bottom-5 z-50 print:hidden">
        <!-- panel -->
        <transition name="chat-pop">
            <div
                v-if="open"
                class="border-border bg-background mb-3 flex h-[28rem] w-[20rem] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-2xl border shadow-2xl"
            >
                <header class="bg-primary text-primary-foreground flex items-center justify-between gap-2 px-4 py-3">
                    <span class="font-semibold">{{ company }}</span>
                    <button class="rounded p-1 hover:bg-white/15" aria-label="Close chat" @click="toggle"><X class="size-4" /></button>
                </header>
                <div ref="scroller" class="flex-1 space-y-3 overflow-y-auto p-3">
                    <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                        <div
                            class="max-w-[85%] rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap"
                            :class="m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                        >
                            {{ m.content }}
                        </div>
                    </div>
                    <div v-if="sending" class="text-muted-foreground text-xs">Typing…</div>
                </div>
                <form class="border-border flex items-center gap-2 border-t p-2" @submit.prevent="send">
                    <input
                        v-model="input"
                        placeholder="Type a message…"
                        class="border-input bg-background focus:ring-primary min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm focus:ring-1 focus:outline-none"
                    />
                    <button
                        type="submit"
                        :disabled="sending"
                        class="bg-primary text-primary-foreground rounded-lg p-2 disabled:opacity-60"
                        aria-label="Send"
                    >
                        <Send class="size-4" />
                    </button>
                </form>
            </div>
        </transition>

        <!-- launcher -->
        <button
            class="bg-primary text-primary-foreground flex size-14 items-center justify-center rounded-full shadow-xl transition hover:scale-105"
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
