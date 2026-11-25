/** The words people see for the app's fixed lists. */

/** How often a plan's visits come (matches ServiceSubscription::FREQUENCIES). */
export const SCHEDULES: Record<string, string> = { weekly: 'Weekly', biweekly: 'Every 2 weeks', monthly: 'Monthly', one_time: 'One-time' };

/** How a price reads next to its amount: "$45 per visit", "$150 for the job". */
export const priceUnit = (frequency: string) => (frequency === 'one_time' ? 'for the job' : 'per visit');

export const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

/** Pool types, as the pool form offers them. */
export const POOL_TYPES: Record<string, string> = {
    inground: 'In-ground',
    above_ground: 'Above-ground',
    indoor: 'Indoor',
    spa: 'Spa / hot tub',
    infinity: 'Infinity',
    other: 'Other',
};

export const SANITIZERS: Record<string, string> = {
    chlorine: 'Chlorine',
    salt: 'Salt',
    bromine: 'Bromine',
    biguanide: 'Biguanide',
    ozone: 'Ozone',
    uv: 'UV',
    other: 'Other',
};

export const FILTERS: Record<string, string> = { cartridge: 'Cartridge', sand: 'Sand', de: 'DE' };

/** A service plan's status, and the chip colour that goes with it. */
export const PLAN_STATUS: Record<string, string> = { active: 'Active', paused: 'Paused', cancelled: 'Ended' };
export const planTone = (status: string) => (status === 'active' ? 'good' : status === 'paused' ? 'warn' : 'muted');

/** The kinds of equipment a pool's list tracks ("salt_cell" reads "salt cell"). */
export const EQUIPMENT_TYPES = ['pump', 'filter', 'heater', 'salt_cell', 'cleaner', 'automation', 'other'];
export const equipmentLabel = (type: string) => type.replace('_', ' ');

/** A new plan starts from how its service is sold: its schedule (a one-time job → One-time) and its price. */
export function startFromService(
    plan: { frequency: string; price: number | string },
    services: { id: number; frequency: string; price: number }[],
    id: unknown,
): void {
    const service = services.find((t) => t.id === Number(id));
    if (service && SCHEDULES[service.frequency]) plan.frequency = service.frequency;
    plan.price = service?.price ?? '';
}
