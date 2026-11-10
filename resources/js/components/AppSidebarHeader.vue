<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { useInventoryTracking } from '@/composables/useInventoryTracking';
import { pageDescription } from '@/lib/pageDescriptions';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useResizeObserver } from '@vueuse/core';
import { Bell, Search, Smartphone } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
    meta?: string;
}>();

const page = usePage<SharedData>();
const { isMobile } = useSidebar();
const { open: openCommand } = useCommandPalette();
const unread = computed(() => page.props.auth.unread ?? 0);
// What this page is for, in the middle of the header on a desktop.
const billing = useCustomerBilling();
const tracking = useInventoryTracking();
const description = computed(() => pageDescription(page.component, page.props.auth.role, billing.value, tracking.value));
// It's shown only when it fits whole: half a sentence ending in … reads as broken.
const descriptionEl = ref<HTMLElement | null>(null);
const descriptionFits = ref(true);
const measureDescription = () => {
    const el = descriptionEl.value;
    descriptionFits.value = !el || el.scrollWidth <= el.clientWidth + 1;
};
useResizeObserver(descriptionEl, measureDescription);
watch(description, () => nextTick(measureDescription));

// A crowded header (a small laptop, Big view, a page with lots of buttons) makes room in
// two steps before anything gets cut: first its buttons drop their words (the data-tight
// rules in app.css), then, if that's still not enough, it takes a second row for the tabs
// and buttons, as on a phone. Each step remembers how wide the header needed to be, so it
// only undoes itself once there's that much room again, rather than flicking at the edge.
const headerEl = ref<HTMLElement | null>(null);
const filtersEl = ref<HTMLElement | null>(null);
const tight = ref(false);
const stacked = ref(false);
let roomUntight = 0;
let roomUnstacked = 0;
const overflowOf = (el: HTMLElement | null): number => (el ? el.scrollWidth - el.clientWidth : 0);
const measureHeader = () => {
    const el = headerEl.value;
    if (!el || isMobile.value) {
        tight.value = stacked.value = false;
        return;
    }
    if (stacked.value) {
        if (el.clientWidth >= roomUnstacked) {
            stacked.value = false;
            nextTick(measureHeader);
        }
        return;
    }
    const over = Math.max(overflowOf(el), overflowOf(filtersEl.value));
    if (over > 1 && !tight.value) {
        roomUntight = el.clientWidth + over;
        tight.value = true;
        nextTick(measureHeader); // see whether dropping the words was enough
    } else if (over > 1) {
        roomUnstacked = el.clientWidth + over;
        stacked.value = true;
    } else if (tight.value && el.clientWidth >= roomUntight) {
        tight.value = false;
        nextTick(measureHeader);
    }
};
useResizeObserver(headerEl, measureHeader);
watch(
    () => page.component,
    () => {
        tight.value = stacked.value = false;
        nextTick(measureHeader);
    },
);
// Widths also change without the header resizing: when the font arrives, or new counts
// come in on the same page.
watch(
    () => page.props,
    () => nextTick(measureHeader),
);
onMounted(() =>
    document.fonts?.ready.then(() => {
        measureDescription();
        measureHeader();
    }),
);
const isStaff = computed(() => ['agent', 'tenant_admin', 'super_admin'].includes(page.props.auth.role ?? ''));
// Anyone who works routes — techs, and office admins who run one themselves — can open the field app.
const canField = computed(() => ['agent', 'tenant_admin'].includes(page.props.auth.role ?? ''));
// A phone hides the sidebar, so the portal's header carries the company's logo instead.
const portalLogo = computed(() =>
    page.props.auth.role === 'customer' && page.props.tenant?.logo_path ? `/storage/${page.props.tenant.logo_path}` : null,
);
</script>

