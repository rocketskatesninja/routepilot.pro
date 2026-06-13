<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    company: {
        name: string;
        timezone: string | null;
        brand_color: string | null;
        tax_rate_percent: number;
        logo_url: string | null;
        address_line1: string | null;
        address_line2: string | null;
        city: string | null;
        state: string | null;
        postal_code: string | null;
    };
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
    connect: { available: boolean; connected: boolean; charges_enabled: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Company', href: '/company' }];

const form = useForm({
    name: props.company.name,
    timezone: props.company.timezone ?? 'America/New_York',
    brand_color: props.company.brand_color ?? '#0ea5e9',
    tax_rate_percent: props.company.tax_rate_percent,
    address_line1: props.company.address_line1 ?? '',
    address_line2: props.company.address_line2 ?? '',
    city: props.company.city ?? '',
    state: props.company.state ?? '',
    postal_code: props.company.postal_code ?? '',
    ai_provider: props.ai.provider,
    ai_model: props.ai.model,
    logo: null as File | null,
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

const connectForm = useForm({});
const connectStripe = () => connectForm.post('/company/connect', { preserveScroll: true });

// Left-hand tabs. Profile/address/AI share one form (PATCH /company); email and
// payments are their own concerns.
const tabs = computed(() => [
    { key: 'profile', label: 'Profile' },
    { key: 'address', label: 'Business address' },
    { key: 'ai', label: 'AI assistant' },
    { key: 'email', label: 'Email' },
    ...(props.connect.available ? [{ key: 'payments', label: 'Payments' }] : []),
]);
const activeTab = ref('profile');
const onCompanyTab = computed(() => ['profile', 'address', 'ai'].includes(activeTab.value));
</script>

<template>
    <Head title="Company settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-6">
            <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
                <aside class="w-full max-w-xl lg:w-48">
                    <nav class="flex flex-col space-x-0 space-y-1">
                        <Button
                            v-for="tab in tabs"
                            :key="tab.key"
                            variant="ghost"
                            :class="['w-full justify-start', { 'bg-muted': activeTab === tab.key }]"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </Button>
                    </nav>
                </aside>

                <Separator class="my-6 md:hidden" />

                <div class="flex-1 md:max-w-2xl">
                    <!-- Profile / address / AI all save together via PATCH /company -->
                    <form v-show="onCompanyTab" class="space-y-6" @submit.prevent="submit">
                        <div v-show="activeTab === 'profile'" class="flex flex-col space-y-6">
                            <HeadingSmall title="Company profile" description="Your brand, identity, and tax rate." />
                            <div class="grid gap-2">
                                <Label>Company logo</Label>
                                <ImageUpload
                                    :model-value="form.logo"
                                    :current="props.company.logo_url ?? null"
                                    @update:model-value="(f) => (form.logo = f)"
                                />
                            </div>
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
                                    <Input id="tax" v-model="form.tax_rate_percent" type="number" step="0.01" min="0" max="30" class="max-w-32" />
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
                        </div>

                        <div v-show="activeTab === 'address'" class="flex flex-col space-y-6">
                            <HeadingSmall title="Business address" description="Used to center your public service-area map." />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="addr1">Street address</Label>
                                    <Input id="addr1" v-model="form.address_line1" autocomplete="address-line1" />
                                    <p v-if="form.errors.address_line1" class="text-sm text-red-600">{{ form.errors.address_line1 }}</p>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="addr2">Suite / unit <span class="font-normal text-muted-foreground">(optional)</span></Label>
                                    <Input id="addr2" v-model="form.address_line2" autocomplete="address-line2" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-6">
                                <div class="grid gap-2 sm:col-span-3">
                                    <Label for="city">City</Label>
                                    <Input id="city" v-model="form.city" autocomplete="address-level2" />
                                    <p v-if="form.errors.city" class="text-sm text-red-600">{{ form.errors.city }}</p>
                                </div>
                                <div class="grid gap-2 sm:col-span-1">
                                    <Label for="state">State</Label>
                                    <Input id="state" v-model="form.state" maxlength="2" placeholder="TX" class="uppercase" autocomplete="address-level1" />
                                    <p v-if="form.errors.state" class="text-sm text-red-600">{{ form.errors.state }}</p>
                                </div>
                                <div class="grid gap-2 sm:col-span-2">
                                    <Label for="zip">ZIP code</Label>
                                    <Input id="zip" v-model="form.postal_code" placeholder="78701" autocomplete="postal-code" />
                                    <p v-if="form.errors.postal_code" class="text-sm text-red-600">{{ form.errors.postal_code }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-show="activeTab === 'ai'" class="flex flex-col space-y-6">
                            <HeadingSmall title="AI assistant" description="Powers AI-generated messages and summaries." />
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
                        </div>

                        <div class="flex items-center gap-3">
                            <Button type="submit" :disabled="form.processing">Save</Button>
                            <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600">Saved.</span>
                        </div>
                    </form>

                    <!-- Email -->
                    <div v-show="activeTab === 'email'" class="flex flex-col space-y-6">
                        <HeadingSmall
                            title="Outgoing email (SMTP)"
                            :description="
                                props.mail.active
                                    ? 'Active — campaigns + statements send from your own mail server.'
                                    : 'Send campaigns + statements from your own mail server.'
                            "
                        />
                        <form class="space-y-6" @submit.prevent="submitMail">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="grid gap-2 sm:col-span-2">
                                    <Label for="host">Host</Label>
                                    <Input id="host" v-model="mailForm.host" placeholder="smtp.example.com" />
                                    <p v-if="mailForm.errors.host" class="text-sm text-red-600">{{ mailForm.errors.host }}</p>
                                </div>
                                <div class="grid gap-2"><Label for="port">Port</Label><Input id="port" v-model="mailForm.port" type="number" class="max-w-32" /></div>
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

                    <!-- Payments -->
                    <div v-show="activeTab === 'payments'" class="flex flex-col space-y-6">
                        <HeadingSmall
                            title="Payments (Stripe)"
                            description="Connect your Stripe account so customer card payments are deposited to you (a small platform fee applies)."
                        />
                        <div class="flex items-center gap-3">
                            <span
                                v-if="props.connect.charges_enabled"
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-sm font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                ✓ Connected — payouts enabled
                            </span>
                            <template v-else>
                                <span v-if="props.connect.connected" class="text-sm text-amber-600 dark:text-amber-400">Onboarding incomplete</span>
                                <Button type="button" :disabled="connectForm.processing" @click="connectStripe">
                                    {{ props.connect.connected ? 'Finish Stripe setup' : 'Connect Stripe' }}
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
