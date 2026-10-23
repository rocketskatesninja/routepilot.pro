<script setup lang="ts">
import { useSiteBrand } from '@/components/landing/brand';
import { frame } from '@/components/landing/layout';
import LeadChatWidget from '@/components/landing/LeadChatWidget.vue';
import { SOCIAL_LINKS } from '@/components/landing/meta';
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import SiteTitle from '@/components/landing/SiteTitle.vue';
import { siteHeadingVar, titleFontHref } from '@/components/landing/titleStyle';
import type { LiveData, SectionConfig, TitleConfig } from '@/components/landing/types';
import { hasContent, quoteAnchor } from '@/components/landing/visibility';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    sections: SectionConfig[];
    live: LiveData;
    seo: { title: string; description: string; og_image: string | null };
    chatbot?: boolean;
    title: TitleConfig;
    social?: Record<string, string>;
}>();

const brand = useSiteBrand();
const year = new Date().getFullYear();

// The chat widget renders client-only (it uses localStorage), when the company
// turned it on and the AI is configured and in quota.
const mounted = ref(false);
onMounted(() => (mounted.value = true));
const showChat = computed(() => mounted.value && props.chatbot === true && props.live.chatEnabled === true);

// Enabled sections with something to show; the nav, footer and buttons only link to these.
const shown = computed(() => props.sections.filter((s) => hasContent(s, props.live)));
const onPage = computed(() => shown.value.map((s) => s.key));
const NAV = [
    { key: 'services', label: 'Services' },
    { key: 'gallery', label: 'Our work' },
    { key: 'testimonials', label: 'Reviews' },
    { key: 'faq', label: 'FAQ' },
];
const nav = computed(() => NAV.filter((n) => onPage.value.includes(n.key)));
// "Get a quote" when there's a quote or booking form, else "Contact us" (if there's a contact form).
const headerCta = computed(() => {
    const href = quoteAnchor(onPage.value);
    if (href !== '#contact') return { href, label: 'Get a quote' };
    return onPage.value.includes('contact') ? { href, label: 'Contact us' } : null;
});

// Footer social links: the platforms the company set, in a fixed order.
const socials = computed(() => SOCIAL_LINKS.flatMap((l) => (props.social?.[l.value] ? [{ ...l, href: props.social[l.value] }] : [])));

// The company's title font: the header name (SiteTitle) and every section heading use it.
const titleFont = computed(() => titleFontHref(props.title));
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
        <meta v-if="seo.og_image" property="og:image" :content="seo.og_image" />
        <meta property="og:type" content="website" />
        <link v-if="titleFont" rel="stylesheet" :href="titleFont" />
    </Head>

    <div class="tenant-site bg-background text-foreground min-h-screen" :style="siteHeadingVar(title)">
        <header class="border-border bg-background/90 sticky top-0 z-40 border-b backdrop-blur">
            <div class="flex h-16 items-center justify-between gap-6" :class="frame">
                <a href="#hero" class="flex min-w-0 items-center gap-2">
                    <SiteTitle :title="title" :logo-url="brand.logoUrl" :name="brand.name" />
                </a>
                <nav class="flex items-center gap-7 text-base" aria-label="Site">
                    <a v-for="n in nav" :key="n.key" :href="`#${n.key}`" class="text-muted-foreground hover:text-foreground hidden @5xl:inline">{{
                        n.label
                    }}</a>
                    <a href="/login" class="text-muted-foreground hover:text-foreground hidden @3xl:inline">Customer login</a>
                    <a
                        v-if="headerCta"
                        :href="headerCta.href"
                        class="bg-primary text-primary-foreground rounded-lg px-4 py-2 font-semibold whitespace-nowrap hover:brightness-110"
                        >{{ headerCta.label }}</a
                    >
                </nav>
            </div>
        </header>

        <main>
            <SectionRenderer :sections="shown" :live="live" :brand="brand" />
        </main>

        <LeadChatWidget v-if="showChat" :action="live.chatAction || `/public/${brand.slug}/chat`" :company="brand.name" />

        <footer class="bg-[#0b2230] text-slate-400">
            <div class="grid gap-12 py-16 @3xl:grid-cols-2 @5xl:grid-cols-5" :class="frame">
                <div class="@5xl:col-span-2">
                    <a href="#hero" class="mb-5 flex items-center gap-2">
                        <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-8 rounded object-contain" />
                        <span class="site-font text-2xl font-bold text-white">{{ brand.name }}</span>
                    </a>
                    <p class="max-w-md">Professional pool service — reliable weekly care and crystal-clear water you never have to think about.</p>
                    <div v-if="socials.length" class="mt-6 flex gap-3">
                        <a
                            v-for="s in socials"
                            :key="s.label"
                            :href="s.href"
                            :aria-label="s.label"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex size-10 items-center justify-center rounded-full bg-white/10 text-slate-300 transition-colors hover:bg-white/20 hover:text-white"
                        >
                            <component :is="s.icon" class="size-5" />
                        </a>
                    </div>
                </div>
                <div v-if="nav.length">
                    <h2 class="mb-4 font-semibold text-white">Explore</h2>
                    <ul class="space-y-2.5">
                        <li v-for="n in nav" :key="n.key">
                            <a :href="`#${n.key}`" class="transition-colors hover:text-white">{{ n.label }}</a>
                        </li>
                    </ul>
                </div>
                <div>
                    <h2 class="mb-4 font-semibold text-white">Get started</h2>
                    <ul class="space-y-2.5">
                        <li v-if="headerCta">
                            <a :href="headerCta.href" class="transition-colors hover:text-white">{{ headerCta.label }}</a>
                        </li>
                        <li><a href="/login" class="transition-colors hover:text-white">Customer login</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="mb-4 font-semibold text-white">Legal</h2>
                    <ul class="space-y-2.5">
                        <li><a href="/privacy" class="transition-colors hover:text-white">Privacy Policy</a></li>
                        <li><a href="/terms" class="transition-colors hover:text-white">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="flex flex-col gap-2 py-6 text-sm text-slate-500 @3xl:flex-row @3xl:justify-between" :class="frame">
                    <p>© {{ year }} {{ brand.name }}. All rights reserved.</p>
                    <p>
                        Powered by
                        <a href="https://routepilot.pro" target="_blank" rel="noopener" class="text-slate-400 transition-colors hover:text-white"
                            >RoutePilot</a
                        >
                        — a
                        <a href="https://punchlistlabs.com" target="_blank" rel="noopener" class="text-slate-400 transition-colors hover:text-white"
                            >Punchlist Labs</a
                        >
                        product
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
