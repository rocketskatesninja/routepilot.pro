<script setup lang="ts">
import { HERO_BADGES, MAX_BADGES } from '@/components/landing/badges';
import { useSiteBrand } from '@/components/landing/brand';
import { choice } from '@/components/landing/editor/choice';
import EditorField from '@/components/landing/editor/EditorField.vue';
import EditorItems from '@/components/landing/editor/EditorItems.vue';
import EditorPanel from '@/components/landing/editor/EditorPanel.vue';
import Segmented from '@/components/landing/editor/Segmented.vue';
import FontPicker from '@/components/landing/FontPicker.vue';
import IconPicker from '@/components/landing/IconPicker.vue';
import { LANDING_ICONS } from '@/components/landing/icons';
import LandingImagePicker from '@/components/landing/LandingImagePicker.vue';
import { ALIGN_OPTIONS, HERO_BACKGROUNDS, HERO_EFFECTS, HERO_PRESETS, SECTION_META, sectionHeading, SOCIAL_LINKS } from '@/components/landing/meta';
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import SiteTitle from '@/components/landing/SiteTitle.vue';
import { STAT_METRICS, statOverrides } from '@/components/landing/stats';
import {
    allTitleFontsHref,
    siteHeadingVar,
    TITLE_DISPLAYS,
    TITLE_SHADOWS,
    TITLE_SIZES,
    TITLE_TRACKINGS,
    TITLE_WEIGHTS,
    titleFontHref,
} from '@/components/landing/titleStyle';
import type { LiveData, SectionConfig, TitleConfig } from '@/components/landing/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { clone } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, ExternalLink, Eye, EyeOff, GripVertical, Loader2, Lock, RotateCw, Star } from 'lucide-vue-next';
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
interface Member {
    user_id: number;
    title: string | null;
    bio: string | null;
}

