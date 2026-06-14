<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    ExternalLink,
    FileText,
    FlaskConical,
    LayoutGrid,
    MapPin,
    Move,
    Package,
    Route as RouteIcon,
    Users,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const isAuthed = computed(() => !!(page.props.auth as { user?: unknown } | undefined)?.user);

// Header turns from transparent (over the hero) to a solid blurred bar on scroll.
const scrolled = ref(false);
const onScroll = () => (scrolled.value = window.scrollY > 60);
onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

useReveal();

const features = [
    { icon: RouteIcon, title: 'Route Optimization', desc: 'Smart routing that minimizes drive time and maximizes stops per day.' },
    {
        icon: FlaskConical,
        title: 'Chemistry Intelligence',
        desc: 'AI-powered analysis using current readings, 12-visit trends, and 7-day weather forecasts.',
    },
    {
        icon: MapPin,
        title: 'Live Route Status',
        desc: 'See which stops are pending, in-progress, or completed across every agent — live map colors and per-stop badges.',
    },
    { icon: Users, title: 'Customer Portal', desc: 'Customers view their pool health, service history, and upcoming visits any time.' },
    { icon: Package, title: 'Inventory Management', desc: 'Track chemical stock with automatic usage deductions from each service visit.' },
    {
        icon: FileText,
        title: 'Service Reports',
        desc: 'Professional reports with photos and chemistry data — emailed to customers after every visit.',
    },
    {
        icon: LayoutGrid,
        title: 'Customizable Dashboard',
        desc: 'Drag-to-reorder widgets for stops, map, weather, and billing — every user builds their own view.',
    },
    {
        icon: Move,
        title: 'Drag-to-Reassign',
        desc: 'Drag a stop from one agent to another and the whole pool moves with it — every future visit follows.',
    },
];

const demoPoints = [
    'Branded landing page with hero & services',
    'Real service reports with photo slideshows',
    'Team profiles and customer testimonials',
    'Contact form with lead capture',
    'Fully customizable from the admin panel',
];

const pricingPoints = ['$0.50 / pool over 50', '$10 / agent over 2', '14-day free trial', 'No credit card required'];
</script>

