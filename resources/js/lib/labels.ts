/** The words people see for the app's fixed lists. */

/** How often a plan's visits come (matches ServiceSubscription::FREQUENCIES). */
export const SCHEDULES: Record<string, string> = { weekly: 'Weekly', biweekly: 'Every 2 weeks', monthly: 'Monthly', one_time: 'One-time' };

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