<template>
    <!-- On a phone the page actions share the title's row when they fit and drop to a
         full-width row of their own when they don't (the title takes nearly all the
         spare room — grow-999 — so actions that fit stay compact at the row's end);
         a single fixed-height row on md+, or two when it's crowded (stacked: the tabs and
         buttons go under the title, after the line break below). -->
    <header
        ref="headerEl"
        :data-tight="tight || undefined"
        class="border-sidebar-border/70 flex min-h-16 shrink-0 flex-wrap items-center gap-x-2 gap-y-2 border-b px-4 py-2.5 transition-[width,height] ease-linear"
        :class="stacked ? 'md:flex-wrap' : 'md:h-16 md:flex-nowrap md:py-0'"
    >
        <div class="flex min-w-0 grow-[999] items-center gap-2 md:flex-none">
            <!-- Desktop collapses via the sidebar logo; on mobile this opens the off-canvas panel. -->
            <SidebarTrigger v-if="isMobile" class="-ml-1" />
            <img v-if="isMobile && portalLogo" :src="portalLogo" :alt="page.props.tenant?.name" class="size-8 shrink-0 rounded object-contain" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumb class="min-w-0">
                    <BreadcrumbList class="md:text-[1.3125rem]">
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" class="md:[&>svg]:size-5.25" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
            <span v-if="meta" class="text-muted-foreground truncate text-sm md:text-[1.3125rem]" :title="meta">{{ meta }}</span>
        </div>

        <!-- Field app, search + notifications: the top row's right on a phone, after
             the page actions on desktop (pushed right themselves when a page has none). -->
        <div class="flex items-center gap-1 md:order-last" :class="{ 'md:ml-auto': !$slots.actions || stacked }">
            <Link
                v-if="canField"
                href="/field"
                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-2 transition-colors"
                :class="{ 'bg-muted text-foreground': page.url.startsWith('/field') }"
                title="Field app"
                aria-label="Field app"
            >
                <Smartphone class="size-5 md:size-7.5" />
            </Link>
            <button
                v-if="isStaff"
                type="button"
                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-2 transition-colors"
                title="Search (Ctrl+K)"
                aria-label="Search"
                @click="openCommand()"
            >
                <Search class="size-5 md:size-7.5" />
            </button>
            <Link
                href="/notifications"
                class="text-muted-foreground hover:bg-muted hover:text-foreground relative rounded-md p-2 transition-colors"
                :class="{ 'bg-muted text-foreground': page.url.startsWith('/notifications') }"
                title="Notifications"
                aria-label="Notifications"
            >
                <Bell class="size-5 md:size-7.5" />
                <span
                    v-if="unread > 0"
                    class="absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white md:h-6 md:min-w-6 md:px-1.5 md:text-[15px]"
                    >{{ unread > 9 ? '9+' : unread }}</span
                >
            </Link>
        </div>

        <!-- Stacked: everything after this break (it comes last, like the icons before it) takes the second row. -->
        <div v-if="stacked" class="order-last basis-full" aria-hidden="true"></div>

        <!-- Filters (list tabs): their own scrollable row on mobile, inline after the title on desktop. -->
        <div
            v-if="$slots.filters"
            ref="filtersEl"
            class="flex w-full items-center gap-1 overflow-x-auto md:w-auto"
            :class="{ 'order-last shrink-0': stacked }"
        >
            <div v-if="!stacked" class="bg-sidebar-border/70 hidden h-5 w-px shrink-0 md:block"></div>
            <slot name="filters" />
        </div>

        <!-- What the page is for: desktop only, between the title and the actions, and only when it fits whole. -->
        <p
            v-if="description"
            ref="descriptionEl"
            class="text-muted-foreground hidden min-w-0 flex-1 overflow-hidden px-2 text-center text-sm whitespace-nowrap lg:block"
            :class="{ invisible: !descriptionFits }"
        >
            {{ description }}
        </p>

        <!-- Page actions: on the title's row if they fit, else a full-width row (mobile); inline on the right on desktop. -->
        <div
            v-if="$slots.actions"
            data-header-actions
            class="flex grow flex-wrap items-center gap-2 md:ml-auto md:grow-0 md:flex-nowrap"
            :class="{ 'order-last': stacked }"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
