<script setup lang="ts">
import FontPicker from '@/components/landing/FontPicker.vue';
import IconPicker from '@/components/landing/IconPicker.vue';
import LandingImagePicker from '@/components/landing/LandingImagePicker.vue';
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import {
    allTitleFontsHref,
    TITLE_SHADOWS,
    TITLE_SIZES,
    TITLE_TRACKINGS,
    TITLE_WEIGHTS,
    titleFontHref,
    titleStyle,
} from '@/components/landing/titleStyle';
import type { BrandContext, LiveData, SectionConfig, TitleConfig } from '@/components/landing/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { clone } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, ChevronDown, Eye, EyeOff, GripVertical, Loader2, Lock, Plus, RotateCw, Star, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';

interface Agent {
    id: number;
    name: string;
    avatar: string | null;
}
interface RecentPhoto {
    id: number;
    url: string;
    is_showcase: boolean;
}

const props = defineProps<{
    config: {
        sections: SectionConfig[];
        seo: { title: string | null; description: string | null; og_image: string | null };
        theme: Record<string, unknown>;
        title: TitleConfig;
        social: Record<string, string | null>;
    };
    ogImageUrl: string | null;
    live: LiveData;
    agents: Agent[];
    recentPhotos: RecentPhoto[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Company', href: '/company' },
    { title: 'Landing page', href: '/company/landing' },
];

const sections = ref<SectionConfig[]>(clone(props.config.sections));
const seo = ref({ title: props.config.seo.title ?? '', description: props.config.seo.description ?? '', og_image: props.config.seo.og_image });
const ogUrl = ref(props.ogImageUrl);
const theme = ref(clone(props.config.theme));
const title = ref<TitleConfig>(clone(props.config.title));

// Footer social links (empty string = unset → sanitized to null on save).
const socialFields = [
    { key: 'facebook', label: 'Facebook' },
    { key: 'instagram', label: 'Instagram' },
    { key: 'twitter', label: 'X (Twitter)' },
    { key: 'linkedin', label: 'LinkedIn' },
    { key: 'youtube', label: 'YouTube' },
] as const;
const social = ref<Record<string, string>>(Object.fromEntries(socialFields.map((f) => [f.key, props.config.social?.[f.key] ?? ''])));
const photos = ref<RecentPhoto[]>(clone(props.recentPhotos));

// Load the selected title font into the editor so its live preview is accurate,
// plus every font so the dropdown can render each option in its own typeface.
const titleFont = computed(() => titleFontHref(title.value));
const allFontsHref = allTitleFontsHref();
// Solid-color opt-in: null = inherit the theme foreground (the default look).
const useCustomColor = computed({
    get: () => title.value.color !== null,
    set: (on: boolean) => {
        title.value.color = on ? (title.value.color ?? '#0f172a') : null;
    },
});

const expanded = ref<string | null>('hero');
const toggleOpen = (k: string) => (expanded.value = expanded.value === k ? null : k);

const page = usePage();
const tenant = page.props.tenant as { name: string; slug: string; logo_path: string | null; brand_color: string | null } | null;
const brand = computed<BrandContext>(() => ({
    name: tenant?.name ?? 'Pool Service',
    slug: tenant?.slug ?? '',
    logoUrl: tenant?.logo_path ? `/storage/${tenant.logo_path}` : null,
    color: tenant?.brand_color ?? null,
}));
const enabledDraft = computed(() => sections.value.filter((s) => s.enabled));

const TITLES: Record<string, string> = {
    hero: 'Hero',
    stats: 'Stats band',
    services: 'Services',
    quote: 'Instant quote',
    gallery: 'Photo gallery',
    team: 'Meet the team',
    service_area: 'Service area',
    booking: 'Online booking',
    testimonials: 'Testimonials',
    faq: 'FAQ',
    cta: 'Call to action',
    contact: 'Contact form',
};

// Each section's on-page heading text, so the builder list reads the same as the live page.
// These fallbacks mirror the section components' own defaults. hero/stats have no on-page
// heading and aren't listed here, so they keep their descriptive TITLES label.
const HEADING_DEFAULTS: Record<string, string> = {
    services: 'Our services',
    quote: 'Get an instant estimate',
    gallery: 'Recent work',
    team: 'Meet the team',
    service_area: 'Where we serve',
    booking: 'Request your first visit',
    testimonials: 'What our customers say',
    faq: 'Frequently asked questions',
    cta: 'Ready for a worry-free pool?',
    contact: 'Get in touch',
};

// Label a section by its actual on-page heading: the tenant's custom value if set, else the
// component default. CTA/hero store it as `headline`; every other section as `heading`.
function sectionLabel(s: SectionConfig): string {
    const fallback = HEADING_DEFAULTS[s.key];
    if (!fallback) return TITLES[s.key] || s.key;
    const field = s.key === 'cta' ? 'headline' : 'heading';
    const custom = typeof s[field] === 'string' ? (s[field] as string).trim() : '';
    return custom || fallback;
}
const taClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
const METRICS: [string, string][] = [
    ['pools_serviced', 'Pools serviced'],
    ['visits_completed', 'Visits completed'],
    ['years_active', 'Years in business'],
    ['happy_customers', 'Happy customers'],
    ['water_tests', 'Water tests'],
    ['gallons_maintained', 'Gallons maintained'],
    ['technicians', 'Expert technicians'],
];
const HERO_PRESETS = [
    'backyard',
    'cityscape',
    'infinity',
    'islands',
    'night',
    'patio',
    'resort',
    'skyline',
    'sunset',
    'tiles',
    'underwater',
    'water',
];
const strOr = (v: unknown, d: string): string => (typeof v === 'string' && v !== '' ? v : d);
const numOr = (v: unknown, d: number): number => (typeof v === 'number' ? v : d);
function fxOf(s: SectionConfig): Record<string, unknown> {
    if (typeof s.effects !== 'object' || s.effects === null || Array.isArray(s.effects)) {
        s.effects = {};
    }
    return s.effects as Record<string, unknown>;
}

// --- mutation helpers (operate on local draft state; preview is reactive) ---
function itemsOf(s: SectionConfig): Record<string, unknown>[] {
    if (!Array.isArray(s.items)) {
        s.items = [];
    }
    return s.items as Record<string, unknown>[];
}
const addItem = (s: SectionConfig, blank: Record<string, unknown>) => itemsOf(s).push({ ...blank });
const removeItem = (s: SectionConfig, i: number) => itemsOf(s).splice(i, 1);

function metricsOf(s: SectionConfig): string[] {
    if (!Array.isArray(s.metrics)) {
        s.metrics = [];
    }
    return s.metrics as string[];
}
function toggleMetric(s: SectionConfig, m: string) {
    const a = metricsOf(s);
    const i = a.indexOf(m);
    if (i >= 0) {
        a.splice(i, 1);
    } else {
        a.push(m);
    }
}

interface Member {
    user_id: number;
    title: string;
    bio: string;
}
function membersOf(s: SectionConfig): Member[] {
    if (!Array.isArray(s.members)) {
        s.members = [];
    }
    return s.members as Member[];
}
const memberOf = (s: SectionConfig, id: number) => membersOf(s).find((m) => m.user_id === id);
function toggleMember(s: SectionConfig, id: number) {
    const a = membersOf(s);
    const i = a.findIndex((m) => m.user_id === id);
    if (i >= 0) {
        a.splice(i, 1);
    } else {
        a.push({ user_id: id, title: '', bio: '' });
    }
}
function setMember(s: SectionConfig, id: number, field: 'title' | 'bio', val: string) {
    const m = memberOf(s, id);
    if (m) {
        m[field] = val;
    }
}

function onHeroUpload(s: SectionConfig, e: { path: string; url: string }) {
    s.image_path = e.path;
    s.image_url = e.url;
}
const heroUrl = (s: SectionConfig): string | null => (typeof s.image_url === 'string' ? s.image_url : null);
function onOgUpload(e: { path: string; url: string }) {
    seo.value.og_image = e.path;
    ogUrl.value = e.url;
}

// Gallery curation persists immediately (the showcase flag is live data).
function toggleShowcase(p: RecentPhoto) {
    const next = !p.is_showcase;
    router.post(
        `/photos/${p.id}/showcase`,
        { is_showcase: next },
        { preserveScroll: true, preserveState: true, onSuccess: () => (p.is_showcase = next) },
    );
}

const saving = ref(false);
const saved = ref(false);
function save() {
    saving.value = true;
    saved.value = false;
    router.post(
        '/company/landing',
        {
            sections: sections.value,
            seo: { title: seo.value.title, description: seo.value.description, og_image: seo.value.og_image },
            theme: theme.value,
            title: title.value,
            social: social.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saved.value = true;
                window.setTimeout(() => (saved.value = false), 2500);
            },
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Head title="Landing page">
        <link v-if="titleFont" rel="stylesheet" :href="titleFont" />
        <link rel="stylesheet" :href="allFontsHref" />
    </Head>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 gap-4 p-4">
            <!-- Editor -->
            <div class="flex w-full flex-col gap-3 overflow-y-auto xl:w-[30rem] xl:shrink-0">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h1 class="text-lg font-semibold">Landing page</h1>
                        <p class="text-xs text-muted-foreground">Edits preview live · saving keeps your place</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="saved" class="flex items-center gap-1 text-sm text-emerald-600"><Check class="size-4" /> Saved</span>
                        <Button :disabled="saving" @click="save"><Loader2 v-if="saving" class="mr-1 size-4 animate-spin" /> Save</Button>
                    </div>
                </div>

                <!-- SEO -->
                <div class="rounded-xl border border-border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                        @click="toggleOpen('__seo')"
                    >
                        SEO &amp; social sharing
                        <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="expanded === '__seo' ? 'rotate-180' : ''" />
                    </button>
                    <div v-show="expanded === '__seo'" class="space-y-3 border-t border-border bg-muted/20 p-4 text-sm">
                        <div class="grid gap-1">
                            <Label>Page title</Label><Input v-model="seo.title" placeholder="Acme Pools — Weekly Pool Service" />
                        </div>
                        <div class="grid gap-1">
                            <Label>Description</Label
                            ><textarea v-model="seo.description" rows="2" :class="taClass" placeholder="Reliable weekly pool service in…" />
                        </div>
                        <div class="grid gap-1">
                            <Label>Social share image</Label><LandingImagePicker :url="ogUrl" label="image" @uploaded="onOgUpload" />
                        </div>
                    </div>
                </div>

                <!-- Social links -->
                <div class="rounded-xl border border-border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                        @click="toggleOpen('__social')"
                    >
                        Social links
                        <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="expanded === '__social' ? 'rotate-180' : ''" />
                    </button>
                    <div v-show="expanded === '__social'" class="space-y-3 border-t border-border bg-muted/20 p-4 text-sm">
                        <p class="text-xs text-muted-foreground">Links for the footer icons — leave a field blank to hide that icon.</p>
                        <div v-for="f in socialFields" :key="f.key" class="grid gap-1">
                            <Label>{{ f.label }}</Label>
                            <Input v-model="social[f.key]" type="url" placeholder="https://…" />
                        </div>
                    </div>
                </div>

                <!-- Company title -->
                <div class="rounded-xl border border-border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                        @click="toggleOpen('__title')"
                    >
                        Company title
                        <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="expanded === '__title' ? 'rotate-180' : ''" />
                    </button>
                    <div v-show="expanded === '__title'" class="space-y-3 border-t border-border bg-muted/20 p-4 text-sm">
                        <!-- Live preview of the styled title, on a header-like background. -->
                        <div
                            class="flex min-h-14 items-center justify-center overflow-hidden rounded-lg border border-border bg-background px-4 py-3"
                        >
                            <span class="whitespace-nowrap" :style="titleStyle(title)">{{ title.text || 'Company Name' }}</span>
                        </div>

                        <div class="grid gap-1">
                            <Label>Title text <span class="font-normal text-muted-foreground">(blank = company name)</span></Label>
                            <Input v-model="title.text" placeholder="Your company name" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-1">
                                <Label>Font</Label>
                                <FontPicker v-model="title.font" />
                            </div>
                            <div class="grid gap-1">
                                <Label>Shadow</Label>
                                <select v-model="title.shadow" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
                                    <option v-for="o in TITLE_SHADOWS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="grid gap-1">
                                <Label>Size</Label>
                                <select v-model="title.size" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                    <option v-for="o in TITLE_SIZES" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label>Weight</Label>
                                <select v-model="title.weight" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                    <option v-for="o in TITLE_WEIGHTS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label>Spacing</Label>
                                <select v-model="title.tracking" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                    <option v-for="o in TITLE_TRACKINGS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-1">
                            <Label>Color</Label>
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-1.5"><input v-model="title.color_type" type="radio" value="solid" /> Solid</label>
                                <label class="flex items-center gap-1.5"
                                    ><input v-model="title.color_type" type="radio" value="gradient" /> Gradient</label
                                >
                            </div>
                        </div>
                        <div v-if="title.color_type === 'solid'" class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5"
                                ><input v-model="useCustomColor" type="checkbox" class="size-4 rounded border-input" /> Custom color</label
                            >
                            <input
                                v-if="title.color !== null"
                                v-model="title.color"
                                type="color"
                                class="h-8 w-12 rounded border border-input bg-background"
                            />
                        </div>
                        <div v-else class="flex items-center gap-2">
                            <input v-model="title.gradient_start" type="color" class="h-8 w-9 rounded border border-input" title="Start color" />
                            <input v-model="title.gradient_via" type="color" class="h-8 w-9 rounded border border-input" title="Middle color" />
                            <input v-model="title.gradient_end" type="color" class="h-8 w-9 rounded border border-input" title="End color" />
                            <div class="ml-auto flex items-center gap-1.5">
                                <Label class="text-xs">Angle</Label>
                                <Input v-model.number="title.gradient_angle" type="number" min="0" max="360" class="w-16" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5"
                                ><input v-model="title.outline" type="checkbox" class="size-4 rounded border-input" /> Outline</label
                            >
                            <template v-if="title.outline">
                                <input v-model="title.outline_color" type="color" class="h-8 w-9 rounded border border-input" title="Outline color" />
                                <select v-model.number="title.outline_width" class="h-8 rounded-md border border-input bg-background px-2 text-sm">
                                    <option :value="1">Thin</option>
                                    <option :value="2">Medium</option>
                                    <option :value="3">Thick</option>
                                </select>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- AI chat assistant -->
                <div class="rounded-xl border border-border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                        @click="toggleOpen('__chat')"
                    >
                        AI chat assistant
                        <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="expanded === '__chat' ? 'rotate-180' : ''" />
                    </button>
                    <div v-show="expanded === '__chat'" class="space-y-2 border-t border-border bg-muted/20 p-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="theme.chatbot" type="checkbox" class="size-4 rounded border-input" />
                            <span>Show a lead-capture chat widget on the public site</span>
                        </label>
                        <p class="text-xs text-muted-foreground">
                            Answers visitor questions and captures leads. Uses your AI allowance; appears only while AI is configured.
                        </p>
                    </div>
                </div>

                <!-- Sections -->
                <draggable v-model="sections" item-key="key" handle=".drag-handle" :animation="150" class="space-y-2">
                    <template #item="{ element: s }">
                        <div class="rounded-xl border border-border">
                            <div class="flex items-center gap-2 px-3 py-2.5">
                                <GripVertical class="drag-handle size-4 shrink-0 cursor-grab text-muted-foreground active:cursor-grabbing" />
                                <button type="button" class="flex flex-1 items-center gap-2 text-left text-sm font-medium" @click="toggleOpen(s.key)">
                                    {{ sectionLabel(s) }}
                                    <ChevronDown
                                        class="size-4 text-muted-foreground transition-transform"
                                        :class="expanded === s.key ? 'rotate-180' : ''"
                                    />
                                </button>
                                <button
                                    type="button"
                                    :title="s.enabled ? 'Visible — click to hide' : 'Hidden — click to show'"
                                    :class="s.enabled ? 'text-primary' : 'text-muted-foreground'"
                                    @click="s.enabled = !s.enabled"
                                >
                                    <Eye v-if="s.enabled" class="size-4" /><EyeOff v-else class="size-4" />
                                </button>
                            </div>

                            <div v-show="expanded === s.key" class="space-y-3 border-t border-border bg-muted/20 p-4 text-sm">
                                <!-- HERO -->
                                <template v-if="s.key === 'hero'">
                                    <div class="grid gap-1"><Label>Headline</Label><Input v-model="s.headline" /></div>
                                    <div class="grid gap-1"><Label>Subheadline</Label><textarea v-model="s.subhead" rows="2" :class="taClass" /></div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="grid gap-1"><Label>Button text</Label><Input v-model="s.cta_label" /></div>
                                        <div class="grid gap-1">
                                            <Label>Button target</Label><Input v-model="s.cta_anchor" placeholder="contact" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="grid gap-1">
                                            <Label>Headline size</Label>
                                            <div class="flex gap-1">
                                                <button
                                                    v-for="sz in ['sm', 'md', 'lg', 'xl']"
                                                    :key="sz"
                                                    type="button"
                                                    class="flex-1 rounded-md border py-1 text-xs uppercase"
                                                    :class="
                                                        (s.headline_size || 'lg') === sz
                                                            ? 'border-primary bg-primary/10 text-primary'
                                                            : 'border-border'
                                                    "
                                                    @click="s.headline_size = sz"
                                                >
                                                    {{ sz }}
                                                </button>
                                            </div>
                                        </div>
                                        <div class="grid gap-1">
                                            <Label>Width · {{ numOr(s.headline_max_width, 56) }}rem</Label>
                                            <input
                                                type="range"
                                                min="32"
                                                max="80"
                                                :value="numOr(s.headline_max_width, 56)"
                                                class="mt-2 w-full"
                                                @input="s.headline_max_width = Number(($event.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label>Background</Label>
                                        <div class="flex gap-1">
                                            <button
                                                v-for="[bt, bl] in [
                                                    ['preset', 'Preset'],
                                                    ['image', 'Upload'],
                                                    ['gradient', 'Gradient'],
                                                ]"
                                                :key="bt"
                                                type="button"
                                                class="flex-1 rounded-md border py-1 text-xs"
                                                :class="
                                                    (s.bg_type || 'preset') === bt ? 'border-primary bg-primary/10 text-primary' : 'border-border'
                                                "
                                                @click="s.bg_type = bt"
                                            >
                                                {{ bl }}
                                            </button>
                                        </div>
                                        <div v-if="(s.bg_type || 'preset') === 'preset'" class="grid grid-cols-4 gap-2">
                                            <button
                                                v-for="p in HERO_PRESETS"
                                                :key="p"
                                                type="button"
                                                class="overflow-hidden rounded-md border-2"
                                                :class="(s.preset || 'backyard') === p ? 'border-primary' : 'border-transparent'"
                                                @click="s.preset = p"
                                            >
                                                <img
                                                    :src="`/assets/images/hero-presets/${p}.jpg`"
                                                    :alt="p"
                                                    loading="lazy"
                                                    class="h-11 w-full object-cover"
                                                />
                                            </button>
                                        </div>
                                        <LandingImagePicker
                                            v-else-if="s.bg_type === 'image'"
                                            :url="heroUrl(s)"
                                            label="hero image"
                                            @uploaded="(e) => onHeroUpload(s, e)"
                                        />
                                        <div v-else class="grid grid-cols-2 gap-3">
                                            <div class="grid gap-1">
                                                <Label class="text-xs">Top color</Label
                                                ><input
                                                    type="color"
                                                    :value="strOr(s.gradient_start, '#0f172a')"
                                                    class="h-9 w-full rounded border border-input"
                                                    @input="s.gradient_start = ($event.target as HTMLInputElement).value"
                                                />
                                            </div>
                                            <div class="grid gap-1">
                                                <Label class="text-xs">Bottom color</Label
                                                ><input
                                                    type="color"
                                                    :value="strOr(s.gradient_end, '#0369a1')"
                                                    class="h-9 w-full rounded border border-input"
                                                    @input="s.gradient_end = ($event.target as HTMLInputElement).value"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label>Effects</Label>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                            <label class="flex items-center gap-2"
                                                ><input
                                                    type="checkbox"
                                                    :checked="!!fxOf(s).ken_burns"
                                                    @change="fxOf(s).ken_burns = ($event.target as HTMLInputElement).checked"
                                                />
                                                Ken Burns zoom</label
                                            >
                                            <label class="flex items-center gap-2"
                                                ><input
                                                    type="checkbox"
                                                    :checked="!!fxOf(s).cta_glow"
                                                    @change="fxOf(s).cta_glow = ($event.target as HTMLInputElement).checked"
                                                />
                                                Button glow</label
                                            >
                                            <label class="flex items-center gap-2"
                                                ><input
                                                    type="checkbox"
                                                    :checked="!!fxOf(s).scroll_cue"
                                                    @change="fxOf(s).scroll_cue = ($event.target as HTMLInputElement).checked"
                                                />
                                                Scroll-down arrow</label
                                            >
                                            <label class="flex items-center gap-2"
                                                ><input
                                                    type="checkbox"
                                                    :checked="!!fxOf(s).dark_overlay"
                                                    @change="fxOf(s).dark_overlay = ($event.target as HTMLInputElement).checked"
                                                />
                                                Dark overlay</label
                                            >
                                            <label class="flex items-center gap-2"
                                                ><input
                                                    type="checkbox"
                                                    :checked="!!fxOf(s).dot_matrix"
                                                    @change="fxOf(s).dot_matrix = ($event.target as HTMLInputElement).checked"
                                                />
                                                Dot matrix</label
                                            >
                                            <label class="flex items-center gap-2"
                                                ><input
                                                    type="checkbox"
                                                    :checked="!!fxOf(s).vignette"
                                                    @change="fxOf(s).vignette = ($event.target as HTMLInputElement).checked"
                                                />
                                                Vignette</label
                                            >
                                        </div>
                                        <div v-if="fxOf(s).dark_overlay">
                                            <Label class="text-xs">Overlay · {{ fxOf(s).overlay_opacity ?? 40 }}%</Label>
                                            <input
                                                type="range"
                                                min="0"
                                                max="90"
                                                :value="numOr(fxOf(s).overlay_opacity, 40)"
                                                class="mt-1 w-full"
                                                @input="fxOf(s).overlay_opacity = Number(($event.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                    </div>
                                </template>

                                <!-- STATS -->
                                <template v-else-if="s.key === 'stats'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div class="grid gap-1.5">
                                        <Label>Show metrics</Label>
                                        <label v-for="[mk, ml] in METRICS" :key="mk" class="flex items-center gap-2"
                                            ><input type="checkbox" :checked="metricsOf(s).includes(mk)" @change="toggleMetric(s, mk)" />
                                            {{ ml }}</label
                                        >
                                    </div>
                                </template>

                                <!-- SERVICES -->
                                <template v-else-if="s.key === 'services'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div v-for="(it, i) in itemsOf(s)" :key="i" class="space-y-2 rounded-lg border border-border bg-background p-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-muted-foreground">Service {{ i + 1 }}</span
                                            ><button type="button" @click="removeItem(s, i)">
                                                <Trash2 class="size-4 text-muted-foreground hover:text-red-600" />
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <IconPicker v-model="it.icon" />
                                            <Input v-model="it.title" placeholder="Title" class="flex-1" />
                                        </div>
                                        <textarea v-model="it.body" rows="2" :class="taClass" placeholder="Description" />
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="addItem(s, { title: '', body: '', icon: 'droplet' })"
                                        ><Plus class="mr-1 size-3.5" /> Add service</Button
                                    >
                                </template>

                                <!-- GALLERY -->
                                <template v-else-if="s.key === 'gallery'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div class="grid gap-1">
                                        <Label>Max photos</Label><Input v-model.number="s.limit" type="number" min="1" max="24" class="w-24" />
                                    </div>
                                    <div>
                                        <Label
                                            >Featured photos
                                            <span class="font-normal text-muted-foreground">(tap to feature on your site)</span></Label
                                        >
                                        <p v-if="!photos.length" class="mt-1 text-xs text-muted-foreground">
                                            No visit photos yet — photos your team uploads on visits will appear here to feature.
                                        </p>
                                        <div v-else class="mt-2 grid grid-cols-4 gap-2">
                                            <button
                                                v-for="p in photos"
                                                :key="p.id"
                                                type="button"
                                                class="relative aspect-square overflow-hidden rounded-md border-2"
                                                :class="p.is_showcase ? 'border-primary' : 'border-border'"
                                                @click="toggleShowcase(p)"
                                            >
                                                <img
                                                    :src="p.url"
                                                    alt=""
                                                    class="h-full w-full object-cover"
                                                    :class="p.is_showcase ? '' : 'opacity-60'"
                                                />
                                                <span
                                                    v-if="p.is_showcase"
                                                    class="absolute right-1 top-1 rounded-full bg-primary p-0.5 text-primary-foreground"
                                                    ><Star class="size-3"
                                                /></span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- TEAM -->
                                <template v-else-if="s.key === 'team'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <p v-if="!agents.length" class="text-xs text-muted-foreground">No team members to show yet.</p>
                                    <div v-for="a in agents" :key="a.id" class="rounded-lg border border-border bg-background p-3">
                                        <div class="flex items-center gap-3">
                                            <label class="flex flex-1 items-center gap-2 font-medium"
                                                ><input type="checkbox" :checked="!!memberOf(s, a.id)" @change="toggleMember(s, a.id)" />
                                                {{ a.name }}</label
                                            >
                                            <Input
                                                v-if="memberOf(s, a.id)"
                                                :model-value="memberOf(s, a.id)?.title ?? ''"
                                                placeholder="Title (e.g. Lead Technician)"
                                                class="flex-1"
                                                @update:model-value="(v) => setMember(s, a.id, 'title', String(v))"
                                            />
                                        </div>
                                        <div v-if="memberOf(s, a.id)" class="mt-2">
                                            <textarea
                                                :value="memberOf(s, a.id)?.bio ?? ''"
                                                rows="2"
                                                :class="taClass"
                                                placeholder="Short bio"
                                                @input="(e) => setMember(s, a.id, 'bio', (e.target as HTMLTextAreaElement).value)"
                                            />
                                        </div>
                                    </div>
                                </template>

                                <!-- SERVICE AREA -->
                                <template v-else-if="s.key === 'service_area'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div class="grid gap-1">
                                        <Label>Area caption</Label><Input v-model="s.radius_label" placeholder="Serving a 25-mile radius" />
                                    </div>
                                    <div class="grid gap-1">
                                        <Label>Center ZIP code <span class="font-normal text-muted-foreground">(optional)</span></Label>
                                        <Input v-model="s.zip" placeholder="e.g. 90210" class="w-40" />
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        The map centers on your business address (set in
                                        <a href="/company" class="text-primary">Company settings</a>). Enter a ZIP code to center it there instead —
                                        useful to show a service area without revealing your exact address.
                                    </p>
                                </template>

                                <!-- TESTIMONIALS -->
                                <template v-else-if="s.key === 'testimonials'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div v-for="(it, i) in itemsOf(s)" :key="i" class="space-y-2 rounded-lg border border-border bg-background p-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-muted-foreground">Review {{ i + 1 }}</span
                                            ><button type="button" @click="removeItem(s, i)">
                                                <Trash2 class="size-4 text-muted-foreground hover:text-red-600" />
                                            </button>
                                        </div>
                                        <textarea v-model="it.quote" rows="2" :class="taClass" placeholder="Quote" />
                                        <div class="grid grid-cols-2 gap-2">
                                            <Input v-model="it.author" placeholder="Name" /><Input v-model="it.location" placeholder="Location" />
                                        </div>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="addItem(s, { quote: '', author: '', location: '' })"
                                        ><Plus class="mr-1 size-3.5" /> Add review</Button
                                    >
                                </template>

                                <!-- FAQ -->
                                <template v-else-if="s.key === 'faq'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div v-for="(it, i) in itemsOf(s)" :key="i" class="space-y-2 rounded-lg border border-border bg-background p-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-muted-foreground">Q{{ i + 1 }}</span
                                            ><button type="button" @click="removeItem(s, i)">
                                                <Trash2 class="size-4 text-muted-foreground hover:text-red-600" />
                                            </button>
                                        </div>
                                        <Input v-model="it.q" placeholder="Question" />
                                        <textarea v-model="it.a" rows="2" :class="taClass" placeholder="Answer" />
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="addItem(s, { q: '', a: '' })"
                                        ><Plus class="mr-1 size-3.5" /> Add question</Button
                                    >
                                </template>

                                <!-- CTA -->
                                <template v-else-if="s.key === 'cta'">
                                    <div class="grid gap-1"><Label>Headline</Label><Input v-model="s.headline" /></div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="grid gap-1"><Label>Button text</Label><Input v-model="s.button_label" /></div>
                                        <div class="grid gap-1">
                                            <Label>Button target</Label><Input v-model="s.button_anchor" placeholder="contact" />
                                        </div>
                                    </div>
                                </template>

                                <!-- QUOTE / BOOKING (heading + blurb; both pull live service pricing) -->
                                <template v-else-if="s.key === 'quote' || s.key === 'booking'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div class="grid gap-1"><Label>Blurb</Label><textarea v-model="s.blurb" rows="2" :class="taClass" /></div>
                                    <p class="text-xs text-muted-foreground">
                                        Uses your active services and their prices. Set prices under Services.
                                    </p>
                                </template>

                                <!-- CONTACT -->
                                <template v-else-if="s.key === 'contact'">
                                    <div class="grid gap-1"><Label>Heading</Label><Input v-model="s.heading" /></div>
                                    <div class="grid gap-1"><Label>Blurb</Label><textarea v-model="s.blurb" rows="2" :class="taClass" /></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </draggable>
            </div>

            <!-- Live preview (faux browser frame) -->
            <aside class="sticky top-4 hidden flex-1 xl:block">
                <div class="overflow-hidden rounded-xl border border-border shadow-lg">
                    <div class="flex items-center gap-2 border-b border-border bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                        <ArrowLeft class="size-3.5" />
                        <ArrowRight class="size-3.5 opacity-40" />
                        <RotateCw class="size-3.5" />
                        <span class="ml-1 flex flex-1 items-center gap-1.5 truncate rounded-full bg-background px-3 py-1">
                            <Lock class="size-3 text-emerald-600" /> routepilot.pro/t/{{ brand.slug }}
                        </span>
                    </div>
                    <div class="h-[calc(100vh-9rem)] overflow-y-auto bg-background">
                        <div class="preview-live landing-scale">
                            <SectionRenderer :sections="enabledDraft" :live="live" :brand="brand" editing />
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
