<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';

interface Section {
    key: string;
    enabled: boolean;
    heading?: string | null;
    headline?: string | null;
}

defineProps<{
    sections: Section[];
    seo: { title: string; description: string; og_image: string | null };
}>();

const page = usePage();
const tenant = page.props.tenant as { name: string; logo_path: string | null } | null;
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

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-6 py-4">
                <img v-if="tenant?.logo_path" :src="`/storage/${tenant.logo_path}`" alt="" class="h-9 w-9 rounded object-cover" />
                <span class="text-lg font-semibold">{{ tenant?.name }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-16">
            <!-- P1 skeleton — the SectionRenderer + real section components land in P2. -->
            <ul class="space-y-3">
                <li v-for="s in sections" :key="s.key" class="rounded-lg border border-border p-4">
                    <span class="text-xs uppercase tracking-wide text-muted-foreground">{{ s.key }}</span>
                    <p class="font-medium">{{ s.heading ?? s.headline ?? s.key }}</p>
                </li>
            </ul>
        </main>
    </div>
</template>
