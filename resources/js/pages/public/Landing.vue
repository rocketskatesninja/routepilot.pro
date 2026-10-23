<script setup lang="ts">
import { frame } from '@/components/landing/layout';
import LeadChatWidget from '@/components/landing/LeadChatWidget.vue';
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import { siteHeadingVar, titleFontHref, titleStyle } from '@/components/landing/titleStyle';
import type { BrandContext, LiveData, SectionConfig, TitleConfig } from '@/components/landing/types';
import { hasContent, quoteAnchor } from '@/components/landing/visibility';
import { Head, usePage } from '@inertiajs/vue3';
import { Facebook, Instagram, Linkedin, Twitter, Youtube } from 'lucide-vue-next';
import { computed, onMounted, ref, type Component } from 'vue';

const props = defineProps<{
    sections: SectionConfig[];
    live: LiveData;
    seo: { title: string; description: string; og_image: string | null };
    chatbot?: boolean;
    title: TitleConfig;
    social?: Partial<Record<'facebook' | 'instagram' | 'twitter' | 'linkedin' | 'youtube', string>>;
}>();

// Render the chat widget client-only (it uses localStorage) when the tenant
// enabled it AND the AI is actually configured + in-quota.
const mounted = ref(false);
onMounted(() => (mounted.value = true));
const showChat = computed(() => mounted.value && props.chatbot === true && props.live.chatEnabled === true);

const page = usePage();
const tenant = page.props.tenant as { name: string; slug: string; logo_path: string | null; brand_color: string | null } | null;

const brand = computed<BrandContext>(() => ({
    name: tenant?.name ?? 'Pool Service',
    slug: tenant?.slug ?? '',
    logoUrl: tenant?.logo_path ? `/storage/${tenant.logo_path}` : null,
    color: tenant?.brand_color ?? null,
}));

const year = new Date().getFullYear();

// Enabled sections with something to show; the nav, footer and buttons only link to these.
const shown = computed(() => props.sections.filter((s) => hasContent(s, props.live)));
const onPage = computed(() => shown.value.map((s) => s.key));
const NAV: { key: string; label: string }[] = [
    { key: 'services', label: 'Services' },
    { key: 'gallery', label: 'Our work' },
    { key: 'testimonials', label: 'Reviews' },
    { key: 'faq', label: 'FAQ' },
];
const nav = computed(() => NAV.filter((n) => onPage.value.includes(n.key)));
const hasQuote = computed(() => onPage.value.includes('quote') || onPage.value.includes('booking'));
const headerCta = computed(() =>
    hasQuote.value
        ? { href: quoteAnchor(onPage.value), label: 'Get a quote' }
        : onPage.value.includes('contact')
          ? { href: '#contact', label: 'Contact us' }
          : null,
);

// Footer social links — only the platforms the tenant configured, in a fixed order.
const socialMeta: { key: 'facebook' | 'instagram' | 'twitter' | 'linkedin' | 'youtube'; label: string; icon: Component }[] = [
    { key: 'facebook', label: 'Facebook', icon: Facebook },
    { key: 'instagram', label: 'Instagram', icon: Instagram },
    { key: 'twitter', label: 'X (Twitter)', icon: Twitter },
    { key: 'linkedin', label: 'LinkedIn', icon: Linkedin },
    { key: 'youtube', label: 'YouTube', icon: Youtube },
];
const socials = computed(() =>
    socialMeta.flatMap((s) => {
        const href = props.social?.[s.key];
        return href ? [{ label: s.label, href, icon: s.icon }] : [];
    }),
);

// Header company title — styled from the saved config; text falls back to the tenant name.
const titleText = computed(() => props.title.text || brand.value.name);
const titleStyleStr = computed(() => titleStyle(props.title));
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
                    <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-9 rounded object-contain" />
                    <span class="truncate" :style="titleStyleStr">{{ titleText }}</span>
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
