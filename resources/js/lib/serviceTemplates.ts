/**
 * Starter services for the "Start from a template" picker: a sensible starting
 * point, every field still editable. A checklist never repeats what the visit's
 * own sections do (testing and balancing the water, adding chemicals, photos);
 * a test keeps it that way.
 */
export interface ServiceTemplate {
    name: string;
    description: string;
    frequency: string;
    price: string;
    minutes: number;
    tasks: string[];
    sections?: Record<string, boolean>;
}
const cleanTasks = [
    'Skim the surface',
    'Brush walls, steps and tile line',
    'Vacuum the floor',
    'Empty skimmer and pump baskets',
    'Check the equipment is running',
];
export const SERVICE_TEMPLATES: ServiceTemplate[] = [
    {
        name: 'Weekly pool service',
        description: 'Weekly cleaning and water balancing so your pool stays swim-ready.',
        frequency: 'weekly',
        price: '50',
        minutes: 30,
        tasks: cleanTasks,
    },
    {
        name: 'Every-2-weeks service',
        description: 'A full clean and water balance every other week.',
        frequency: 'biweekly',
        price: '60',
        minutes: 35,
        tasks: cleanTasks,
    },
    {
        name: 'Chemistry check',
        description: 'A quick stop to test and balance your water between cleanings.',
        frequency: 'weekly',
        price: '25',
        minutes: 15,
        tasks: [],
        sections: { tasks: false, chemistry: true, treatments: true, photos: false },
    },
    {
        name: 'Filter clean',
        description: 'We take your filter apart, clean it thoroughly and put it back together.',
        frequency: 'one_time',
        price: '95',
        minutes: 60,
        tasks: ['Shut down the system', 'Take apart and hose the filter', 'Inspect cartridges or grids', 'Reassemble and restart'],
        sections: { tasks: true, chemistry: false, treatments: false, photos: true },
    },
    {
        name: 'Green-to-clean',
        description: 'Turn a green pool clear again: shock, scrub, vacuum and rebalance.',
        frequency: 'one_time',
        price: '350',
        minutes: 120,
        tasks: ['Shock treatment', 'Brush every surface', 'Vacuum to waste', 'Clean the filter'],
    },
    {
        name: 'Pool opening',
        description: 'Get your pool clean, running and balanced for the season.',
        frequency: 'one_time',
        price: '225',
        minutes: 120,
        tasks: ['Remove and store the cover', 'Reinstall the equipment', 'Fill to the right level', 'Start up and prime the pump'],
    },
    {
        name: 'Pool closing',
        description: 'Winterize your pool to protect it until spring.',
        frequency: 'one_time',
        price: '225',
        minutes: 120,
        tasks: ['Lower the water level', 'Blow out the lines', 'Winterize the equipment', 'Install the cover'],
    },
];
