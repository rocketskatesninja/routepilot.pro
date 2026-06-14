<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Route as RouteIcon } from 'lucide-vue-next';

defineProps<{
    title: string;
    subtitle: string;
    updated: string;
    toc: { id: string; label: string }[];
}>();
</script>

<template>
    <div class="min-h-svh bg-muted/30 text-foreground">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2 text-lg font-bold">
                    <span class="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground"
                        ><RouteIcon class="size-4"
                    /></span>
                    <span><span class="text-primary">Route</span><span class="text-orange-500">Pilot</span></span>
                </Link>
                <Link href="/" class="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground">
                    <ArrowLeft class="size-4" /> Back to Home
                </Link>
            </div>
        </header>

        <!-- Hero -->
        <div class="px-4 py-12 text-center text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #0369a1 100%)">
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-sky-300">Legal</p>
            <h1 class="text-3xl font-bold sm:text-4xl">{{ title }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-sky-100/80">{{ subtitle }}</p>
            <p class="mt-3 text-xs text-sky-300/70">{{ updated }}</p>
        </div>

        <!-- Body -->
        <div class="mx-auto max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid lg:grid-cols-4">
            <aside class="hidden lg:block">
                <nav class="sticky top-20 rounded-xl border border-border bg-background p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Contents</p>
                    <ul class="space-y-0.5">
                        <li v-for="t in toc" :key="t.id">
                            <a
                                :href="`#${t.id}`"
                                class="block rounded px-2 py-1 text-sm text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary"
                                >{{ t.label }}</a
                            >
                        </li>
                    </ul>
                </nav>
            </aside>
            <main class="space-y-4 lg:col-span-3"><slot /></main>
        </div>

        <!-- Footer -->
        <footer class="border-t border-border bg-background py-6">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 sm:flex-row sm:px-6">
                <p class="text-sm text-muted-foreground">© {{ new Date().getFullYear() }} Punchlist Labs. All rights reserved.</p>
                <div class="flex gap-4 text-sm">
                    <Link href="/privacy" class="text-muted-foreground transition-colors hover:text-foreground">Privacy Policy</Link>
                    <Link href="/terms" class="text-muted-foreground transition-colors hover:text-foreground">Terms of Service</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
