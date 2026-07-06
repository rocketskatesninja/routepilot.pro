<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Banknote,
    Bell,
    Bot,
    CalendarDays,
    Check,
    FileText,
    FlaskConical,
    Globe,
    LayoutGrid,
    Lock,
    Move,
    Package,
    RotateCw,
    Route as RouteIcon,
    Smartphone,
    Sparkles,
    Users,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    pricing?: {
        base_price: number;
        included_pools: number;
        included_agents: number;
        price_per_pool: number;
        price_per_agent: number;
    };
    faq?: { q: string; a: string }[];
    canonical?: string;
    ogImage?: string;
}>();

const page = usePage();
const isAuthed = computed(() => !!(page.props.auth as { user?: unknown } | undefined)?.user);

// Pricing comes from config/billing.php via the controller; fall back to today's plan.
const pricing = computed(
    () => props.pricing ?? { base_price: 34.99, included_pools: 50, included_agents: 2, price_per_pool: 0.5, price_per_agent: 10 },
);
const dollars = computed(() => Math.floor(pricing.value.base_price));
const cents = computed(() => String(Math.round((pricing.value.base_price - dollars.value) * 100)).padStart(2, '0'));
const pricingPoints = computed(() => [
    `$${pricing.value.price_per_pool.toFixed(2)} / pool over ${pricing.value.included_pools}`,
    `$${pricing.value.price_per_agent.toFixed(0)} / agent over ${pricing.value.included_agents}`,
    '14-day free trial',
    'No credit card required',
]);
const faq = computed(() => props.faq ?? []);

const demoUrl = computed(() => route('public.site', { tenant: 'demo' }));
const description =
    'All-in-one pool service management software that runs your whole business — route optimization, AI chemistry tracking, automated billing, a field app for technicians, and a branded customer website + portal. Built for pool cleaning businesses.';

// Header turns from transparent (over the hero) to a solid blurred bar on scroll.
const scrolled = ref(false);
const onScroll = () => (scrolled.value = window.scrollY > 60);
onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

useReveal();

// Honest capability proof — true product facts, framed as outcomes. No invented metrics.
const proof = [
    { icon: Globe, label: 'Your own branded customer website + portal' },
    { icon: FlaskConical, label: 'AI chemistry from 12-visit trends + 7-day weather' },
    { icon: RouteIcon, label: 'Routes optimized in seconds' },
    { icon: Smartphone, label: 'A field app that works offline' },
];

// The workflow story: scheduling → service → invoicing.
const steps = [
    {
        icon: CalendarDays,
        title: 'Plan the day',
        desc: 'Optimized routes cut windshield time. Drag a stop to another tech and the whole pool moves with it — every future visit follows.',
    },
    {
        icon: Smartphone,
        title: 'Work the route',
        desc: 'Techs get an offline-ready field app with on-device chemistry dosing and photo capture — no signal required at the pool.',
    },
    {
        icon: Banknote,
        title: 'Get paid',
        desc: 'Photo + chemistry reports auto-email after every visit. Invoices and autopay run themselves, so the money follows the work.',
    },
];

// Signature differentiators get top billing (the review: don't weight every feature equally).
const signature = [
    {
        icon: Globe,
        title: 'Your own customer website + portal',
        desc: 'Most pool CRMs stop at the back office. RoutePilot also gives you a branded marketing site and a customer portal where homeowners see their pool health, service history, and next visit — fewer “when are you coming?” calls, and a premium experience that keeps clients.',
        cta: true,
    },
    {
        icon: FlaskConical,
        title: 'AI chemistry that thinks for your techs',
        desc: 'Never lose a chemical record or rewrite a service note. RoutePilot reads 12-visit trends and the 7-day weather forecast and tells the tech exactly what to add — on-device, even offline.',
    },
    {
        icon: RouteIcon,
        title: 'Smart routing + live operations',
        desc: 'Pack more stops into fewer miles, then watch the day unfold live — pending, in-progress, and completed stops in real time across every truck on one map.',
    },
];

