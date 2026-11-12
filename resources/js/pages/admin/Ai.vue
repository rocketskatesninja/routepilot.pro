<script setup lang="ts">
import PasswordInput from '@/components/PasswordInput.vue';
import Chip from '@/components/pane/Chip.vue';
import PaneSection from '@/components/pane/PaneSection.vue';
import PortalPageHeader from '@/components/portal/PortalPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface KeyStatus {
    configured: boolean;
    source: string;
    hint: string;
}

interface UsageRow {
    tenant: string;
    messages: number;
    limit: number;
    tokens: number;
    cost: number;
}

const props = defineProps<{
    defaults: { provider: string; model: string; default_quota: number };
    keys: Record<string, KeyStatus>;
    modelHints: Record<string, string>;
    usage: {
        month: string;
        rows: UsageRow[];
        total: { messages: number; tokens: number; cost: number };
        last_month: { messages: number; cost: number };
    };
}>();

// AI costs are fractions of a cent per question: show that detail until it adds up to a dollar.
const dollars = (n: number): string => `$${n.toFixed(n < 1 ? 4 : 2)}`;
const count = (n: number): string => n.toLocaleString();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI', href: '/platform/ai' }];

const providers = [
    { key: 'anthropic', label: 'Anthropic (Claude)' },
    { key: 'openai', label: 'OpenAI' },
];

// --- platform defaults + key rotation ---
const form = useForm({
    provider: props.defaults.provider,
    model: props.defaults.model,
    default_quota: props.defaults.default_quota,
    anthropic_key: '',
    openai_key: '',
});
// Switching provider swaps the model to that provider's default so it never
// stays "stuck" on the previous provider's model (e.g. claude-… under OpenAI).
watch(
    () => form.provider,
    (provider) => {
        form.model = props.modelHints[provider] ?? '';
    },
);

const save = () =>
    form.patch('/platform/ai', {
        preserveScroll: true,
        onSuccess: () => form.reset('anthropic_key', 'openai_key'),
    });
</script>

<template>
    <Head title="AI" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8 px-4 py-6">
            <PortalPageHeader
                title="Platform AI"
                summary="Every tenant’s assistant runs on the provider, model and key you set here. Tenants just get a monthly allowance."
            />

            <PaneSection :title="`This month's use (${usage.month})`">
                <p v-if="usage.rows.length === 0" class="text-muted-foreground border-border rounded-2xl border p-5 text-sm">
                    No questions answered yet this month.
                </p>
                <div v-else class="border-border overflow-x-auto rounded-2xl border">
                    <table class="w-full text-sm">
                        <thead class="text-muted-foreground border-border border-b text-left text-xs">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Company</th>
                                <th class="px-4 py-2.5 text-right font-medium">Questions</th>
                                <th class="px-4 py-2.5 text-right font-medium">Tokens</th>
                                <th class="px-4 py-2.5 text-right font-medium">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in usage.rows" :key="row.tenant" class="border-border border-b last:border-b-0">
                                <td class="px-4 py-2.5 font-medium">{{ row.tenant }}</td>
                                <td class="text-muted-foreground px-4 py-2.5 text-right tabular-nums">
                                    {{ count(row.messages) }} of {{ count(row.limit) }}
                                </td>
                                <td class="text-muted-foreground px-4 py-2.5 text-right tabular-nums">{{ count(row.tokens) }}</td>
                                <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ dollars(row.cost) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-muted/40">
                            <tr>
                                <td class="px-4 py-3 font-semibold">All companies</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ count(usage.total.messages) }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ count(usage.total.tokens) }}</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums">{{ dollars(usage.total.cost) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="text-muted-foreground mt-2 text-sm">
                    Last month: {{ count(usage.last_month.messages) }} questions, {{ dollars(usage.last_month.cost) }}. Only answered questions count;
                    costs use each model's list price.
                </p>
            </PaneSection>

            <form class="space-y-8" @submit.prevent="save">
                <PaneSection title="Which AI">
                    <div class="border-border grid gap-4 rounded-2xl border p-5 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="provider">Provider</Label>
                            <select id="provider" v-model="form.provider" class="border-input bg-background h-9 rounded-md border px-3 text-sm">
                                <option v-for="p in providers" :key="p.key" :value="p.key">{{ p.label }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="model">Model</Label>
                            <Input id="model" v-model="form.model" :placeholder="props.modelHints[form.provider] ?? ''" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="quota">Monthly allowance per tenant</Label>
                            <Input id="quota" v-model.number="form.default_quota" type="number" min="0" />
                        </div>
                    </div>
                </PaneSection>

                <PaneSection title="API keys">
                    <ul class="border-border divide-border divide-y rounded-2xl border">
                        <li v-for="p in providers" :key="p.key" class="grid items-center gap-3 p-4 sm:grid-cols-[12rem_1fr] sm:px-5">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ p.label }}</span>
                                <Chip :tone="props.keys[p.key]?.configured ? 'good' : 'muted'">{{
                                    props.keys[p.key]?.configured ? 'Set' : 'Not set'
                                }}</Chip>
                            </div>
                            <PasswordInput
                                v-model="(form as unknown as Record<string, string>)[`${p.key}_key`]"
                                autocomplete="off"
                                :aria-label="`${p.label} key`"
                                :placeholder="
                                    props.keys[p.key]?.configured
                                        ? `${props.keys[p.key].hint} (${props.keys[p.key].source}). Paste a new key to replace it.`
                                        : 'Paste a key'
                                "
                            />
                        </li>
                    </ul>
                    <p class="text-muted-foreground mt-2 text-sm">
                        Keys are encrypted and never shown in full. Leave a box empty to keep the current key.
                    </p>
                </PaneSection>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Save</Button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-400">Saved.</span>
                </div>
            </form>

            <p class="text-muted-foreground text-sm">
                Each tenant’s usage, allowance, on/off switch and own-provider setting are on their panel in
                <Link href="/people" class="text-primary font-medium underline-offset-4 hover:underline">People, under Tenants</Link>.
            </p>
        </div>
    </AppLayout>
</template>
