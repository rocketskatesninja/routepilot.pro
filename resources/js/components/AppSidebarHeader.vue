<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { useCommandPalette } from '@/composables/useCommandPalette';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Search } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
    meta?: string;
}>();

const page = usePage<SharedData>();
const { isMobile } = useSidebar();
const { open: openCommand } = useCommandPalette();
const unread = computed(() => page.props.auth.unread ?? 0);
const isStaff = computed(() => ['agent', 'tenant_admin', 'super_admin'].includes(page.props.auth.role ?? ''));
</script>

<template>
    <!-- Wraps to multiple rows on a phone (page actions get their own full-width row);
         a single fixed-height row on md+ where there's room. -->
    <header
        class="flex min-h-16 shrink-0 flex-wrap items-center gap-x-2 gap-y-2 border-b border-sidebar-border/70 px-4 py-2.5 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:md:h-12 md:h-16 md:flex-nowrap md:py-0"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 md:flex-none">
            <!-- Desktop collapses via the sidebar logo; on mobile this opens the off-canvas panel. -->
            <SidebarTrigger v-if="isMobile" class="-ml-1" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumb class="min-w-0">
                    <BreadcrumbList>
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
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
            <span v-if="meta" class="truncate text-sm text-muted-foreground">{{ meta }}</span>
        </div>

        <!-- Mobile quick actions stay pinned to the top row's right. -->
        <div v-if="isMobile" class="flex items-center gap-1">
            <button
                v-if="isStaff"
                type="button"
                class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="Search"
                @click="openCommand()"
            >
                <Search class="size-5" />
            </button>
            <Link
                href="/notifications"
                class="relative rounded-md p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="Notifications"
            >
                <Bell class="size-5" />
                <span
                    v-if="unread > 0"
                    class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                    >{{ unread > 9 ? '9+' : unread }}</span
                >
            </Link>
        </div>

        <!-- Filters (list tabs): their own scrollable row on mobile, inline after the title on desktop. -->
        <div v-if="$slots.filters" class="flex w-full items-center gap-1 overflow-x-auto md:w-auto">
            <div class="hidden h-5 w-px shrink-0 bg-sidebar-border/70 md:block"></div>
            <slot name="filters" />
        </div>

        <!-- Page actions: full-width wrapping row on mobile, inline on the right on desktop. -->
        <div v-if="$slots.actions" class="flex w-full flex-wrap items-center gap-2 md:ml-auto md:w-auto md:flex-nowrap">
            <slot name="actions" />
        </div>
    </header>
</template>
