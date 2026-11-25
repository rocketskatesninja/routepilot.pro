import { useCustomerBilling } from '@/composables/useCustomerBilling';
import { useFeature } from '@/composables/useFeature';
import { useInventoryTracking } from '@/composables/useInventoryTracking';
import { navByRole, type NavSection } from '@/lib/nav';
import { type NavItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * The signed-in person's menu: their role's pages, minus the parts their company
 * has switched off. The sidebar and the command palette both read this, so the
 * palette never offers a page the sidebar wouldn't.
 */
export function useNav(): ComputedRef<NavSection[]> {
    const page = usePage<SharedData>();
    // (/billing is the company's own subscription: it stays.)
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

    return computed(() =>
        (navByRole[page.props.auth.role ?? 'customer'] ?? navByRole.customer).map((section) => ({
            ...section,
            items: section.items.filter(shown).map(named),
        })),
    );
}
