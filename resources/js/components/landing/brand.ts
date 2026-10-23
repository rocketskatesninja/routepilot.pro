import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import type { BrandContext } from './types';

interface SharedTenant {
    name: string;
    slug: string;
    logo_path: string | null;
    brand_color: string | null;
}

/** The company's name, slug, logo and color, from the shared `tenant` prop (the page and the editor's preview). */
export function useSiteBrand(): ComputedRef<BrandContext> {
    const page = usePage();

    return computed(() => {
        const t = page.props.tenant as SharedTenant | null;
        return {
            name: t?.name ?? 'Pool Service',
            slug: t?.slug ?? '',
            logoUrl: t?.logo_path ? `/storage/${t.logo_path}` : null,
            color: t?.brand_color ?? null,
        };
    });
}
