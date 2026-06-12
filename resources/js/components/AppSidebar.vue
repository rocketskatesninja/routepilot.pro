<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavNotifications from '@/components/NavNotifications.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    Bot,
    Building2,
    CalendarDays,
    ClipboardList,
    FileText,
    FlaskConical,
    Inbox,
    LayoutGrid,
    Map,
    ShieldCheck,
    Users,
    Waves,
} from 'lucide-vue-next';
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
        { title: 'Tenants', href: '/tenants', icon: Building2 },
        { title: 'People', href: '/people', icon: Users },
        { title: 'Assistant', href: '/assistant', icon: Bot },
    ],
    tenant_admin: [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
        { title: 'Schedule', href: '/schedule', icon: CalendarDays },
        { title: 'Pools', href: '/pools', icon: Waves },
        { title: 'People', href: '/people', icon: Users },
        { title: 'Services', href: '/services', icon: ClipboardList },
        { title: 'Routes', href: '/dashboard', icon: Map },
        { title: 'Inventory', href: '/inventory', icon: FlaskConical },
        { title: 'Reports', href: '/reports', icon: FileText },
        { title: 'Insights', href: '/insights', icon: BarChart3 },
        { title: 'Balances', href: '/balances', icon: Banknote },
        { title: 'Assistant', href: '/assistant', icon: Bot },
        { title: 'Company', href: '/company', icon: Building2 },
    ],
    agent: [
        { title: 'Today', href: '/dashboard', icon: CalendarDays },
        { title: 'My Route', href: '/dashboard', icon: Map },
        { title: 'Reports', href: '/reports', icon: FileText },
        { title: 'Assistant', href: '/assistant', icon: Bot },
    ],
    customer: [
        { title: 'My Pools', href: '/dashboard', icon: Waves },
        { title: 'Service History', href: '/history', icon: FileText },
        { title: 'Balance', href: '/balance', icon: Banknote },
        { title: 'Requests', href: '/requests', icon: Inbox },
        { title: 'Assistant', href: '/assistant', icon: Bot },
    ],
};

const mainNavItems = computed<NavItem[]>(() => {
    const role = page.props.auth.role ?? 'customer';
    return navByRole[role] ?? navByRole.customer;
});

const { isMobile } = useSidebar();
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
            <NavNotifications v-if="!isMobile" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
