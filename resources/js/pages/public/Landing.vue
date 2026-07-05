<script setup lang="ts">
import LeadChatWidget from '@/components/landing/LeadChatWidget.vue';
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import { titleFontHref, titleStyle } from '@/components/landing/titleStyle';
import type { BrandContext, LiveData, SectionConfig, TitleConfig } from '@/components/landing/types';
import { useReveal } from '@/composables/useReveal';
import { Head, usePage } from '@inertiajs/vue3';
import { Facebook, Instagram, Linkedin, Twitter } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    sections: SectionConfig[];
    live: LiveData;
    seo: { title: string; description: string; og_image: string | null };
    chatbot?: boolean;
    title: TitleConfig;
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

// Footer social links (placeholders — no per-tenant contact details are shown).
const socials = [
    { label: 'Facebook', href: '#', icon: Facebook },
    { label: 'Instagram', href: '#', icon: Instagram },
    { label: 'X (Twitter)', href: '#', icon: Twitter },
    { label: 'LinkedIn', href: '#', icon: Linkedin },
];

// Header company title — styled from the saved config; text falls back to the tenant name.
const titleText = computed(() => props.title.text || brand.value.name);
const titleStyleStr = computed(() => titleStyle(props.title));
const titleFont = computed(() => titleFontHref(props.title));

useReveal();
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

    <div class="landing-scale min-h-screen bg-background text-foreground">
        <header class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4 sm:px-6">
                <a href="#hero" class="flex items-center gap-2">
                    <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-9 rounded object-contain" />
                    <span class="whitespace-nowrap" :style="titleStyleStr">{{ titleText }}</span>
                </a>
                <div class="flex items-center gap-3">
                    <a href="/login" class="hidden text-sm font-medium text-muted-foreground hover:text-foreground sm:inline">Customer login</a>
                    <a href="#contact" class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground">Contact us</a>
                </div>
            </div>
        </header>

        <main>
            <SectionRenderer :sections="sections" :live="live" :brand="brand" />
        </main>

        <LeadChatWidget v-if="showChat" :action="live.chatAction || `/public/${brand.slug}/chat`" :company="brand.name" />

        <footer class="bg-gray-900 text-gray-400">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div class="grid gap-12 text-center md:grid-cols-2 md:text-left lg:grid-cols-5">
                    <!-- Brand -->
                    <div class="lg:col-span-2">
                        <a href="#hero" class="mb-6 flex items-center justify-center gap-2 md:justify-start">
                            <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-8 rounded object-contain" />
                            <span class="text-2xl font-bold text-white">{{ brand.name }}</span>
                        </a>
                        <p class="mx-auto mb-6 max-w-md text-gray-400 md:mx-0">
                            Professional pool service — reliable weekly care and crystal-clear water you never have to think about.
                        </p>
                        <div class="flex justify-center gap-3 md:justify-start">
                            <a
                                v-for="s in socials"
                                :key="s.label"
                                :href="s.href"
                                :aria-label="s.label"
                                class="flex size-10 items-center justify-center rounded-full bg-gray-800 text-gray-400 transition-all hover:bg-gray-700 hover:text-white"
                            >
                                <component :is="s.icon" class="size-5" />
                            </a>
                        </div>
                    </div>
                    <!-- Explore -->
                    <div>
                        <h4 class="mb-5 text-lg font-semibold text-white">Explore</h4>
                        <ul class="space-y-3">
                            <li><a href="#services" class="transition-colors hover:text-white">Services</a></li>
                            <li><a href="#gallery" class="transition-colors hover:text-white">Our work</a></li>
                            <li><a href="#testimonials" class="transition-colors hover:text-white">Reviews</a></li>
                            <li><a href="#faq" class="transition-colors hover:text-white">FAQ</a></li>
                        </ul>
                    </div>
                    <!-- Get started -->
                    <div>
                        <h4 class="mb-5 text-lg font-semibold text-white">Get started</h4>
                        <ul class="space-y-3">
                            <li><a href="#contact" class="transition-colors hover:text-white">Get a free quote</a></li>
                            <li><a href="/login" class="transition-colors hover:text-white">Customer login</a></li>
                        </ul>
                    </div>
                    <!-- Legal -->
                    <div>
                        <h4 class="mb-5 text-lg font-semibold text-white">Legal</h4>
                        <ul class="space-y-3">
                            <li><a href="/privacy" class="transition-colors hover:text-white">Privacy Policy</a></li>
                            <li><a href="/terms" class="transition-colors hover:text-white">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Bottom bar -->
            <div class="border-t border-gray-800">
                <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                    <div class="flex flex-col items-center gap-2 text-sm text-gray-500 md:flex-row md:justify-between">
                        <p>© {{ year }} {{ brand.name }}. All rights reserved.</p>
                        <p>
                            Powered by
                            <a href="https://routepilot.pro" target="_blank" rel="noopener" class="text-gray-400 transition-colors hover:text-white"
                                >RoutePilot</a
                            >
                            — a
                            <a
                                href="https://punchlistlabs.com"
                                target="_blank"
                                rel="noopener"
                                class="text-gray-400 transition-colors hover:text-white"
                                >Punchlist Labs</a
                            >
                            product
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
