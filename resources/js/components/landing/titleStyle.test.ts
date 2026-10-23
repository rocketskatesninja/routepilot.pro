import { Palmtree } from 'lucide-vue-next';
import { describe, expect, it } from 'vitest';
import { titleMark } from './titleStyle';
import type { TitleConfig } from './types';

const base = { size: 'md' } as TitleConfig;
const t = (fields: Partial<TitleConfig>): TitleConfig => ({ ...base, ...fields });

describe('titleMark', () => {
    it('shows the uploaded logo beside the name by default', () => {
        expect(titleMark(t({ icon: 'logo', display: 'icon_title' }), '/storage/logo.png')).toMatchObject({
            logo: '/storage/logo.png',
            icon: null,
            text: true,
        });
    });

    it('shows just the name when the logo is picked but none is uploaded, even if set to "icon only"', () => {
        expect(titleMark(t({ icon: 'logo', display: 'icon' }), null)).toMatchObject({ logo: null, icon: null, text: true });
    });

    it('shows a picked icon, alone when set to "icon only"', () => {
        expect(titleMark(t({ icon: 'palmtree', display: 'icon_title' }), null)).toMatchObject({ icon: Palmtree, text: true });
        expect(titleMark(t({ icon: 'palmtree', display: 'icon' }), '/storage/logo.png')).toMatchObject({ logo: null, icon: Palmtree, text: false });
    });

    it('shows no mark for "name only", "none" or an unknown icon', () => {
        expect(titleMark(t({ icon: 'palmtree', display: 'title' }), null)).toMatchObject({ logo: null, icon: null, text: true });
        expect(titleMark(t({ icon: null, display: 'icon' }), '/storage/logo.png')).toMatchObject({ logo: null, icon: null, text: true });
        expect(titleMark(t({ icon: 'bogus', display: 'icon_title' }), null)).toMatchObject({ icon: null, text: true });
    });

    it('scales the mark with the title size', () => {
        expect(titleMark(t({ icon: 'palmtree', size: 'sm' }), null).px).toBeLessThan(titleMark(t({ icon: 'palmtree', size: 'xl' }), null).px);
    });
});
