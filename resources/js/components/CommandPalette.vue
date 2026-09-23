<script setup lang="ts">
import { useCommandPalette } from '@/composables/useCommandPalette';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Item {
    type: string;
    label: string;
    sublabel?: string | null;
    url: string;
}
interface Group {
    label: string;
    items: Item[];
}

const page = usePage<SharedData>();
const isStaff = computed(() => ['agent', 'tenant_admin', 'super_admin'].includes(page.props.auth.role ?? ''));

const { isOpen } = useCommandPalette();
const mounted = ref(false); // gate the Teleport to client-only — keeps it out of SSR/hydration
const q = ref('');
const remote = ref<Group[]>([]);
const loading = ref(false);
const active = ref(0);
const inputEl = ref<HTMLInputElement | null>(null);
let seq = 0;
let debounce: ReturnType<typeof setTimeout> | null = null;

// Static "go to" commands — a quick jump to any screen.
const NAV: Item[] = [
    { type: 'page', label: 'Dashboard', url: '/dashboard' },
    { type: 'page', label: 'People', url: '/people' },
    { type: 'page', label: 'Schedule', url: '/schedule' },
    { type: 'page', label: 'Pools', url: '/pools' },
    { type: 'page', label: 'Reports', url: '/reports' },
    { type: 'page', label: 'Insights', url: '/insights' },
    { type: 'page', label: 'Inventory', url: '/inventory' },
    { type: 'page', label: 'Balances', url: '/balances' },
    { type: 'page', label: 'Assistant', url: '/assistant' },
];

const navMatches = computed<Item[]>(() => {
    const t = q.value.trim().toLowerCase();
    return t ? NAV.filter((n) => n.label.toLowerCase().includes(t)) : NAV;
});

const groups = computed<Group[]>(() => {
    const g: Group[] = [];
    if (navMatches.value.length) {
        g.push({ label: 'Go to', items: navMatches.value });
    }
    return g.concat(remote.value);
});
const flat = computed<Item[]>(() => groups.value.flatMap((g) => g.items));

const itemIndex = (gi: number, ii: number): number => {
    let n = 0;
    for (let x = 0; x < gi; x++) {
        n += groups.value[x].items.length;
    }
    return n + ii;
};

watch(q, () => {
    active.value = 0;
    if (debounce) {
        clearTimeout(debounce);
    }
    const term = q.value.trim();
    if (term.length < 2) {
        remote.value = [];
        loading.value = false;
        return;
    }
    loading.value = true;
    debounce = setTimeout(async () => {
        const mine = ++seq;
        try {
            const res = await fetch(`/search?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (mine === seq) {
                remote.value = data.groups ?? [];
            }
        } catch {
            if (mine === seq) {
                remote.value = [];
            }
        } finally {
            if (mine === seq) {
                loading.value = false;
            }
        }
    }, 200);
});

// Reset + focus whenever the palette opens (via ⌘K or the sidebar button); a
// non-staff opener is bounced immediately (the endpoint is staff-only anyway).
watch(isOpen, (v) => {
    if (!v) {
        return;
    }
    if (!isStaff.value) {
        isOpen.value = false;
        return;
    }
    q.value = '';
    remote.value = [];
    active.value = 0;
    nextTick(() => inputEl.value?.focus());
});
function close() {
    isOpen.value = false;
}
function select(item?: Item) {
    const it = item ?? flat.value[active.value];
    if (!it) {
        return;
    }
    close();
    router.visit(it.url);
}
function onListKey(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        active.value = Math.min(active.value + 1, flat.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        select();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        close();
    }
}
function onGlobalKey(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        if (!isStaff.value) {
            return;
        }
        isOpen.value = !isOpen.value;
    }
}
onMounted(() => {
    mounted.value = true;
    window.addEventListener('keydown', onGlobalKey);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKey);
    if (debounce) {
        clearTimeout(debounce);
    }
});
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex flex-col sm:items-start sm:justify-center sm:p-4 sm:pt-[12vh]"
            role="dialog"
            aria-modal="true"
            @keydown="onListKey"
        >
            <!-- Dim only matters for the centered desktop card; the mobile sheet covers the screen. -->
            <div class="fixed inset-0 hidden bg-black/40 sm:block" @click="close"></div>
            <div
                class="relative z-10 flex h-full w-full flex-col overflow-hidden bg-popover text-popover-foreground shadow-2xl sm:mx-auto sm:h-auto sm:max-w-lg sm:rounded-xl sm:border sm:border-border"
            >
                <div class="flex items-center gap-2 border-b border-border px-3">
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <input
                        ref="inputEl"
                        v-model="q"
                        type="text"
                        placeholder="Search customers, pools, agents…"
                        class="h-14 w-full bg-transparent text-base outline-none placeholder:text-muted-foreground sm:h-12 sm:text-sm"
                        aria-label="Search"
                    />
                    <kbd class="hidden shrink-0 rounded border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground sm:inline-block">Esc</kbd>
                    <button type="button" class="shrink-0 px-1 text-sm font-medium text-primary sm:hidden" @click="close">Cancel</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-2 sm:max-h-[60vh] sm:flex-none">
                    <p v-if="loading && !remote.length" class="px-2 py-3 text-sm text-muted-foreground">Searching…</p>
                    <template v-for="(g, gi) in groups" :key="g.label">
                        <p class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ g.label }}</p>
                        <button
                            v-for="(it, ii) in g.items"
                            :key="it.type + it.url"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-3 text-left sm:py-2"
                            :class="itemIndex(gi, ii) === active ? 'bg-muted' : 'hover:bg-muted/60'"
                            @mousemove="active = itemIndex(gi, ii)"
                            @click="select(it)"
                        >
                            <span class="flex min-w-0 items-baseline gap-2">
                                <span class="truncate text-sm font-medium">{{ it.label }}</span>
                                <span v-if="it.sublabel" class="truncate text-xs text-muted-foreground">{{ it.sublabel }}</span>
                            </span>
                            <span class="shrink-0 text-[10px] uppercase tracking-wide text-muted-foreground">{{ it.type }}</span>
                        </button>
                    </template>
                    <p v-if="!loading && !flat.length" class="px-2 py-6 text-center text-sm text-muted-foreground">No matches.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
