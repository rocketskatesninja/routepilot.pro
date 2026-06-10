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
    mail: {
        host: string;
        port: number;
        encryption: string;
        username: string;
        from_address: string;
        from_name: string;
        has_password: boolean;
        active: boolean;
    };
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

const mailForm = useForm({
    host: props.mail.host,
    port: props.mail.port,
    encryption: props.mail.encryption || 'tls',
    username: props.mail.username,
    password: '',
    from_address: props.mail.from_address,
    from_name: props.mail.from_name,
});
const submitMail = () => mailForm.patch('/company/mail', { preserveScroll: true, onSuccess: () => mailForm.reset('password') });
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

            <div class="mt-8 border-t border-border pt-6">
                <h2 class="font-medium">Outgoing email (SMTP)</h2>
                <p class="text-sm text-muted-foreground">
                    Send campaigns + statements from your own mail server.<span
                        v-if="props.mail.active"
                        class="text-emerald-600 dark:text-emerald-400"
                    >
                        · Active</span
                    >
                </p>
                <form class="mt-4 space-y-5" @submit.prevent="submitMail">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="host">Host</Label>
                            <Input id="host" v-model="mailForm.host" placeholder="smtp.example.com" />
                            <p v-if="mailForm.errors.host" class="text-sm text-red-600">{{ mailForm.errors.host }}</p>
                        </div>
                        <div class="grid gap-2"><Label for="port">Port</Label><Input id="port" v-model="mailForm.port" type="number" /></div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="enc">Encryption</Label>
                            <select id="enc" v-model="mailForm.encryption" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                        <div class="grid gap-2"><Label for="user">Username</Label><Input id="user" v-model="mailForm.username" /></div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="pass">Password</Label>
                        <Input
                            id="pass"
                            v-model="mailForm.password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="props.mail.has_password ? '•••••••• (leave blank to keep)' : ''"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="fromaddr">From address</Label>
                            <Input id="fromaddr" v-model="mailForm.from_address" type="email" placeholder="hello@yourco.com" />
                            <p v-if="mailForm.errors.from_address" class="text-sm text-red-600">{{ mailForm.errors.from_address }}</p>
                        </div>
                        <div class="grid gap-2"><Label for="fromname">From name</Label><Input id="fromname" v-model="mailForm.from_name" /></div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="mailForm.processing">Save mail settings</Button>
                        <span v-if="mailForm.recentlySuccessful" class="text-sm text-emerald-600">Saved.</span>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
