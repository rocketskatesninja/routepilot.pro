<script setup lang="ts">
import NavFieldStatus from '@/components/NavFieldStatus.vue';
import NavMain from '@/components/NavMain.vue';
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
import { usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    Bot,
    Building2,
    CalendarDays,
    ClipboardList,
    CreditCard,
    FileText,
    FlaskConical,
    Globe,
    Home,
    Inbox,
    LayoutGrid,
    Map,
    Navigation,
    ShieldCheck,
    Users,
    Waves,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

/**
 * Role-adaptive navigation. Each role sees only its own items; tenant_admin
 * is the densest, so its menu is split into labelled sections (Operations /
 * Customers / Business) with Dashboard pinned on top and Assistant on the
 * bottom, ungrouped. Shorter menus stay a single unlabelled section.
 */
type NavSection = { label?: string; items: NavItem[] };

const navByRole: Record<string, NavSection[]> = {
    super_admin: [
        {
            items: [
                { title: 'Platform', href: '/dashboard', icon: ShieldCheck },
                { title: 'People', href: '/people', icon: Users },
                { title: 'Billing', href: '/platform/billing', icon: CreditCard },
                { title: 'AI', href: '/platform/ai', icon: Bot },
                { title: 'Assistant', href: '/assistant', icon: Bot },
            ],
        },
    ],
    tenant_admin: [
        { items: [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid }] },
        {
            label: 'Operations',
            items: [
                { title: 'Schedule', href: '/schedule', icon: CalendarDays },
                { title: 'Reports', href: '/reports', icon: FileText },
                { title: 'Inventory', href: '/inventory', icon: FlaskConical },
            ],
        },
        {
            label: 'Customers',
            items: [
                { title: 'Requests', href: '/inbox', icon: Inbox },
                { title: 'People', href: '/people', icon: Users },
                { title: 'Pools', href: '/pools', icon: Waves },
                { title: 'Services', href: '/services', icon: ClipboardList },
                { title: 'Balances', href: '/balances', icon: Banknote },
            ],
        },
        {
            label: 'Business',
            items: [
                { title: 'Insights', href: '/insights', icon: BarChart3 },
                { title: 'Company', href: '/company', icon: Building2 },
                { title: 'Billing', href: '/billing', icon: CreditCard },
                { title: 'Landing page', href: '/company/landing', icon: Globe },
            ],
        },
        { items: [{ title: 'Assistant', href: '/assistant', icon: Bot }] },
    ],
    agent: [
        {
            items: [
                { title: 'Today', href: '/dashboard', icon: CalendarDays },
                { title: 'Field app', href: '/field', icon: Navigation },
                { title: 'My Route', href: '/schedule', icon: Map },
                { title: 'Reports', href: '/reports', icon: FileText },
                { title: 'Assistant', href: '/assistant', icon: Bot },
            ],
        },
    ],
    customer: [
        {
            items: [
                { title: 'Home', href: '/dashboard', icon: Home },
                { title: 'My Pools', href: '/my-pools', icon: Waves },
                { title: 'Service History', href: '/history', icon: FileText },
                { title: 'Balance', href: '/balance', icon: Banknote },
                { title: 'Services', href: '/requests', icon: ClipboardList },
                { title: 'Assistant', href: '/assistant', icon: Bot },
            ],
        },
    ],
};

const navSections = computed<NavSection[]>(() => {
    const role = page.props.auth.role ?? 'customer';
    return navByRole[role] ?? navByRole.customer;
});

const isAgent = computed(() => page.props.auth.role === 'agent');

// Feeds the panel-filling row height in app.css: how many links share the nav's
// height, and how much of it the section labels (h-5) and the gaps between
// sections (gap-1.5) take up.
const navFit = computed(() => {
    const sections = navSections.value;
    const labels = sections.filter((section) => section.label).length;
    return {
        '--nav-links': sections.reduce((count, section) => count + section.items.length, 0),
        '--nav-chrome': `${labels * 20 + (sections.length - 1) * 6}px`,
    };
});

const { state, toggleSidebar } = useSidebar();
const toggleLabel = computed(() => (state.value === 'collapsed' ? 'Expand sidebar' : 'Collapse sidebar'));
</script>

<template>
    <!-- Search and notifications live in the page header (AppSidebarHeader) on every
         screen size, so the panel holds only the logo, the nav and the account. -->
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" type="button" :title="toggleLabel" :aria-label="toggleLabel" @click="toggleSidebar">
                        <AppLogo />
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-1.5" :style="navFit">
            <NavMain v-for="(section, i) in navSections" :key="section.label ?? `section-${i}`" :label="section.label" :items="section.items" />
        </SidebarContent>

        <SidebarFooter>
            <NavFieldStatus v-if="isAgent" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
