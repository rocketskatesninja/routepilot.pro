<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    company: { name: string; timezone: string | null; brand_color: string | null; tax_rate_percent: number };
    ai: { provider: string; model: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Company', href: '/company' }];

const form = useForm({
    name: props.company.name,
    timezone: props.company.timezone ?? 'America/New_York',
    brand_color: props.company.brand_color ?? '#0ea5e9',
    tax_rate_percent: props.company.tax_rate_percent,
    ai_provider: props.ai.provider,
    ai_model: props.ai.model,
});

const timezones = [
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Phoenix',
    'America/Los_Angeles',
    'America/Anchorage',
    'Pacific/Honolulu',
];

const submit = () => form.patch('/company', { preserveScroll: true });
</script>

<template>
    <Head title="Company settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <h1 class="text-xl font-semibold">Company settings</h1>
            <p class="text-sm text-muted-foreground">Branding, timezone, sales tax, and AI configuration.</p>

            <form class="mt-5 space-y-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Company name</Label>
                    <Input id="name" v-model="form.name" />
                    <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="timezone">Timezone</Label>
                        <select id="timezone" v-model="form.timezone" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="tax">Sales tax %</Label>
                        <Input id="tax" v-model="form.tax_rate_percent" type="number" step="0.01" min="0" max="30" />
                        <p v-if="form.errors.tax_rate_percent" class="text-sm text-red-600">{{ form.errors.tax_rate_percent }}</p>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="brand">Brand color</Label>
                    <div class="flex items-center gap-2">
                        <input id="brand" v-model="form.brand_color" type="color" class="h-9 w-12 rounded border border-input" />
                        <Input v-model="form.brand_color" class="max-w-32" />
                    </div>
                    <p v-if="form.errors.brand_color" class="text-sm text-red-600">{{ form.errors.brand_color }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="provider">AI provider</Label>
                        <select id="provider" v-model="form.ai_provider" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="openai">OpenAI</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="model">AI model</Label>
                        <Input id="model" v-model="form.ai_model" placeholder="claude-haiku-4-5" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Save</Button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600">Saved.</span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
