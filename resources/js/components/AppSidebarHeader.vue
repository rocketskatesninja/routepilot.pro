<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { pageDescription } from '@/lib/pageDescriptions';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Search, Smartphone } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
    meta?: string;
}>();

const page = usePage<SharedData>();
const { isMobile } = useSidebar();
const { open: openCommand } = useCommandPalette();
const unread = computed(() => page.props.auth.unread ?? 0);
// What this page is for, in the middle of the header on a desktop.
const description = computed(() => pageDescription(page.component, page.props.auth.role));
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
         a single fixed-height row on md+. -->
    <header
        class="border-sidebar-border/70 flex min-h-16 shrink-0 flex-wrap items-center gap-x-2 gap-y-2 border-b px-4 py-2.5 transition-[width,height] ease-linear md:h-16 md:flex-nowrap md:py-0"
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
            <span v-if="meta" class="text-muted-foreground truncate text-sm md:text-[1.3125rem]">{{ meta }}</span>
        </div>

        <!-- Field app, search + notifications: the top row's right on a phone, after
             the page actions on desktop (pushed right themselves when a page has none). -->
        <div class="flex items-center gap-1 md:order-last" :class="{ 'md:ml-auto': !$slots.actions }">
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
                title="Search (⌘K)"
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

        <!-- Filters (list tabs): their own scrollable row on mobile, inline after the title on desktop. -->
        <div v-if="$slots.filters" class="flex w-full items-center gap-1 overflow-x-auto md:w-auto">
            <div class="bg-sidebar-border/70 hidden h-5 w-px shrink-0 md:block"></div>
            <slot name="filters" />
        </div>

        <!-- What the page is for: desktop only, between the title and the actions, cut short with … when tight. -->
        <p v-if="description" class="text-muted-foreground hidden min-w-0 flex-1 truncate px-2 text-center text-sm lg:block" :title="description">
            {{ description }}
        </p>

        <!-- Page actions: on the title's row if they fit, else a full-width row (mobile); inline on the right on desktop. -->
        <div v-if="$slots.actions" class="flex grow flex-wrap items-center gap-2 md:ml-auto md:grow-0 md:flex-nowrap">
            <slot name="actions" />
        </div>
    </header>
</template>
