<script setup lang="ts">
import { useSiteBrand } from '@/components/landing/brand';
import { TEXTAREA } from '@/components/landing/editor/choice';
import GalleryEditor, { type RecentPhoto } from '@/components/landing/editor/GalleryEditor.vue';
import HeroEditor from '@/components/landing/editor/HeroEditor.vue';
import StatsEditor from '@/components/landing/editor/StatsEditor.vue';
import TeamEditor from '@/components/landing/editor/TeamEditor.vue';
import TitleEditor from '@/components/landing/editor/TitleEditor.vue';
import EditorField from '@/components/landing/editor/EditorField.vue';
import EditorItems from '@/components/landing/editor/EditorItems.vue';
import EditorPanel from '@/components/landing/editor/EditorPanel.vue';
import IconPicker from '@/components/landing/IconPicker.vue';
import LandingImagePicker from '@/components/landing/LandingImagePicker.vue';
import { SECTION_META, sectionHeading, SOCIAL_LINKS } from '@/components/landing/meta';
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import { allTitleFontsHref, siteHeadingVar, titleFontHref } from '@/components/landing/titleStyle';
import type { LiveData, SectionConfig, TitleConfig } from '@/components/landing/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { clone } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, ExternalLink, Eye, EyeOff, GripVertical, Loader2, Lock, RotateCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';

interface Agent {
    id: number;
    name: string;
    avatar: string | null;
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
// In the script, not the template: a // inside the social links' v-for makes vue-tsc 3.3 lose the loop variable.
const URL_PLACEHOLDER = 'https://…';
const photos = ref<RecentPhoto[]>(clone(props.recentPhotos));
const enabledDraft = computed(() => sections.value.filter((s) => s.enabled));

// The title font in use, plus every font so the font picker can show each in its own face.
const titleFont = computed(() => titleFontHref(title.value));
const allFontsHref = allTitleFontsHref();

// One panel open at a time: the hero, or Company title when linked here with ?panel=title (from Company settings).
const open = ref<string | null>(new URL(usePage().url, 'http://localhost').searchParams.get('panel') === 'title' ? '__title' : 'hero');
const toggle = (key: string) => (open.value = open.value === key ? null : key);
// A section's panel reads as it does on the page: its heading, else what it is.
const panelTitle = (s: SectionConfig) => sectionHeading(s) || SECTION_META[s.key]?.name || s.key;

const textarea = TEXTAREA;

// Section configs carry free-form fields (typed unknown); they are plain JSON, which Inertia posts as-is.
type PostData = Parameters<typeof router.post>[1];

const saving = ref(false);
const saved = ref(false);
function save() {
    saving.value = true;
    saved.value = false;
    router.post(
        '/company/landing',
        { sections: sections.value, seo: seo.value, theme: theme.value, title: title.value, social: social.value } as PostData,
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
                        <Input v-model="social[l.value]" type="url" :placeholder="URL_PLACEHOLDER" />
                    </EditorField>
                </EditorPanel>

                <EditorPanel title="Company title" data-panel="title" :open="open === '__title'" @toggle="toggle('__title')">
                    <TitleEditor v-model="title" />
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

                            <HeroEditor v-if="s.key === 'hero'" :section="s" />

                            <StatsEditor v-else-if="s.key === 'stats'" :section="s" :live="live" />

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

                            <GalleryEditor v-else-if="s.key === 'gallery'" :section="s" :photos="photos" />

                            <TeamEditor v-else-if="s.key === 'team'" :section="s" :agents="agents" />

                            <template v-else-if="s.key === 'service_area'">
                                <p
                                    v-if="typeof props.live.serviceArea?.lat !== 'number'"
                                    role="alert"
                                    class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400"
                                >
                                    The map isn’t showing: your business address hasn’t been placed on the map yet. Check it in
                                    <Link href="/company" class="font-medium underline underline-offset-4">Company settings</Link> and save it again.
                                </p>
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
