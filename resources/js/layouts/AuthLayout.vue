<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Route as RouteIcon } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- Brand panel (desktop) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex"
            style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #0369a1 75%, #0ea5e9 100%)"
        >
            <div class="hero-dots pointer-events-none absolute inset-0"></div>
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div
                    class="blob absolute rounded-full"
                    style="top: -8rem; right: -8rem; width: 26rem; height: 26rem; background: rgba(56, 189, 248, 0.1); filter: blur(48px)"
                ></div>
                <div
                    class="blob-2 absolute rounded-full"
                    style="bottom: -6rem; left: -6rem; width: 20rem; height: 20rem; background: rgba(96, 165, 250, 0.1); filter: blur(48px)"
                ></div>
            </div>

            <Link href="/" class="relative z-10 flex items-center gap-2 text-2xl font-bold">
                <span class="flex size-9 items-center justify-center rounded-md bg-white/15 ring-1 ring-white/20">
                    <RouteIcon class="size-5" />
                </span>
                <span><span class="text-sky-300">Route</span><span class="text-orange-400">Pilot</span></span>
            </Link>

            <div class="relative z-10 max-w-md">
                <p class="text-3xl font-bold leading-snug">Route smarter. Dose precisely. Get paid faster.</p>
                <p class="mt-4 text-sky-100/70">
                    The complete pool service platform — routing, chemistry, billing, and a customer portal in one place.
                </p>
            </div>
        </div>

        <!-- Form panel -->
        <div class="flex flex-col items-center justify-center bg-background p-6 sm:p-10">
            <div class="w-full max-w-sm">
                <!-- Mobile logo -->
                <Link href="/" class="mb-8 flex items-center justify-center gap-2 text-2xl font-bold lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <RouteIcon class="size-5" />
                    </span>
                    <span><span class="text-primary">Route</span><span class="text-orange-500">Pilot</span></span>
                </Link>

                <div class="mb-6 flex flex-col gap-2">
                    <h1 v-if="title" class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
