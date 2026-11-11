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
import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { useFeature } from '@/composables/useFeature';
import { useInventoryTracking } from '@/composables/useInventoryTracking';
import { navByRole, type NavSection } from '@/lib/nav';
import { type NavCounts, type NavItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

// Links to the parts of RoutePilot a company can switch off. (/billing is its own subscription: it stays.)
const billing = useCustomerBilling();
const website = useFeature('landing_page');
const assistant = useFeature('ai_assistant');
const shown = (item: NavItem): boolean =>
    (billing.value || !['/balances', '/balance'].includes(item.href)) &&
    (website.value || item.href !== '/company/landing') &&
    (assistant.value || item.href !== '/assistant');
// A company that doesn't count its stock just has a list of chemicals.
const tracking = useInventoryTracking();
const named = (item: NavItem): NavItem => (item.href === '/inventory' && !tracking.value ? { ...item, title: 'Chemicals' } : item);

// Which links carry a number, and which count it is.
const countFor: Record<string, keyof NavCounts> = { '/reports': 'reports', '/work-orders': 'work_orders', '/inbox': 'requests' };

const navSections = computed<NavSection[]>(() => {
    const role = page.props.auth.role ?? 'customer';
    const counts = page.props.auth.counts ?? {};
    return (navByRole[role] ?? navByRole.customer).map((section) => ({
        ...section,
        items: section.items
            .filter(shown)
            .map(named)
            .map((item) => (countFor[item.href] ? { ...item, badge: counts[countFor[item.href]] } : item)),
    }));
});

const isAgent = computed(() => page.props.auth.role === 'agent');

// Feeds the panel-filling sizes in app.css. Expanded: how many links share the
// nav's height, and how much of it the section labels (h-5) and the gaps between
// sections (gap-1.5) take up. Collapsed: every square in the rail (the links,
// the logo and avatar, an agent's two status icons) shares the whole panel, less
// its padding (p-2 on the panel, header and footer), the section gaps (the labels
// fold away) and an agent's footer gap (gap-2).
const navFit = computed(() => {
    const sections = navSections.value;
    const labels = sections.filter((section) => section.label).length;
    const links = sections.reduce((count, section) => count + section.items.length, 0);
    return {
        '--nav-links': links,
        '--nav-chrome': `${labels * 1.25 + (sections.length - 1) * 0.375}rem`, // in rem, so it grows with Big view
        '--rail-cells': links + 2 + (isAgent.value ? 2 : 0),
        '--rail-chrome': `calc(${3 + (sections.length - 1) * 0.375 + (isAgent.value ? 0.5 : 0)}rem + 1px)`,
    };
});

const { state, toggleSidebar } = useSidebar();
const toggleLabel = computed(() => (state.value === 'collapsed' ? 'Expand sidebar' : 'Collapse sidebar'));
</script>

<template>
    <!-- Search and notifications live in the page header (AppSidebarHeader) on every
         screen size, so the panel holds only the logo, the nav and the account. -->
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader :style="navFit">
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

        <SidebarFooter :style="navFit">
            <NavFieldStatus v-if="isAgent" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
