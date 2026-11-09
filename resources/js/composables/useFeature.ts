import { type SharedData, type TenantFeature } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Whether this company has that part of RoutePilot switched on (no company, e.g. a super admin: treated as yes). */
export function useFeature(feature: TenantFeature) {
    const page = usePage<SharedData>();

    return computed(() => page.props.tenant?.[feature] !== false);
}
