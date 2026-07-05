<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
    meta?: string;
}>();

const page = usePage<SharedData>();
const { isMobile } = useSidebar();
const unread = computed(() => page.props.auth.unread ?? 0);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <!-- Desktop collapses via the sidebar logo; on mobile this opens the off-canvas panel. -->
            <SidebarTrigger v-if="isMobile" class="-ml-1" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumb>
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
            <span v-if="meta" class="whitespace-nowrap text-sm text-muted-foreground">{{ meta }}</span>
            <template v-if="$slots.filters">
                <div class="mx-1 h-5 w-px shrink-0 bg-sidebar-border/70"></div>
                <div class="flex items-center gap-1">
                    <slot name="filters" />
                </div>
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <slot name="actions" />
            <Link
                v-if="isMobile"
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
    </header>
</template>
