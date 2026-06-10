<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Banknote, CalendarDays, LayoutGrid, Map, Settings, ShieldCheck, Users, Waves } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

/**
 * Role-adaptive navigation. Each role sees only its own items; tenant_admin
 * is the densest. Routes for not-yet-built surfaces point at the dashboard
 * for now and are filled in as later phases land their screens.
 */
const navByRole: Record<string, NavItem[]> = {
    super_admin: [
        { title: 'Platform', href: '/dashboard', icon: ShieldCheck },
        { title: 'Tenants', href: '/dashboard', icon: Users },
        { title: 'Billing', href: '/dashboard', icon: Banknote },
    ],
    tenant_admin: [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
        { title: 'Schedule', href: '/dashboard', icon: CalendarDays },
        { title: 'Pools', href: '/pools', icon: Waves },
        { title: 'People', href: '/people', icon: Users },
        { title: 'Routes', href: '/dashboard', icon: Map },
        { title: 'Billing', href: '/dashboard', icon: Banknote },
    ],
    agent: [
        { title: 'Today', href: '/dashboard', icon: CalendarDays },
        { title: 'My Route', href: '/dashboard', icon: Map },
    ],
    customer: [
        { title: 'My Pools', href: '/dashboard', icon: Waves },
        { title: 'Billing', href: '/dashboard', icon: Banknote },
    ],
};

const mainNavItems = computed<NavItem[]>(() => {
    const role = page.props.auth.role ?? 'customer';
    return navByRole[role] ?? navByRole.customer;
});

const footerNavItems: NavItem[] = [{ title: 'Settings', href: '/settings/profile', icon: Settings }];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
