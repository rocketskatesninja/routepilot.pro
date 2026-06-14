<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Lock, RotateCw, Route as RouteIcon } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

// Carousel of real app screens — 5s each, slow crossfade.
const shots = [
    '/assets/images/screenshots/route-map.png',
    '/assets/images/screenshots/reports.png',
    '/assets/images/screenshots/dashboard.png',
    '/assets/images/screenshots/pools.png',
];
const current = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => (current.value = (current.value + 1) % shots.length), 5000);
});
onBeforeUnmount(() => clearInterval(timer));
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

            <!-- Product showcase: the real app, desktop + mobile -->
            <div class="relative z-10 my-8 flex flex-1 items-center justify-center">
                <div class="relative mx-auto w-full max-w-2xl">
                    <div class="overflow-hidden rounded-xl border border-white/15 shadow-2xl ring-1 ring-black/40 [rotate:-1.5deg]">
                        <div class="flex items-center gap-2 bg-slate-800 px-3 py-2 text-slate-400">
                            <ArrowLeft class="size-3.5" />
                            <ArrowRight class="size-3.5 opacity-40" />
                            <RotateCw class="size-3.5" />
                            <span
                                class="ml-1 flex flex-1 items-center gap-1.5 truncate rounded-full bg-slate-700/70 px-3 py-1 text-xs text-slate-300"
                            >
                                <Lock class="size-3 text-emerald-400" /> routepilot.pro
                            </span>
                        </div>
                        <div class="relative aspect-[16/10] bg-white">
                            <img
                                v-for="(s, i) in shots"
                                :key="s"
                                :src="s"
                                alt="RoutePilot app"
                                loading="lazy"
                                class="duration-[1200ms] absolute inset-0 size-full object-cover object-top transition-opacity ease-in-out"
                                :class="i === current ? 'opacity-100' : 'opacity-0'"
                            />
                        </div>
                    </div>
                    <img
                        src="/assets/images/screenshots/agent-mobile.png"
                        alt="RoutePilot mobile app"
                        loading="lazy"
                        class="absolute -bottom-6 right-2 w-24 rounded-[1.4rem] border-4 border-slate-800 shadow-2xl [rotate:4deg] sm:w-28"
                    />
                </div>
            </div>

            <div class="relative z-10 max-w-md">
                <p class="text-2xl font-bold leading-snug">Route smarter. Dose precisely. Get paid faster.</p>
                <p class="mt-3 text-sm text-sky-100/70">
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
