import { describe, expect, it } from 'vitest';
import { extras } from './portal';
import { SERVICE_TEMPLATES } from './serviceTemplates';

const ALL = ['tasks', 'chemistry', 'treatments', 'photos'];

describe('a service card lists each thing once', () => {
    it('leaves out a visit section’s line when the checklist already says it', () => {
        // Glynn's imported Chemistry Check and Hot Tub Service, as they are.
        expect(extras(ALL, ['Test FC/CC/pH/TA/CH/CYA', 'Add chemicals as needed', 'Record readings'])).toEqual([
            'Photos of your pool after each visit',
        ]);
        expect(extras(ALL, ['Clean filter', 'Check temperature', 'Inspect jets', 'Add chemicals', 'Wipe down shell'])).toEqual([
            'Water tested and balanced',
            'Photos of your pool after each visit',
        ]);
        expect(extras(['tasks'], ['Check pump operation', 'Inspect filter pressure'])).toEqual([]);
        expect(extras(ALL)).toHaveLength(3);
    });

    it('no starter template repeats in its checklist what its sections already do', () => {
        for (const t of SERVICE_TEMPLATES) {
            const on = Object.entries({ ...Object.fromEntries(ALL.map((m) => [m, true])), ...t.sections })
                .filter(([, v]) => v)
                .map(([m]) => m);
            expect(extras(on, t.tasks), t.name).toEqual(extras(on));
        }
    });
});
