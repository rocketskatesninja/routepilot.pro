<script setup lang="ts">
import SectionRenderer from '@/components/landing/SectionRenderer.vue';
import type { BrandContext, LiveData, SectionConfig } from '@/components/landing/types';
import { useReveal } from '@/composables/useReveal';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    sections: SectionConfig[];
    live: LiveData;
    seo: { title: string; description: string; og_image: string | null };
}>();

const page = usePage();
const tenant = page.props.tenant as { name: string; slug: string; logo_path: string | null; brand_color: string | null } | null;

const brand = computed<BrandContext>(() => ({
    name: tenant?.name ?? 'Pool Service',
    slug: tenant?.slug ?? '',
    logoUrl: tenant?.logo_path ? `/storage/${tenant.logo_path}` : null,
    color: tenant?.brand_color ?? null,
}));

const year = new Date().getFullYear();

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
    </Head>

    <div class="landing-scale min-h-screen bg-background text-foreground">
        <header class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4 sm:px-6">
                <a href="#hero" class="flex items-center gap-2">
                    <img v-if="brand.logoUrl" :src="brand.logoUrl" alt="" class="size-9 rounded object-contain" />
                    <span class="text-lg font-bold">{{ brand.name }}</span>
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

        <footer class="border-t border-border bg-muted/40">
            <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
                <div class="flex flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left">
                    <div>
                        <p class="font-bold">{{ brand.name }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">Professional pool service.</p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        © {{ year }} {{ brand.name }} · Powered by <a href="https://routepilot.pro" class="text-primary">RoutePilot</a>
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