// The rest of the platform — still benefit-first, in a compact grid.
const features = [
    { icon: Bell, title: 'Live route status', desc: 'See every truck in real time — per-stop badges and live map colors across all agents.' },
    {
        icon: Banknote,
        title: 'Automated billing & autopay',
        desc: 'Invoices, receipts, and card-on-file autopay run on their own — chase fewer payments.',
    },
    { icon: Package, title: 'Inventory management', desc: 'Stop guessing on chemical stock — usage auto-deducts from every service visit.' },
    {
        icon: FileText,
        title: 'Professional service reports',
        desc: 'Photo + chemistry reports emailed after every visit — you look buttoned-up automatically.',
    },
    {
        icon: LayoutGrid,
        title: 'Build-your-own dashboard',
        desc: 'Drag-to-reorder widgets for stops, map, weather, and billing — every user builds their view.',
    },
    { icon: Bot, title: 'AI assistant', desc: 'Ask about a customer, a route, or pool chemistry and get an answer in plain English.' },
    { icon: Move, title: 'Drag-to-reassign', desc: 'Move a stop to another tech in one drag and every future visit follows automatically.' },
    { icon: Users, title: 'Customer management', desc: 'A pool company CRM built in — pools, contacts, notes, and history in one place.' },
];
</script>

<template>
    <Head title="Pool Service Management Software">
        <meta name="description" :content="description" />
        <link v-if="canonical" rel="canonical" :href="canonical" />
        <!-- Open Graph -->
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="RoutePilot" />
        <meta property="og:title" content="RoutePilot — pool service software + your own customer website" />
        <meta property="og:description" :content="description" />
        <meta v-if="canonical" property="og:url" :content="canonical" />
        <meta v-if="ogImage" property="og:image" :content="ogImage" />
        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="RoutePilot — pool service software + your own customer website" />
        <meta name="twitter:description" :content="description" />
        <meta v-if="ogImage" name="twitter:image" :content="ogImage" />
    </Head>

    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <!-- Header -->
        <header
            class="fixed inset-x-0 top-0 z-30 transition-all duration-300"
            :class="scrolled ? 'border-b border-border bg-background/90 shadow-sm backdrop-blur-md' : 'border-b border-transparent'"
        >
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6" aria-label="Primary">
                <Link href="/" class="flex items-center gap-2 text-2xl font-bold" aria-label="RoutePilot home">
                    <span class="flex size-8 items-center justify-center rounded-md bg-orange-500 text-white">
                        <RouteIcon class="size-5" />
                    </span>
                    <span> <span :class="scrolled ? 'text-sky-500' : 'text-sky-300'">Route</span><span class="text-orange-500">Pilot</span> </span>
                </Link>

                <div class="hidden items-center gap-7 text-sm font-medium sm:flex" :class="scrolled ? 'text-muted-foreground' : 'text-sky-100/90'">
                    <a href="#how" class="transition-colors hover:text-foreground">How it works</a>
                    <a href="#features" class="transition-colors hover:text-foreground">Features</a>
                    <a :href="demoUrl" target="_blank" class="transition-colors hover:text-foreground">Demo</a>
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

                <div class="relative z-10 mx-auto grid w-full max-w-7xl items-center gap-10 lg:grid-cols-[1fr_1.3fr] lg:gap-12">
                    <!-- Left: copy -->
                    <div class="text-center lg:text-left">
                        <span
                            class="hero-animate hero-d1 mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-sky-200"
                        >
                            <span class="size-1.5 rounded-full bg-orange-400"></span>
                            A Punchlist Labs Product
                        </span>
                        <h1 class="hero-animate hero-d2 font-bold leading-tight text-white" style="font-size: clamp(2.3rem, 5vw, 3.7rem)">
                            Run your whole pool business —<br />
                            <span class="bg-gradient-to-br from-orange-300 to-orange-500 bg-clip-text text-transparent">plus your own website.</span>
                        </h1>
                        <p class="hero-animate hero-d3 mx-auto mt-5 max-w-xl text-lg leading-relaxed text-sky-100/80 lg:mx-0">
                            Routing, chemistry, scheduling, and billing — and a branded customer website + portal your clients actually love. One
                            platform, built for pool service companies.
                        </p>
                        <div class="hero-animate hero-d4 mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                            <Link
                                :href="route('register')"
                                class="btn-glow inline-flex items-center gap-2 rounded-xl bg-orange-500 px-7 py-3.5 text-base font-bold text-white shadow-lg transition-colors hover:bg-orange-600"
                            >
                                Start Free Trial <ArrowRight class="size-5" />
                            </Link>
                            <a
                                :href="demoUrl"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 rounded-xl border-2 border-sky-200/30 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-white/10"
                            >
                                See the live demo <ArrowRight class="size-5" />
                            </a>
                        </div>
                        <p class="hero-animate hero-d5 mt-4 text-sm text-sky-300/60">14-day free trial · No credit card required · Cancel anytime</p>
                    </div>

                    <!-- Right: a preview of a real RoutePilot demo site (light/dark aware) -->
                    <div class="hero-animate hero-d3 hidden lg:block">
                        <div class="overflow-hidden rounded-xl border border-white/15 shadow-2xl ring-1 ring-black/20">
                            <div
                                class="flex items-center gap-2 border-b border-black/5 bg-slate-100 px-3 py-2 text-slate-400 dark:border-white/5 dark:bg-slate-800 dark:text-slate-400"
                            >
                                <ArrowLeft class="size-3.5" />
                                <ArrowRight class="size-3.5 opacity-40" />
                                <RotateCw class="size-3.5" />
                                <span
                                    class="ml-1 flex flex-1 items-center gap-1.5 truncate rounded-full bg-white px-3 py-1 text-xs text-slate-500 dark:bg-slate-700/70 dark:text-slate-300"
                                >
                                    <Lock class="size-3 text-emerald-600 dark:text-emerald-400" /> routepilot.pro/t/demo
                                </span>
                            </div>
                            <a
                                :href="demoUrl"
                                target="_blank"
                                rel="noopener"
                                class="group relative block h-[550px] overflow-hidden bg-white dark:bg-slate-900"
                                aria-label="Open the live demo pool service website"
                            >
                                <img
                                    src="/assets/images/screenshots/demo-landing.jpg"
                                    alt="A live pool service company website built and hosted on RoutePilot"
                                    class="absolute inset-0 size-full object-cover object-top dark:hidden"
                                />
                                <img
                                    src="/assets/images/screenshots/dark/demo-landing.jpg"
                                    alt="A live pool service company website built and hosted on RoutePilot"
                                    class="absolute inset-0 hidden size-full object-cover object-top dark:block"
                                />
                                <span class="absolute inset-0 flex items-end justify-center pb-4">
                                    <span
                                        class="translate-y-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-orange-600 opacity-0 shadow-lg transition-all group-hover:translate-y-0 group-hover:opacity-100"
                                    >
                                        Open the live demo →
                                    </span>
                                </span>
                            </a>
                        </div>
                        <p class="mt-3 text-center text-xs text-sky-300/60">A real customer-facing site, built and hosted on RoutePilot.</p>
                    </div>
                </div>
            </section>

            <!-- Proof band: honest capability facts framed as outcomes -->
            <section class="border-b border-border bg-muted/40">
                <div class="mx-auto grid max-w-6xl grid-cols-1 gap-x-6 gap-y-4 px-4 py-6 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <div v-for="p in proof" :key="p.label" class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="p.icon" class="size-5" />
                        </span>
                        <span class="text-sm font-medium leading-snug">{{ p.label }}</span>
                    </div>
                </div>
            </section>

            <!-- How it works: the scheduling → service → invoicing story -->
            <section id="how" class="bg-background py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="reveal mx-auto mb-14 max-w-2xl text-center">
                        <h2 class="text-2xl font-bold sm:text-3xl">From schedule to invoice, without the busywork</h2>
                        <p class="mt-3 text-muted-foreground">
                            Pool route software that carries a job from planning to payment — so your team spends less time on admin and more time on
                            pools.
                        </p>
                    </div>
                    <div class="grid gap-8 md:grid-cols-3">
                        <div
                            v-for="(s, i) in steps"
                            :key="s.title"
                            class="reveal relative rounded-2xl border border-border bg-card/40 p-6"
                            :style="{ transitionDelay: `${i * 0.1}s` }"
                        >
                            <span class="mb-4 flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <component :is="s.icon" class="size-6" />
                            </span>
                            <span class="absolute right-5 top-5 text-4xl font-black text-muted-foreground/15">{{ i + 1 }}</span>
                            <h3 class="mb-2 text-lg font-bold">{{ s.title }}</h3>
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ s.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Signature features: the differentiators, weighted heavier than the rest -->
            <section class="border-y border-border bg-muted/30 py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 class="reveal mx-auto mb-4 max-w-2xl text-center text-2xl font-bold sm:text-3xl">
                        The all-in-one pool service app — and the only one that hands you a website
                    </h2>
                    <p class="reveal mx-auto mb-12 max-w-2xl text-center text-muted-foreground">
                        Matching feature checklists isn't enough. These are the things RoutePilot does that make owners switch.
                    </p>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div
                            v-for="(s, i) in signature"
                            :key="s.title"
                            class="reveal flex flex-col rounded-2xl border border-border bg-background p-7 shadow-sm"
                            :class="i === 0 ? 'lg:row-span-1 lg:ring-1 lg:ring-primary/20' : ''"
                            :style="{ transitionDelay: `${i * 0.08}s` }"
                        >
                            <span class="mb-4 flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <component :is="s.icon" class="size-6" />
                            </span>
                            <h3 class="mb-2 text-lg font-bold">{{ s.title }}</h3>
                            <p class="flex-1 text-sm leading-relaxed text-muted-foreground">{{ s.desc }}</p>
                            <a
                                v-if="s.cta"
                                :href="demoUrl"
                                target="_blank"
                                rel="noopener"
                                class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline"
                            >
                                See a real site built on RoutePilot <ArrowRight class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Everything else -->
            <section id="features" class="bg-background py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <h2 class="reveal mx-auto mb-3 max-w-2xl text-center text-2xl font-bold sm:text-3xl">
                        Everything a pool service business needs, in one place
                    </h2>
                    <p class="reveal mx-auto mb-12 max-w-2xl text-center text-muted-foreground">
                        A pool company CRM, billing, inventory, and pool technician software — no more stitching together five disconnected tools.
                    </p>
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

            <!--
                Testimonial slot — drop in a REAL quote when available (e.g. Acme Pool Co);
                do not fabricate. Ready-to-use markup:
                <section class="bg-background py-20">
                    <figure class="reveal mx-auto max-w-3xl px-4 text-center sm:px-6">
                        <blockquote class="text-xl font-medium leading-relaxed sm:text-2xl">“REAL QUOTE HERE.”</blockquote>
                        <figcaption class="mt-5 text-sm text-muted-foreground">Name · Company</figcaption>
                    </figure>
                </section>
            -->

            <!-- Pricing -->
            <section id="pricing" class="border-t border-border bg-muted/30 py-20">
                <div class="mx-auto max-w-5xl px-4 sm:px-6">
                    <div class="reveal mx-auto mb-10 max-w-2xl text-center">
                        <h2 class="text-2xl font-bold sm:text-3xl">Simple, transparent pricing</h2>
                        <p class="mt-3 text-muted-foreground">One plan. Everything included. You only pay more as you grow.</p>
                    </div>

                    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                        <!-- Plan card -->
                        <div class="reveal rounded-2xl border border-border bg-background p-8 text-center shadow-xl">
                            <p class="font-bold">
                                <span class="text-5xl">${{ dollars }}</span
                                ><span class="text-2xl">.{{ cents }}</span
                                ><span class="text-base font-medium text-muted-foreground">/mo</span>
                            </p>
                            <p class="mt-2 text-muted-foreground">
                                Includes {{ pricing.included_pools }} pools and {{ pricing.included_agents }} agents
                            </p>
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
                            <p class="mt-3 text-xs text-muted-foreground">No credit card required · Cancel anytime</p>
                        </div>

                        <!-- What's included + FAQ -->
                        <div class="reveal" style="transition-delay: 0.12s">
                            <h3 class="mb-3 flex items-center gap-2 font-bold"><Sparkles class="size-4 text-primary" /> What's included</h3>
                            <ul class="mb-8 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                <li
                                    v-for="inc in [
                                        'Route optimization',
                                        'AI chemistry & dosing',
                                        'Scheduling & dispatch',
                                        'Customer website + portal',
                                        'Billing & autopay',
                                        'Offline field app',
                                        'Inventory tracking',
                                        'Service reports',
                                    ]"
                                    :key="inc"
                                    class="flex items-center gap-2"
                                >
                                    <Check class="size-4 shrink-0 text-green-500" /> <span>{{ inc }}</span>
                                </li>
                            </ul>

                            <h3 class="mb-3 font-bold">Pricing questions</h3>
                            <dl class="space-y-4">
                                <div v-for="f in faq" :key="f.q">
                                    <dt class="text-sm font-semibold">{{ f.q }}</dt>
                                    <dd class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ f.a }}</dd>
                                </div>
                            </dl>
                        </div>
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
                            <span class="flex size-7 items-center justify-center rounded-md bg-orange-500 text-white"
                                ><RouteIcon class="size-4"
                            /></span>
                            <span><span class="text-sky-500 dark:text-sky-300">Route</span><span class="text-orange-500">Pilot</span></span>
                        </span>
                        <p class="mt-3 max-w-xs text-sm leading-relaxed text-muted-foreground">
                            The all-in-one pool service management platform — software for pool cleaning businesses that want to run leaner and look
                            more professional. Built by Punchlist Labs.
                        </p>
                    </div>
                    <div>
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-widest text-foreground">Product</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#how" class="text-muted-foreground transition-colors hover:text-foreground">How it works</a></li>
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
                            <li>
                                <a :href="demoUrl" target="_blank" class="text-muted-foreground transition-colors hover:text-foreground">Live demo</a>
                            </li>
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
