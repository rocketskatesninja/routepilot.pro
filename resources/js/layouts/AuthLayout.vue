<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Lock, RotateCw, Route as RouteIcon } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

// Carousels of real app screens — 5s each, slow crossfade. Each frame has a
// light + dark capture; the dark one shows when the page is in dark mode.
const shots = ['dashboard', 'route-map', 'reports', 'pools'];
const phones = ['phone-day', 'phone-water', 'phone-portal'];
const current = ref(0);
const phoneCurrent = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    timer = setInterval(() => {
        current.value = (current.value + 1) % shots.length;
        phoneCurrent.value = (phoneCurrent.value + 1) % phones.length;
    }, 5000);
});
onBeforeUnmount(() => clearInterval(timer));

const light = (name: string) => `/assets/images/screenshots/${name}.png`;
const dark = (name: string) => `/assets/images/screenshots/dark/${name}.png`;
</script>

<template>
    <!-- The showcase side gets 60% so the screenshots can be big; the form keeps at least 30rem. -->
    <div class="grid min-h-svh lg:grid-cols-[minmax(0,3fr)_minmax(30rem,2fr)]">
        <!-- Brand panel (desktop) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex [@media(max-height:50rem)]:p-6"
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

            <!-- Product showcase: the real app, desktop + mobile, light/dark aware. Up to 50.4rem wide,
                 but never so tall that the panel outgrows the window: its padding, logo, margins,
                 tagline and the frame's browser bar take 18rem, and the screen is 16:10. On a short
                 window (a laptop) the padding tightens to 14.5rem, so the screenshots can stay big
                 without the page scrolling. -->
            <div class="relative z-10 my-8 flex flex-1 items-center justify-center [@media(max-height:50rem)]:my-5">
                <div
                    class="relative mx-auto w-full max-w-[min(50.4rem,calc((100svh-18rem)*1.6))] [@media(max-height:50rem)]:max-w-[min(50.4rem,calc((100svh-14.5rem)*1.6))]"
                >
                    <div class="[rotate:-1.5deg] overflow-hidden rounded-xl border border-white/15 shadow-2xl ring-1 ring-black/40">
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
                        <div class="relative aspect-[16/10] bg-white dark:bg-slate-900">
                            <template v-for="(name, i) in shots" :key="name">
                                <img
                                    :src="light(name)"
                                    alt="RoutePilot app"
                                    loading="lazy"
                                    class="absolute inset-0 size-full object-cover object-top transition-opacity duration-[1200ms] ease-in-out dark:hidden"
                                    :class="i === current ? 'opacity-100' : 'opacity-0'"
                                />
                                <img
                                    :src="dark(name)"
                                    alt="RoutePilot app"
                                    loading="lazy"
                                    class="absolute inset-0 hidden size-full object-cover object-top transition-opacity duration-[1200ms] ease-in-out dark:block"
                                    :class="i === current ? 'opacity-100' : 'opacity-0'"
                                />
                            </template>
                        </div>
                    </div>

                    <!-- phone carousel -->
                    <div
                        class="absolute right-2 -bottom-6 w-1/6 min-w-28 [rotate:4deg] overflow-hidden rounded-[1.4rem] border-4 border-slate-800 shadow-2xl"
                    >
                        <div class="relative aspect-[390/640] bg-white dark:bg-slate-900">
                            <template v-for="(name, i) in phones" :key="name">
                                <img
                                    :src="light(name)"
                                    alt="RoutePilot mobile"
                                    loading="lazy"
                                    class="absolute inset-0 size-full object-cover object-top transition-opacity duration-[1200ms] ease-in-out dark:hidden"
                                    :class="i === phoneCurrent ? 'opacity-100' : 'opacity-0'"
                                />
                                <img
                                    :src="dark(name)"
                                    alt="RoutePilot mobile"
                                    loading="lazy"
                                    class="absolute inset-0 hidden size-full object-cover object-top transition-opacity duration-[1200ms] ease-in-out dark:block"
                                    :class="i === phoneCurrent ? 'opacity-100' : 'opacity-0'"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Each line stays on one line: full size from 1280px up, scaled to the panel's width below that. -->
            <div class="@container relative z-10">
                <p class="text-[length:min(1.5rem,4.4cqi)] leading-snug font-bold whitespace-nowrap">
                    Route smarter. Dose precisely. Get paid faster.
                </p>
                <p class="mt-3 text-[length:min(0.875rem,2.2cqi)] whitespace-nowrap text-sky-100/70">
                    The complete pool service platform — routing, chemistry, billing, and a customer portal in one place.
                </p>
            </div>
        </div>

        <!-- Form panel -->
        <div class="bg-background flex flex-col items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-sm">
                <!-- Mobile logo -->
                <Link href="/" class="mb-8 flex items-center justify-center gap-2 text-2xl font-bold lg:hidden">
                    <span class="flex size-9 items-center justify-center rounded-md bg-orange-500 text-white">
                        <RouteIcon class="size-5" />
                    </span>
                    <span><span class="text-sky-500 dark:text-sky-300">Route</span><span class="text-orange-500">Pilot</span></span>
                </Link>

                <div class="mb-6 flex flex-col gap-2 text-center">
                    <h1 v-if="title" class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
