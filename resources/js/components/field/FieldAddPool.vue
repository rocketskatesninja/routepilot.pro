<script setup lang="ts">
import { isSignedOut, SIGNED_OUT } from '@/lib/field/session';
import { postJson } from '@/lib/http';
import { ChevronLeft, LoaderCircle, Plus, Search } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface PoolHit {
    id: number;
    name: string;
    customer: string | null;
    address: string | null;
}

/**
 * Pick a pool that isn't on today's route and add it to the end as an extra
 * visit. Needs a connection (the stop is made on the server); the visit itself
 * then works offline like any other.
 */
// The office adds to a tech's route on a picked day; a tech adds to their own today.
const props = defineProps<{ agent?: number | null; date?: string | null }>();
const emit = defineEmits<{ (e: 'added', stopId: number): void; (e: 'close'): void }>();

const search = ref('');
const pools = ref<PoolHit[]>([]);
const loading = ref(true);
const adding = ref<number | null>(null);
const error = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let latest = 0; // answers to searches that were typed over are dropped

async function find() {
    const ask = ++latest;
    loading.value = true;
    try {
        const res = await fetch(`/api/field/pools?search=${encodeURIComponent(search.value.trim())}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error(String(res.status));
        const data = (await res.json()) as { pools: PoolHit[] };
        if (ask !== latest) return;
        pools.value = data.pools;
        error.value = null;
    } catch {
        if (ask === latest) error.value = 'Couldn’t load your pools. Check your connection.';
    } finally {
        if (ask === latest) loading.value = false;
    }
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(find, 250);
});
onMounted(find);
onBeforeUnmount(() => clearTimeout(timer));

async function add(pool: PoolHit) {
    adding.value = pool.id;
    error.value = null;
    try {
        const res = await postJson(
            `/api/field/pools/${pool.id}/visit`,
            props.agent || props.date ? { agent: props.agent, date: props.date } : undefined,
        );
        if (isSignedOut(res)) {
            error.value = SIGNED_OUT;
            return;
        }
        if (!res.ok) throw new Error(String(res.status));
        emit('added', ((await res.json()) as { stop_id: number }).stop_id);
    } catch {
        error.value = `Couldn’t add ${pool.name}. Check your connection and try again.`;
    } finally {
        adding.value = null;
    }
}

const line = (p: PoolHit): string => [p.customer, p.address].filter(Boolean).join(' · ');
</script>

<template>
    <div
        class="bg-background text-foreground lg:border-border fixed inset-0 z-50 flex flex-col lg:relative lg:inset-auto lg:z-auto lg:h-full lg:overflow-hidden lg:rounded-2xl lg:border"
    >
        <header class="border-border bg-background flex items-center gap-2 border-b px-3 py-2.5">
            <button class="hover:bg-muted grid size-10 shrink-0 place-items-center rounded-xl" aria-label="Back to the route" @click="emit('close')">
                <ChevronLeft class="size-6" />
            </button>
            <div class="min-w-0 flex-1">
                <h1 class="font-display truncate text-xl leading-tight font-semibold tracking-tight">New report</h1>
                <p class="text-muted-foreground text-sm">Pick the pool. It’s added to the end of today’s route and isn’t billed.</p>
            </div>
        </header>

        <div class="border-border border-b p-3">
            <label class="relative block">
                <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Pool, customer or street"
                    aria-label="Find a pool"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground h-12 w-full rounded-xl border pr-3 pl-10 text-base"
                />
            </label>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <p v-if="error" role="alert" class="mb-3 rounded-xl bg-red-500/10 px-3 py-2 text-sm font-medium text-red-700 dark:text-red-400">
                {{ error }}
            </p>
            <div v-if="loading && !pools.length" class="text-muted-foreground flex justify-center py-12">
                <LoaderCircle class="size-7 animate-spin" />
            </div>
            <p v-else-if="!pools.length && !error" class="text-muted-foreground py-12 text-center">
                {{ search.trim() ? `No pools match “${search.trim()}”.` : 'No pools yet.' }}
            </p>
            <ul v-else-if="pools.length" class="border-border divide-border divide-y overflow-hidden rounded-2xl border">
                <li v-for="p in pools" :key="p.id">
                    <button
                        class="active:bg-muted/60 flex w-full items-center gap-3 p-4 text-left disabled:opacity-60"
                        :disabled="adding !== null"
                        @click="add(p)"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[17px] font-semibold">{{ p.name }}</p>
                            <p v-if="line(p)" class="text-muted-foreground truncate text-sm">{{ line(p) }}</p>
                        </div>
                        <LoaderCircle v-if="adding === p.id" class="text-muted-foreground size-5 shrink-0 animate-spin" />
                        <Plus v-else class="text-primary size-5 shrink-0" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
