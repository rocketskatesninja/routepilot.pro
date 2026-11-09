import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Whether this company bills its customers through RoutePilot (no company, e.g. a super admin: treated as yes). */
export function useCustomerBilling() {
    const page = usePage<SharedData>();

    return computed(() => page.props.tenant?.customer_billing !== false);
}
