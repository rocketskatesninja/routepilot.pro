import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Whether this company counts its stock and repair parts in RoutePilot (no company, e.g. a super admin: treated as yes). */
export function useInventoryTracking() {
    const page = usePage<SharedData>();

    return computed(() => page.props.tenant?.inventory_tracking !== false);
}
