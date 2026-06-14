<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, KeyRound, XCircle } from 'lucide-vue-next';

interface KeyStatus {
    configured: boolean;
    source: string;
    hint: string;
}

const props = defineProps<{
    defaults: { provider: string; model: string; default_quota: number };
    keys: Record<string, KeyStatus>;
    modelHints: Record<string, string>;
}>();

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
const save = () =>
    form.patch('/platform/ai', {
        preserveScroll: true,
        onSuccess: () => form.reset('anthropic_key', 'openai_key'),
    });
</script>

<template>
    <Head title="AI" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4">
            <!-- Platform defaults -->
            <form class="rounded-xl border border-border p-5" @submit.prevent="save">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold">Platform AI</h2>
                    <p class="text-sm text-muted-foreground">
                        AI is bundled and platform-managed. The provider, model, and keys below power every tenant's assistant; tenants only get a
                        monthly allowance.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="provider">Provider</Label>
                        <select id="provider" v-model="form.provider" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="p in providers" :key="p.key" :value="p.key">{{ p.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="model">Model</Label>
                        <Input id="model" v-model="form.model" :placeholder="props.modelHints[form.provider] ?? ''" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="quota">Default monthly quota</Label>
                        <Input id="quota" v-model.number="form.default_quota" type="number" min="0" />
                    </div>
                </div>

                <!-- API keys -->
                <div class="mt-5 space-y-3">
                    <div class="flex items-center gap-2 text-sm font-medium"><KeyRound class="size-4" /> API keys</div>
                    <div v-for="p in providers" :key="p.key" class="grid items-center gap-2 sm:grid-cols-[10rem_1fr]">
                        <div class="flex items-center gap-1.5 text-sm">
                            <CheckCircle2 v-if="props.keys[p.key]?.configured" class="size-4 text-emerald-500" />
                            <XCircle v-else class="size-4 text-muted-foreground" />
                            <span>{{ p.label }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Input
                                v-model="(form as unknown as Record<string, string>)[`${p.key}_key`]"
                                type="password"
                                autocomplete="off"
                                :placeholder="
                                    props.keys[p.key]?.configured
                                        ? `Set — ${props.keys[p.key].hint} (${props.keys[p.key].source}). Enter a new key to rotate.`
                                        : 'Not configured — paste a key'
                                "
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Keys are encrypted at rest and never shown in full. Leave a field blank to keep the current key.
                    </p>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Save</Button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600">Saved.</span>
                </div>
            </form>

            <!-- Per-tenant -->
            <p class="text-sm text-muted-foreground">
                Per-tenant AI — usage, quota, on/off, and the override policy — lives on each company's detail panel under
                <Link href="/people" class="text-primary hover:underline">People → Tenants</Link>.
            </p>
        </div>
    </AppLayout>
</template>
