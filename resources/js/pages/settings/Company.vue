<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import ImageUpload from '@/components/ImageUpload.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
        unlocated: boolean; // a saved address that couldn't be placed on the map
        reply_to_email: string | null;
        report_messages: boolean;
    };
    mail: {
        host: string;
        port: number;
        encryption: string;
        username: string;
        from_address: string;
        from_name: string;
        has_password: boolean;
        saved: boolean; // settings are stored (switched on or not)
        active: boolean; // a test got through: the company's emails go out this way
        failure: { at: string; reason: string } | null; // the last time it couldn't send
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
    reply_to_email: props.company.reply_to_email ?? '',
    report_messages: props.company.report_messages,
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

// Saved: drop the staged file so the field shows the stored logo, not "applies when you save".
const submit = () => form.patch('/company', { preserveScroll: true, onSuccess: () => form.reset('logo') });

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
// Turning it on takes a test that gets through; turning it off sends from RoutePilot again.
const mailAction = useForm({});
const testMail = () => mailAction.post('/company/mail/test', { preserveScroll: true });
const turnOffMail = () => mailAction.post('/company/mail/off', { preserveScroll: true });
const failedOn = computed(() =>
    props.mail.failure ? new Date(props.mail.failure.at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '',
);

const connectForm = useForm({});
const connectStripe = () => connectForm.post('/company/connect', { preserveScroll: true });

// Left-hand tabs. Profile/address share one form (PATCH /company); email and
// payments are their own concerns. (AI is platform-managed by the super-admin.)
const tabs = computed(() => [
    { key: 'profile', label: 'Profile' },
    { key: 'address', label: 'Business address' },
    { key: 'email', label: 'Email' },
    ...(props.connect.available ? [{ key: 'payments', label: 'Payments' }] : []),
]);
// ?tab=email opens a tab directly (the "Your mail server" alert links there).
const asked = new URL(usePage().url, 'http://x').searchParams.get('tab');
const activeTab = ref(asked && ['profile', 'address', 'email', 'payments'].includes(asked) ? asked : 'profile');
const onCompanyTab = computed(() => ['profile', 'address'].includes(activeTab.value));
</script>

<template>
    <Head title="Company settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-6">
            <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside class="w-full max-w-xl lg:w-48">
                    <nav class="flex flex-col space-y-1 space-x-0">
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
                                    transparent
                                    :error="form.errors.logo"
                                    @update:model-value="(f) => (form.logo = f)"
                                />
                                <p class="text-muted-foreground text-sm">
                                    Shown on your emails, customer portal and website. To choose how it sits beside your name on your website, open
                                    <Link href="/company/landing?panel=title" class="text-primary underline-offset-4 hover:underline"
                                        >Landing page → Company title</Link
                                    >.
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="name">Company name</Label>
                                <Input id="name" v-model="form.name" />
                                <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="timezone">Timezone</Label>
                                    <select
                                        id="timezone"
                                        v-model="form.timezone"
                                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    >
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
                                    <input id="brand" v-model="form.brand_color" type="color" class="border-input h-9 w-12 rounded border" />
                                    <Input v-model="form.brand_color" class="max-w-32" />
                                </div>
                                <p v-if="form.errors.brand_color" class="text-sm text-red-600">{{ form.errors.brand_color }}</p>
                            </div>

                            <HeadingSmall title="Customer emails" description="How customers reach you from the emails you send them." />
                            <div class="grid gap-2">
                                <Label for="reply_to">Replies go to</Label>
                                <Input
                                    id="reply_to"
                                    v-model="form.reply_to_email"
                                    type="email"
                                    placeholder="office@yourcompany.com"
                                    class="max-w-md"
                                />
                                <p v-if="form.errors.reply_to_email" class="text-sm text-red-600">{{ form.errors.reply_to_email }}</p>
                                <p v-else class="text-muted-foreground text-sm">
                                    When a customer hits Reply on one of your emails, it lands here. Left blank, replies go to RoutePilot instead of
                                    you.
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="report_messages" class="flex items-center gap-2 font-normal">
                                    <Checkbox id="report_messages" v-model:checked="form.report_messages" />
                                    <span>Let customers message you from their visit reports</span>
                                </Label>
                                <p class="text-muted-foreground pl-6 text-sm">
                                    Each report gets a “Message {{ form.name || 'us' }}” button. Messages land in Requests, with the visit attached.
                                </p>
                            </div>
                        </div>

                        <div v-show="activeTab === 'address'" class="flex flex-col space-y-6">
                            <HeadingSmall title="Business address" description="Used to center your public service-area map." />
                            <p
                                v-if="props.company.unlocated"
                                role="alert"
                                class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400"
                            >
                                This address couldn’t be placed on the map, so your website’s “Where we serve” map can’t show. Check it’s complete,
                                then save it again. If it still won’t place, the map lookup itself is failing; let RoutePilot know.
                            </p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="addr1">Street address</Label>
                                    <Input id="addr1" v-model="form.address_line1" autocomplete="address-line1" />
                                    <p v-if="form.errors.address_line1" class="text-sm text-red-600">{{ form.errors.address_line1 }}</p>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="addr2">Suite / unit <span class="text-muted-foreground font-normal">(optional)</span></Label>
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
                                    <Input
                                        id="state"
                                        v-model="form.state"
                                        maxlength="2"
                                        placeholder="TX"
                                        class="uppercase"
                                        autocomplete="address-level1"
                                    />
                                    <p v-if="form.errors.state" class="text-sm text-red-600">{{ form.errors.state }}</p>
                                </div>
                                <div class="grid gap-2 sm:col-span-2">
                                    <Label for="zip">ZIP code</Label>
                                    <Input id="zip" v-model="form.postal_code" placeholder="78701" autocomplete="postal-code" />
                                    <p v-if="form.errors.postal_code" class="text-sm text-red-600">{{ form.errors.postal_code }}</p>
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
                            title="Your own mail server (SMTP)"
                            description="Send your customer emails (visit reports, reminders, invoices, receipts) and campaigns from your own mail server."
                        />
                        <p class="text-sm" role="status">
                            <template v-if="props.mail.active"
                                ><span class="font-medium text-emerald-700 dark:text-emerald-400">On.</span> Your emails go out from
                                {{ props.mail.from_address }}.</template
                            >
                            <template v-else-if="props.mail.saved"
                                ><span class="font-medium">Saved, not on yet.</span> Send a test email to turn it on.</template
                            >
                            <template v-else><span class="font-medium">Off.</span> Your emails go out from RoutePilot.</template>
                        </p>
                        <div
                            v-if="props.mail.failure"
                            role="alert"
                            class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-700 dark:text-amber-400"
                        >
                            On {{ failedOn }}: {{ props.mail.failure.reason }} Those emails went out from RoutePilot instead, so your customers still
                            got them.
                        </div>
                        <form class="space-y-6" @submit.prevent="submitMail">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="grid gap-2 sm:col-span-2">
                                    <Label for="host">Host</Label>
                                    <Input id="host" v-model="mailForm.host" placeholder="smtp.example.com" />
                                    <p v-if="mailForm.errors.host" class="text-sm text-red-600">{{ mailForm.errors.host }}</p>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="port">Port</Label><Input id="port" v-model="mailForm.port" type="number" class="max-w-32" />
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="enc">Encryption</Label>
                                    <select
                                        id="enc"
                                        v-model="mailForm.encryption"
                                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    >
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                    </select>
                                </div>
                                <div class="grid gap-2"><Label for="user">Username</Label><Input id="user" v-model="mailForm.username" /></div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="pass">Password</Label>
                                <PasswordInput
                                    id="pass"
                                    v-model="mailForm.password"
                                    autocomplete="new-password"
                                    :placeholder="props.mail.has_password ? '•••••••• (leave blank to keep)' : ''"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="fromaddr">From address</Label>
                                    <Input id="fromaddr" v-model="mailForm.from_address" type="email" required placeholder="hello@yourco.com" />
                                    <p v-if="mailForm.errors.from_address" class="text-sm text-red-600">{{ mailForm.errors.from_address }}</p>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="fromname">From name</Label><Input id="fromname" v-model="mailForm.from_name" />
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <Button type="submit" :disabled="mailForm.processing">Save mail settings</Button>
                                <Button v-if="props.mail.saved" type="button" variant="outline" :disabled="mailAction.processing" @click="testMail"
                                    >Send test email</Button
                                >
                                <Button v-if="props.mail.active" type="button" variant="ghost" :disabled="mailAction.processing" @click="turnOffMail"
                                    >Turn off</Button
                                >
                            </div>
                            <p v-if="props.mail.saved" class="text-muted-foreground text-sm">
                                The test goes to you. Saving changes turns it off until a test gets through again.
                            </p>
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
