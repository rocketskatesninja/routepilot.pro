<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// SSR-safe + reactive: Inertia provides the current URL in the page object
// (window is undefined during server render).
const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

// 2FA enrollment is staff-only (super_admin / tenant_admin / agent).
const isStaff = computed(() => ['super_admin', 'tenant_admin', 'agent'].includes((page.props.auth?.user?.role as string) ?? ''));

const sidebarNavItems = computed<NavItem[]>(() => [
    { title: 'Profile', href: '/settings/profile' },
    { title: 'Password', href: '/settings/password' },
    ...(isStaff.value ? [{ title: 'Two-factor', href: '/settings/two-factor' }] : []),
    { title: 'Appearance', href: '/settings/appearance' },
]);
</script>

<template>
    <div class="px-4 py-6">
        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