// The config arrives complete and clean (LandingConfig::forTenant), so every
// field a control binds to already exists.
const props = defineProps<{
    config: {
        sections: SectionConfig[];
        seo: { title: string | null; description: string | null; og_image: string | null };
        theme: { chatbot: boolean };
        title: TitleConfig;
        social: Record<string, string | null>;
    };
    ogImageUrl: string | null;
    live: LiveData;
    agents: Agent[];
    recentPhotos: RecentPhoto[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Landing page', href: '/company/landing' }];
const brand = useSiteBrand();

// The company's site (opened from the header) and the host its preview's address bar shows.
const host = new URL((usePage().props.ziggy as { url: string }).url).host;
const siteUrl = computed(() => `/t/${brand.value.slug}`);

const sections = ref<SectionConfig[]>(clone(props.config.sections));
const seo = ref(clone(props.config.seo));
const ogUrl = ref(props.ogImageUrl);
const theme = ref(clone(props.config.theme));
const title = ref<TitleConfig>(clone(props.config.title));
const social = ref(clone(props.config.social));
const photos = ref<RecentPhoto[]>(clone(props.recentPhotos));
const enabledDraft = computed(() => sections.value.filter((s) => s.enabled));

// The title font in use, plus every font so the font picker can show each in its own face.
const titleFont = computed(() => titleFontHref(title.value));
const allFontsHref = allTitleFontsHref();
// A solid title color is opt-in: null = the theme's text color.
const useCustomColor = computed({
    get: () => title.value.color !== null,
    set: (on: boolean) => (title.value.color = on ? (title.value.color ?? '#0f172a') : null),
});

// One panel open at a time: the hero, or Company title when linked here with ?panel=title (from Company settings).
const open = ref<string | null>(new URL(usePage().url, 'http://localhost').searchParams.get('panel') === 'title' ? '__title' : 'hero');
const toggle = (key: string) => (open.value = open.value === key ? null : key);
// A section's panel reads as it does on the page: its heading, else what it is.
const panelTitle = (s: SectionConfig) => sectionHeading(s) || SECTION_META[s.key]?.name || s.key;

const textarea = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
const select = 'border-input bg-background h-9 w-full rounded-md border px-2 text-sm';

// Add a key to a picked list (badges, stats), or take it off.
function flip(list: string[], key: string) {
    const i = list.indexOf(key);
    if (i >= 0) list.splice(i, 1);
    else list.push(key);
}

// A stat's own number: blank (or not a number) = the live count, which Reset also goes back to.
function setOverride(s: SectionConfig, key: string, raw: string | number) {
    const n = Number.parseInt(String(raw), 10);
    if (Number.isNaN(n) || n < 0) delete statOverrides(s)[key];
    else statOverrides(s)[key] = n;
}

// Team members: an agent is on the site once ticked, with an optional title and bio.
const memberOf = (s: SectionConfig, id: number) => (s.members as Member[]).find((m) => m.user_id === id);
function toggleMember(s: SectionConfig, id: number) {
    const members = s.members as Member[];
    const i = members.findIndex((m) => m.user_id === id);
    if (i >= 0) members.splice(i, 1);
    else members.push({ user_id: id, title: null, bio: null });
}

// Featuring a photo saves at once (it's live data, not part of the config). The tile flips
// straight away and back if the save fails; `async` keeps a quick run of taps from
// cancelling each other (each waited on its own visit before, so most were lost on screen).
function toggleShowcase(p: RecentPhoto) {
    const next = !p.is_showcase;
    p.is_showcase = next;
    router.post(
        `/photos/${p.id}/showcase`,
        { is_showcase: next },
        { async: true, preserveScroll: true, preserveState: true, onError: () => (p.is_showcase = !next) },
    );
}

const saving = ref(false);
const saved = ref(false);
function save() {
    saving.value = true;
    saved.value = false;
    router.post(
        '/company/landing',
        { sections: sections.value, seo: seo.value, theme: theme.value, title: title.value, social: social.value },
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
        <template #actions>
            <span v-if="saved" class="flex items-center gap-1 text-sm text-emerald-600"><Check class="size-4" /> Saved</span>
            <Button as="a" :href="siteUrl" target="_blank" rel="noopener" variant="outline"
                ><ExternalLink class="mr-1 size-4" /> View your site</Button
            >
            <Button :disabled="saving" @click="save"><Loader2 v-if="saving" class="mr-1 size-4 animate-spin" /> Save</Button>
        </template>

        <div class="flex h-full flex-1 gap-4 p-4">
            <!-- Editor -->
            <div class="flex w-full flex-col gap-3 overflow-y-auto xl:w-[30rem] xl:shrink-0">
                <div>
                    <h1 class="text-lg font-semibold">Landing page</h1>
                    <p class="text-muted-foreground text-xs">Edits preview live · saving keeps your place</p>
                </div>

                <EditorPanel title="SEO & social sharing" data-panel="seo" :open="open === '__seo'" @toggle="toggle('__seo')">
                    <EditorField label="Page title"><Input v-model="seo.title" placeholder="Acme Pools — Weekly Pool Service" /></EditorField>
                    <EditorField label="Description">
                        <textarea v-model="seo.description" rows="2" :class="textarea" placeholder="Reliable weekly pool service in…" />
                    </EditorField>
                    <EditorField label="Social share image">
                        <LandingImagePicker :url="ogUrl" label="image" @uploaded="(e) => ((seo.og_image = e.path), (ogUrl = e.url))" />
                    </EditorField>
                </EditorPanel>

                <EditorPanel title="Social links" data-panel="social" :open="open === '__social'" @toggle="toggle('__social')">
                    <p class="text-muted-foreground text-xs">Links for the footer icons. Leave a field blank to hide that icon.</p>
                    <EditorField v-for="l in SOCIAL_LINKS" :key="l.value" :label="l.label">
                        <Input v-model="social[l.value]" type="url" placeholder="https://…" />
                    </EditorField>
                </EditorPanel>

                <EditorPanel title="Company title" data-panel="title" :open="open === '__title'" @toggle="toggle('__title')">
                    <!-- Exactly what the site header shows. -->
                    <div class="border-border bg-background flex min-h-14 items-center justify-center overflow-hidden rounded-lg border px-4 py-3">
                        <SiteTitle :title="title" :logo-url="brand.logoUrl" :name="brand.name" />
                    </div>
                    <EditorField label="Title text" hint="Blank uses your company name.">
                        <Input v-model="title.text" placeholder="Your company name" />
                    </EditorField>
                    <EditorField label="Icon beside the name">
                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Icon beside the name">
                            <button
                                type="button"
                                :aria-pressed="title.icon === null"
                                class="h-10 rounded-md border px-3 text-xs"
                                :class="choice(title.icon === null)"
                                @click="title.icon = null"
                            >
                                None
                            </button>
                            <button
                                type="button"
                                :aria-pressed="title.icon === 'logo'"
                                class="flex h-10 items-center gap-1.5 rounded-md border px-2.5 text-xs"
                                :class="choice(title.icon === 'logo')"
                                @click="title.icon = 'logo'"
                            >
                                <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-6 rounded object-contain" /> Logo
                            </button>
                            <button
                                v-for="ic in LANDING_ICONS"
                                :key="ic.key"
                                type="button"
                                :title="ic.label"
                                :aria-label="ic.label"
                                :aria-pressed="title.icon === ic.key"
                                class="flex size-10 items-center justify-center rounded-md border"
                                :class="choice(title.icon === ic.key)"
                                @click="title.icon = ic.key"
                            >
                                <component :is="ic.icon" class="size-5" />
                            </button>
                        </div>
                        <!-- The logo itself lives in Company settings (emails and the portal use it too). -->
                        <template v-if="title.icon === 'logo'" #hint>
                            <template v-if="brand.logoUrl">
                                This is your company logo.
                                <Link href="/company" class="text-primary underline-offset-4 hover:underline">Change it in Company settings</Link>.
                            </template>
                            <template v-else>
                                No logo uploaded yet, so nothing shows.
                                <Link href="/company" class="text-primary underline-offset-4 hover:underline">Add one in Company settings</Link>, or
                                pick an icon.
                            </template>
                        </template>
                    </EditorField>
                    <EditorField label="Show in header"><Segmented v-model="title.display" :options="TITLE_DISPLAYS" /></EditorField>
                    <EditorField label="Size"><Segmented v-model="title.size" :options="TITLE_SIZES" /></EditorField>
                    <div class="grid grid-cols-2 gap-3">
                        <EditorField label="Font"><FontPicker v-model="title.font" /></EditorField>
                        <EditorField label="Shadow">
                            <select v-model="title.shadow" :class="select">
                                <option v-for="o in TITLE_SHADOWS" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </EditorField>
                        <EditorField label="Weight">
                            <select v-model="title.weight" :class="select">
                                <option v-for="o in TITLE_WEIGHTS" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </EditorField>
                        <EditorField label="Spacing">
                            <select v-model="title.tracking" :class="select">
                                <option v-for="o in TITLE_TRACKINGS" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </EditorField>
                    </div>
                    <EditorField label="Color">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5"><input v-model="title.color_type" type="radio" value="solid" /> Solid</label>
                            <label class="flex items-center gap-1.5"
                                ><input v-model="title.color_type" type="radio" value="gradient" /> Gradient</label
                            >
                        </div>
                        <div v-if="title.color_type === 'solid'" class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5"
                                ><input v-model="useCustomColor" type="checkbox" class="border-input size-4 rounded" /> Custom color</label
                            >
                            <input
                                v-if="title.color !== null"
                                v-model="title.color"
                                type="color"
                                class="border-input bg-background h-8 w-12 rounded border"
                            />
                        </div>
                        <div v-else class="flex items-center gap-2">
                            <input v-model="title.gradient_start" type="color" class="border-input h-8 w-9 rounded border" title="Start color" />
                            <input v-model="title.gradient_via" type="color" class="border-input h-8 w-9 rounded border" title="Middle color" />
                            <input v-model="title.gradient_end" type="color" class="border-input h-8 w-9 rounded border" title="End color" />
                            <label class="ml-auto flex items-center gap-1.5 text-xs">
                                Angle <Input v-model.number="title.gradient_angle" type="number" min="0" max="360" class="w-16" />
                            </label>
                        </div>
                    </EditorField>
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1.5"
                            ><input v-model="title.outline" type="checkbox" class="border-input size-4 rounded" /> Outline</label
                        >
                        <template v-if="title.outline">
                            <input v-model="title.outline_color" type="color" class="border-input h-8 w-9 rounded border" title="Outline color" />
                            <select v-model.number="title.outline_width" class="border-input bg-background h-8 rounded-md border px-2 text-sm">
                                <option :value="1">Thin</option>
                                <option :value="2">Medium</option>
                                <option :value="3">Thick</option>
                            </select>
                        </template>
                    </div>
                </EditorPanel>

                <EditorPanel title="AI chat assistant" data-panel="chat" :open="open === '__chat'" @toggle="toggle('__chat')">
                    <EditorField hint="Answers visitor questions and captures leads. Uses your AI allowance; appears only while AI is configured.">
                        <label class="flex items-center gap-2">
                            <input v-model="theme.chatbot" type="checkbox" class="border-input size-4 rounded" /> Show a lead-capture chat widget on
                            the public site
                        </label>
                    </EditorField>
                </EditorPanel>

                <!-- The page's sections: drag to reorder, the eye shows or hides one. -->
                <draggable v-model="sections" item-key="key" handle=".drag-handle" :animation="150" class="space-y-2">
                    <template #item="{ element: s }">
                        <EditorPanel :title="panelTitle(s)" :data-panel="s.key" :open="open === s.key" @toggle="toggle(s.key)">
                            <template #before>
                                <GripVertical class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab active:cursor-grabbing" />
                            </template>
                            <template #after>
                                <button
                                    type="button"
                                    :title="s.enabled ? 'Visible — click to hide' : 'Hidden — click to show'"
                                    :class="s.enabled ? 'text-primary' : 'text-muted-foreground'"
                                    @click="s.enabled = !s.enabled"
                                >
                                    <Eye v-if="s.enabled" class="size-4" /><EyeOff v-else class="size-4" />
                                </button>
                            </template>

                            <!-- Every section with a heading or a blurb gets these first. -->
                            <EditorField v-if="'heading' in s" label="Heading"><Input v-model="s.heading" /></EditorField>
                            <EditorField v-if="'blurb' in s" label="Blurb"><textarea v-model="s.blurb" rows="2" :class="textarea" /></EditorField>

                            <template v-if="s.key === 'hero'">
                                <EditorField label="Headline" hint="Press Enter to start a new line.">
                                    <textarea v-model="s.headline" rows="2" :class="textarea" />
                                </EditorField>
                                <EditorField label="Subheadline"><textarea v-model="s.subhead" rows="2" :class="textarea" /></EditorField>
                                <EditorField label="Text alignment"><Segmented v-model="s.align" :options="ALIGN_OPTIONS" /></EditorField>
                                <div class="grid grid-cols-2 gap-3">
                                    <EditorField label="Button text"><Input v-model="s.cta_label" /></EditorField>
                                    <EditorField label="Button target"><Input v-model="s.cta_anchor" placeholder="contact" /></EditorField>
                                </div>
                                <EditorField hint="Shown under the headline. Only pick what’s true of your business.">
                                    <template #label>Badges · {{ s.badges.length }} of {{ MAX_BADGES }}</template>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="b in HERO_BADGES"
                                            :key="b.key"
                                            type="button"
                                            :aria-pressed="s.badges.includes(b.key)"
                                            :disabled="!s.badges.includes(b.key) && s.badges.length >= MAX_BADGES"
                                            class="rounded-full border px-2.5 py-1 text-xs font-medium disabled:opacity-40"
                                            :class="choice(s.badges.includes(b.key))"
                                            @click="flip(s.badges, b.key)"
                                        >
                                            {{ b.label }}
                                        </button>
                                    </div>
                                    <button
                                        v-if="s.badges.length"
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground justify-self-start text-xs underline-offset-4 hover:underline"
                                        @click="s.badges = []"
                                    >
                                        Turn all off
                                    </button>
                                </EditorField>
                                <EditorField label="Headline size"><Segmented v-model="s.headline_size" :options="TITLE_SIZES" /></EditorField>
                                <EditorField :label="`Headline width · ${s.headline_max_width}rem`">
                                    <input v-model.number="s.headline_max_width" type="range" min="32" max="80" class="w-full" />
                                </EditorField>
                                <EditorField label="Background">
                                    <Segmented v-model="s.bg_type" :options="HERO_BACKGROUNDS" />
                                    <div v-if="s.bg_type === 'preset'" class="grid grid-cols-4 gap-2">
                                        <button
                                            v-for="p in HERO_PRESETS"
                                            :key="p"
                                            type="button"
                                            :aria-pressed="s.preset === p"
                                            class="overflow-hidden rounded-md border-2"
                                            :class="s.preset === p ? 'border-primary' : 'border-transparent'"
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
                                        :url="s.image_url"
                                        label="hero image"
                                        @uploaded="(e) => Object.assign(s, { image_path: e.path, image_url: e.url })"
                                    />
                                    <div v-else class="grid grid-cols-2 gap-3">
                                        <label class="grid gap-1 text-xs"
                                            >Top color <input v-model="s.gradient_start" type="color" class="border-input h-9 w-full rounded border"
                                        /></label>
                                        <label class="grid gap-1 text-xs"
                                            >Bottom color <input v-model="s.gradient_end" type="color" class="border-input h-9 w-full rounded border"
                                        /></label>
                                    </div>
                                </EditorField>
                                <EditorField label="Effects">
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                        <label v-for="fx in HERO_EFFECTS" :key="fx.value" class="flex items-center gap-2">
                                            <input v-model="s.effects[fx.value]" type="checkbox" /> {{ fx.label }}
                                        </label>
                                    </div>
                                    <label v-if="s.effects.dark_overlay" class="grid gap-1 text-xs">
                                        Overlay · {{ s.effects.overlay_opacity }}%
                                        <input v-model.number="s.effects.overlay_opacity" type="range" min="0" max="90" class="w-full" />
                                    </label>
                                </EditorField>
                            </template>

                            <template v-else-if="s.key === 'stats'">
                                <p class="text-muted-foreground text-xs">
                                    Tick the numbers to show. Each shows your live count unless you type your own; Reset goes back to the live count.
                                    A number at 0 stays hidden.
                                </p>
                                <EditorField label="Alignment"><Segmented v-model="s.align" :options="ALIGN_OPTIONS" /></EditorField>
                                <ul class="grid gap-2">
                                    <li v-for="m in STAT_METRICS" :key="m.key" class="flex items-center gap-2">
                                        <label class="flex min-w-0 flex-1 items-center gap-2">
                                            <input type="checkbox" :checked="s.metrics.includes(m.key)" @change="flip(s.metrics, m.key)" />
                                            <span class="truncate">{{ m.label }}</span>
                                        </label>
                                        <Input
                                            type="number"
                                            min="0"
                                            inputmode="numeric"
                                            class="h-8 w-28"
                                            :aria-label="`Your own number for ${m.label}`"
                                            :placeholder="`${live.stats?.[m.key] ?? 0} live`"
                                            :model-value="statOverrides(s)[m.key] ?? ''"
                                            @update:model-value="(v) => setOverride(s, m.key, v)"
                                        />
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-foreground w-10 text-xs disabled:invisible"
                                            :disabled="statOverrides(s)[m.key] === undefined"
                                            @click="setOverride(s, m.key, '')"
                                        >
                                            Reset
                                        </button>
                                    </li>
                                </ul>
                            </template>

                            <EditorItems
                                v-else-if="s.key === 'services'"
                                v-slot="{ item }"
                                v-model="s.items"
                                noun="Service"
                                :blank="{ title: '', body: '', icon: 'droplet' }"
                            >
                                <div class="flex items-center gap-2">
                                    <IconPicker v-model="item.icon" />
                                    <Input v-model="item.title" placeholder="Title" class="flex-1" />
                                </div>
                                <textarea v-model="item.body" rows="2" :class="textarea" placeholder="Description" />
                            </EditorItems>

                            <template v-else-if="s.key === 'gallery'">
                                <EditorField label="Max photos"
                                    ><Input v-model.number="s.limit" type="number" min="1" max="24" class="w-24"
                                /></EditorField>
                                <EditorField label="Featured photos" hint="Tap a photo to feature it on your site.">
                                    <p v-if="!photos.length" class="text-muted-foreground text-xs">
                                        No visit photos yet. Photos your team takes on visits show up here to feature.
                                    </p>
                                    <div v-else class="grid grid-cols-4 gap-2">
                                        <button
                                            v-for="p in photos"
                                            :key="p.id"
                                            type="button"
                                            :aria-pressed="p.is_showcase"
                                            class="relative aspect-square overflow-hidden rounded-md border-2"
                                            :class="p.is_showcase ? 'border-primary' : 'border-border'"
                                            @click="toggleShowcase(p)"
                                        >
                                            <img :src="p.url" alt="" class="h-full w-full object-cover" :class="p.is_showcase ? '' : 'opacity-60'" />
                                            <span
                                                v-if="p.is_showcase"
                                                class="bg-primary text-primary-foreground absolute top-1 right-1 rounded-full p-0.5"
                                                ><Star class="size-3"
                                            /></span>
                                        </button>
                                    </div>
                                </EditorField>
                            </template>

                            <template v-else-if="s.key === 'team'">
                                <p v-if="!agents.length" class="text-muted-foreground text-xs">No team members to show yet.</p>
                                <div v-for="a in agents" :key="a.id" class="border-border bg-background space-y-2 rounded-lg border p-3">
                                    <div class="flex items-center gap-3">
                                        <label class="flex flex-1 items-center gap-2 font-medium">
                                            <input type="checkbox" :checked="!!memberOf(s, a.id)" @change="toggleMember(s, a.id)" /> {{ a.name }}
                                        </label>
                                        <Input
                                            v-if="memberOf(s, a.id)"
                                            v-model="memberOf(s, a.id)!.title"
                                            placeholder="Title (e.g. Lead Technician)"
                                            class="flex-1"
                                        />
                                    </div>
                                    <textarea
                                        v-if="memberOf(s, a.id)"
                                        v-model="memberOf(s, a.id)!.bio"
                                        rows="2"
                                        :class="textarea"
                                        placeholder="Short bio"
                                    />
                                </div>
                            </template>

                            <template v-else-if="s.key === 'service_area'">
                                <EditorField label="Area caption"
                                    ><Input v-model="s.radius_label" placeholder="Serving a 25-mile radius"
                                /></EditorField>
                                <EditorField label="Center ZIP code (optional)">
                                    <Input v-model="s.zip" placeholder="e.g. 90210" class="w-40" />
                                    <template #hint>
                                        The map centers on your business address (set in
                                        <Link href="/company" class="text-primary">Company settings</Link>). Enter a ZIP code to center it there
                                        instead, to show a service area without revealing your exact address.
                                    </template>
                                </EditorField>
                            </template>

                            <EditorItems
                                v-else-if="s.key === 'testimonials'"
                                v-slot="{ item }"
                                v-model="s.items"
                                noun="Review"
                                :blank="{ quote: '', author: '', location: '' }"
                            >
                                <textarea v-model="item.quote" rows="2" :class="textarea" placeholder="Quote" />
                                <div class="grid grid-cols-2 gap-2">
                                    <Input v-model="item.author" placeholder="Name" /><Input v-model="item.location" placeholder="Location" />
                                </div>
                            </EditorItems>

                            <EditorItems v-else-if="s.key === 'faq'" v-slot="{ item }" v-model="s.items" noun="Question" :blank="{ q: '', a: '' }">
                                <Input v-model="item.q" placeholder="Question" />
                                <textarea v-model="item.a" rows="2" :class="textarea" placeholder="Answer" />
                            </EditorItems>

                            <template v-else-if="s.key === 'cta'">
                                <EditorField label="Headline"><Input v-model="s.headline" /></EditorField>
                                <div class="grid grid-cols-2 gap-3">
                                    <EditorField label="Button text"><Input v-model="s.button_label" /></EditorField>
                                    <EditorField label="Button target"><Input v-model="s.button_anchor" placeholder="contact" /></EditorField>
                                </div>
                            </template>

                            <p v-else-if="s.key === 'quote' || s.key === 'booking'" class="text-muted-foreground text-xs">
                                Uses your active services and their prices. Set prices under Services.
                            </p>
                        </EditorPanel>
                    </template>
                </draggable>
            </div>

            <!-- Live preview (faux browser frame) -->
            <aside class="sticky top-4 hidden flex-1 xl:block">
                <div class="border-border overflow-hidden rounded-xl border shadow-lg">
                    <div class="border-border bg-muted/50 text-muted-foreground flex items-center gap-2 border-b px-3 py-2 text-xs">
                        <ArrowLeft class="size-3.5" />
                        <ArrowRight class="size-3.5 opacity-40" />
                        <RotateCw class="size-3.5" />
                        <span class="bg-background ml-1 flex flex-1 items-center gap-1.5 truncate rounded-full px-3 py-1">
                            <Lock class="size-3 text-emerald-600" /> {{ host }}/t/{{ brand.slug }}
                        </span>
                    </div>
                    <div class="bg-background h-[calc(100vh-9rem)] overflow-y-auto">
                        <div class="preview-live tenant-site" :style="siteHeadingVar(title)">
                            <SectionRenderer :sections="enabledDraft" :live="live" :brand="brand" editing />
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
