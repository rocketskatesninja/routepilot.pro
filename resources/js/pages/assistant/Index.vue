<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useOnline } from '@/composables/useOnline';
import AppLayout from '@/layouts/AppLayout.vue';
import { renderParts } from '@/lib/assistantLinks';
import { postJson } from '@/lib/http';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bot, MessageSquarePlus, Send, WifiOff } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface Message {
    role: string;
    content: string;
}

const props = defineProps<{
    messages: Message[];
    sessionId: number | null;
    hasEarlier: boolean;
    hasPrevious: boolean;
    suggestions: string[];
    intro: string;
}>();

const page = usePage<SharedData>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Assistant', href: '/assistant' }];

const messages = ref<Message[]>([...props.messages]);
const sessionId = ref<number | null>(props.sessionId);
// Inertia keeps this page mounted between its own visits (New conversation,
// Continue, Show earlier), so take each visit's chat when it arrives.
watch(
    () => props.messages,
    (fresh) => {
        messages.value = [...fresh];
        sessionId.value = props.sessionId;
        void scrollDown();
    },
);
const draft = ref('');
const sending = ref(false);
// The assistant needs the internet: offline (a tech out of signal), it says so and waits.
const online = useOnline();

// Links in a reply follow this account's allow-list (lib/assistantLinks).
const role = computed(() => page.props.auth.role ?? 'customer');

// New conversation: the server starts an empty chat and reloads the page.
const startNew = () => router.post('/assistant/new');

// The page itself scrolls (the message box is pinned to the bottom of the
// screen), so "scroll down" means the window, to show the newest message.
async function scrollDown() {
    await nextTick();
    window.scrollTo({ top: document.documentElement.scrollHeight });
}
onMounted(() => {
    if (messages.value.length > 0) void scrollDown();
});

// When a reply lands, put the question at the top of the screen so a long
// answer reads from its start (a short one just ends up at the bottom).
async function showReply() {
    await nextTick();
    [...document.querySelectorAll('[data-mine]')].pop()?.scrollIntoView({ block: 'start' });
}

// Keep the cursor in the message box after every send (and after tapping an example).
const focusBox = () => void nextTick(() => document.getElementById('assistant-message')?.focus({ preventScroll: true }));

const say = (content: string) => messages.value.push({ role: 'assistant', content });

async function send(text?: string) {
    const message = (text ?? draft.value).trim();
    if (message === '' || sending.value || !online.value) {
        return;
    }
    draft.value = '';
    messages.value.push({ role: 'user', content: message });
    sending.value = true;
    await scrollDown();

    try {
        const res = await postJson('/assistant/send', { message, session_id: sessionId.value });
        const data = (await res.json().catch(() => ({}))) as { reply?: string; message?: string; session_id?: number; error?: string };
        if (res.ok && data.reply) {
            sessionId.value = data.session_id ?? sessionId.value;
            // Show the message as it was saved (a card number typed in is blanked out).
            const mine = messages.value[messages.value.length - 1];
            if (mine?.role === 'user' && data.message) mine.content = data.message;
            say(data.reply);
        } else {
            say(data.error ?? 'The assistant isn’t available right now. Please try again in a minute.');
        }
    } catch {
        // It never got through (the connection dropped): put it back in the box to send again,
        // ahead of anything typed since (the box stays usable while waiting).
        messages.value.pop();
        draft.value = draft.value.trim() === '' ? message : `${message} ${draft.value}`;
        say('Couldn’t reach the assistant. Check your connection: your message is back in the box to send again.');
    } finally {
        sending.value = false;
        await showReply();
        focusBox();
    }
}
</script>

<template>
    <Head title="Assistant" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template v-if="messages.length > 0" #actions>
            <Button size="sm" variant="outline" :disabled="sending" @click="startNew">
                <MessageSquarePlus class="mr-1 size-4" /> New conversation
            </Button>
        </template>
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 pt-4">
            <div class="flex flex-1 flex-col gap-4 pb-4">
                <div v-if="hasEarlier" class="text-center">
                    <Link href="/assistant?continue=1&all=1" class="text-muted-foreground text-sm underline underline-offset-2 hover:opacity-80"
                        >Show earlier messages</Link
                    >
                </div>
                <div v-if="messages.length === 0" class="text-muted-foreground flex flex-1 flex-col items-center justify-center text-center">
                    <Bot class="mb-3 size-10 opacity-60" />
                    <p class="mb-4">{{ intro }}</p>
                    <p v-if="hasPrevious" class="-mt-2 mb-4 text-sm">
                        <Link href="/assistant?continue=1" class="underline underline-offset-2 hover:opacity-80"
                            >Continue your last conversation</Link
                        >
                    </p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button
                            v-for="s in suggestions"
                            :key="s"
                            :disabled="!online"
                            class="border-border hover:bg-muted rounded-full border px-3 py-1.5 text-sm transition-colors disabled:pointer-events-none disabled:opacity-50"
                            @click="send(s)"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <div
                    v-for="(m, i) in messages"
                    :key="i"
                    class="flex scroll-mt-4"
                    :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
                    :data-mine="m.role === 'user' || undefined"
                >
                    <div
                        class="max-w-[85%] rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap"
                        :class="m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
                    >
                        <template v-if="m.role === 'user'">{{ m.content }}</template>
                        <template v-else>
                            <template v-for="(part, pi) in renderParts(m.content, role)" :key="pi"
                                ><Link
                                    v-if="part.kind === 'link'"
                                    :href="part.url as string"
                                    class="text-primary font-medium underline underline-offset-2 hover:opacity-80"
                                    >{{ part.text }}</Link
                                ><strong v-else-if="part.kind === 'bold'">{{ part.text }}</strong
                                ><em v-else-if="part.kind === 'italic'">{{ part.text }}</em
                                ><template v-else>{{ part.text }}</template></template
                            >
                        </template>
                    </div>
                </div>

                <div v-if="sending" class="flex justify-start">
                    <div class="bg-muted text-muted-foreground rounded-2xl px-4 py-2 text-sm">Thinking…</div>
                </div>
            </div>

            <!-- Pinned to the bottom of the screen, however long the chat gets. -->
            <div class="bg-background sticky bottom-0 z-10 flex flex-col gap-3 pt-2 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div
                    v-if="!online"
                    role="status"
                    class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-300"
                >
                    <WifiOff class="mt-0.5 size-4 shrink-0" />
                    <p>You’re offline, so the assistant isn’t available. It needs an internet connection and will be back as soon as you are.</p>
                </div>

                <form class="flex items-center gap-2" @submit.prevent="send()">
                    <Input
                        v-model="draft"
                        :placeholder="online ? 'Message the assistant…' : 'Waiting for a connection…'"
                        id="assistant-message"
                        :disabled="!online"
                        autocomplete="off"
                    />
                    <Button type="submit" size="icon" :disabled="sending || !online || draft.trim() === ''" aria-label="Send">
                        <Send class="size-4" />
                    </Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