<template>
    <Head title="Pool Service Management Software">
        <meta
            name="description"
            content="Pool service management software with route optimization, chemistry tracking, a customer portal, automated billing, and real-time monitoring. Built for pool pros."
        />
    </Head>

    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <!-- Header -->
        <header
            class="fixed inset-x-0 top-0 z-30 transition-all duration-300"
            :class="scrolled ? 'border-b border-border bg-background/90 shadow-sm backdrop-blur-md' : 'border-b border-transparent'"
        >
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6" aria-label="Primary">
                <Link href="/" class="flex items-center gap-2 text-2xl font-bold" aria-label="RoutePilot home">
                    <span class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <RouteIcon class="size-5" />
                    </span>
                    <span> <span :class="scrolled ? 'text-primary' : 'text-sky-300'">Route</span><span class="text-orange-500">Pilot</span> </span>
                </Link>

                <div class="hidden items-center gap-7 text-sm font-medium sm:flex" :class="scrolled ? 'text-muted-foreground' : 'text-sky-100/90'">
                    <a href="#features" class="transition-colors hover:text-foreground">Features</a>
                    <a href="#demo" class="transition-colors hover:text-foreground">Demo</a>
                    <a href="#pricing" class="transition-colors hover:text-foreground">Pricing</a>
                </div>

                <div class="flex items-center gap-3 sm:gap-5">
                    <Link
                        v-if="isAuthed"
                        :href="route('dashboard')"
                        class="inline-flex items-center rounded-lg bg-orange-500 px-3 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-orange-600"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-medium transition-colors"
                            :class="scrolled ? 'text-muted-foreground hover:text-foreground' : 'text-sky-100/90 hover:text-white'"
                        >
                            Sign In
                        </Link>
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center rounded-lg bg-orange-500 px-3 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-orange-600"
                        >
                            Start Free Trial
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <main class="flex-1">
            <!-- Hero -->
            <section
                class="relative flex min-h-svh items-center overflow-hidden px-4 pb-24 pt-28 sm:px-6"
                style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #0369a1 75%, #0ea5e9 100%)"
            >
                <div class="hero-dots pointer-events-none absolute inset-0"></div>
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        class="blob absolute rounded-full"
                        style="top: -10rem; right: -10rem; width: 32rem; height: 32rem; background: rgba(56, 189, 248, 0.08); filter: blur(48px)"
                    ></div>
                    <div
                        class="blob-2 absolute rounded-full"
                        style="bottom: -8rem; left: -8rem; width: 24rem; height: 24rem; background: rgba(96, 165, 250, 0.09); filter: blur(48px)"
                    ></div>
                    <div
                        class="blob-3 absolute rounded-full"
                        style="top: 33%; left: 50%; width: 18rem; height: 18rem; background: rgba(125, 211, 252, 0.06); filter: blur(48px)"
                    ></div>
                </div>

                <div class="relative z-10 mx-auto max-w-3xl text-center">
                    <span
                        class="hero-animate hero-d1 mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-sky-200"
                    >
                        <span class="size-1.5 rounded-full bg-orange-400"></span>
                        A Punchlist Labs Product
                    </span>
                    <h1 class="hero-animate hero-d2 font-bold leading-tight text-white" style="font-size: clamp(2.5rem, 6vw, 4.2rem)">
                        Manage your routes<br />
                        <span class="bg-gradient-to-br from-orange-300 to-orange-500 bg-clip-text text-transparent">like a pro</span>
                    </h1>
                    <p class="hero-animate hero-d3 mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-sky-100/80">
                        Route optimization, chemistry tracking, customer management, and real-time service monitoring — all in one platform built for
                        pool service companies.
                    </p>
                    <div class="hero-animate hero-d4 mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            :href="route('register')"
                            class="btn-glow inline-flex items-center gap-2 rounded-xl bg-orange-500 px-7 py-3.5 text-base font-bold text-white shadow-lg transition-colors hover:bg-orange-600"
                        >
                            Start Free Trial <ArrowRight class="size-5" />
                        </Link>
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center rounded-xl border-2 border-sky-200/30 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-white/10"
                        >
                            Sign In
                        </Link>
                    </div>
                    <p class="hero-animate hero-d5 mt-4 text-sm text-sky-300/60">14-day free trial · No credit card required · Cancel anytime</p>
                </div>

                <a href="#features" class="scroll-cue absolute bottom-8 left-1/2 z-10 -translate-x-1/2 text-white/50" aria-label="Scroll down">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                </a>
            </section>

            <!-- Features -->
            <section id="features" class="border-y border-border bg-background py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 class="reveal mx-auto mb-12 max-w-2xl text-center text-2xl font-bold sm:text-3xl">
                        Everything you need to run your pool service business
                    </h2>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(f, i) in features" :key="f.title" class="reveal text-center" :style="{ transitionDelay: `${(i % 4) * 0.08}s` }">
                            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <component :is="f.icon" class="size-6" />
                            </div>
                            <h3 class="mb-2 font-bold">{{ f.title }}</h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ f.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Demo -->
            <section id="demo" class="bg-muted/40 py-20">
                <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                    <div class="reveal overflow-hidden rounded-2xl border border-border bg-background shadow-xl">
                        <div class="flex items-center gap-2 border-b border-border bg-muted/60 px-3 py-2">
                            <span class="size-3 rounded-full bg-red-400"></span>
                            <span class="size-3 rounded-full bg-amber-400"></span>
                            <span class="size-3 rounded-full bg-green-400"></span>
                            <span class="ml-2 flex-1 rounded bg-background px-3 py-1 font-mono text-xs text-muted-foreground"
                                >demo.routepilot.pro</span
                            >
                        </div>
                        <div class="relative h-[420px] overflow-hidden">
                            <iframe
                                src="https://demo.routepilot.pro"
                                title="RoutePilot demo"
                                loading="lazy"
                                class="border-0"
                                style="width: 138.89%; height: 138.89%; transform: scale(0.72); transform-origin: top left"
                            ></iframe>
                            <a
                                href="https://demo.routepilot.pro"
                                target="_blank"
                                rel="noopener"
                                class="absolute inset-0 flex items-end justify-center pb-4"
                                style="background: linear-gradient(to top, rgba(15, 23, 42, 0.65) 0%, transparent 45%)"
                            >
                                <span
                                    class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-orange-600 shadow-lg"
                                >
                                    Open Full Demo <ExternalLink class="size-4" />
                                </span>
                            </a>
                        </div>
                    </div>

                    <div>
                        <p class="reveal mb-2 text-sm font-semibold uppercase tracking-widest text-orange-500">Live preview</p>
                        <h2 class="reveal mb-3 text-2xl font-bold sm:text-3xl" style="transition-delay: 0.1s">See it in action</h2>
                        <p class="reveal mb-6 leading-relaxed text-muted-foreground" style="transition-delay: 0.2s">
                            Explore Sunshine Pools — a fully working RoutePilot demo with real service data, chemistry reports, and everything your
                            future customers will experience.
                        </p>
                        <ul class="mb-6 space-y-2.5">
                            <li
                                v-for="(p, i) in demoPoints"
                                :key="p"
                                class="reveal flex items-center gap-2"
                                :style="{ transitionDelay: `${0.3 + i * 0.07}s` }"
                            >
                                <Check class="size-5 shrink-0 text-orange-500" />
                                <span>{{ p }}</span>
                            </li>
                        </ul>
                        <a
                            href="https://demo.routepilot.pro"
                            target="_blank"
                            rel="noopener"
                            class="reveal inline-flex items-center gap-2 rounded-xl bg-orange-500 px-6 py-3 font-bold text-white shadow transition-colors hover:bg-orange-600"
                            style="transition-delay: 0.6s"
                        >
                            Open Live Demo <ExternalLink class="size-4" />
                        </a>
                    </div>
                </div>
            </section>

            <!-- Pricing -->
            <section id="pricing" class="bg-background py-20">
                <div class="mx-auto max-w-xl px-4 text-center sm:px-6">
                    <h2 class="reveal mb-4 text-2xl font-bold sm:text-3xl">Simple, transparent pricing</h2>
                    <div class="reveal mt-4 rounded-2xl border border-border bg-background p-8 shadow-xl" style="transition-delay: 0.15s">
                        <p class="font-bold">
                            <span class="text-5xl">$34</span><span class="text-2xl">.99</span
                            ><span class="text-base font-medium text-muted-foreground">/mo</span>
                        </p>
                        <p class="mt-2 text-muted-foreground">Includes 50 pools and 2 agents</p>
                        <ul class="mx-auto mt-6 max-w-xs space-y-2.5 text-left text-sm">
                            <li v-for="p in pricingPoints" :key="p" class="flex items-center gap-2">
                                <Check class="size-5 shrink-0 text-green-500" />
                                <span>{{ p }}</span>
                            </li>
                        </ul>
                        <Link
                            :href="route('register')"
                            class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-orange-500 px-6 py-3.5 font-bold text-white shadow transition-colors hover:bg-orange-600"
                        >
                            Get Started
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-border bg-muted/40">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <div class="grid gap-8 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <span class="flex items-center gap-2 text-lg font-bold">
                            <span class="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground"
                                ><RouteIcon class="size-4"
                            /></span>
                            <span><span class="text-primary">Route</span><span class="text-orange-500">Pilot</span></span>
                        </span>
                        <p class="mt-3 max-w-xs text-sm leading-relaxed text-muted-foreground">
                            The complete pool service management platform for modern service companies. Built by Punchlist Labs.
                        </p>
                    </div>
                    <div>
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-foreground">Product</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#features" class="text-muted-foreground transition-colors hover:text-foreground">Features</a></li>
                            <li><a href="#pricing" class="text-muted-foreground transition-colors hover:text-foreground">Pricing</a></li>
                            <li>
                                <Link :href="route('register')" class="text-muted-foreground transition-colors hover:text-foreground"
                                    >Start Free Trial</Link
                                >
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-foreground">Account</h3>
                        <ul class="space-y-2 text-sm">
                            <li><Link :href="route('login')" class="text-muted-foreground transition-colors hover:text-foreground">Sign In</Link></li>
                            <li>
                                <Link :href="route('register')" class="text-muted-foreground transition-colors hover:text-foreground"
                                    >Create account</Link
                                >
                            </li>
                            <li><a href="#demo" class="text-muted-foreground transition-colors hover:text-foreground">Live demo</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 flex flex-col items-center gap-3 border-t border-border pt-6 sm:flex-row sm:justify-between">
                    <p class="text-sm text-muted-foreground">© {{ new Date().getFullYear() }} RoutePilot. All rights reserved.</p>
                    <div class="flex items-center gap-4 text-sm text-muted-foreground">
                        <Link href="/privacy" class="transition-colors hover:text-foreground">Privacy</Link>
                        <Link href="/terms" class="transition-colors hover:text-foreground">Terms</Link>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
