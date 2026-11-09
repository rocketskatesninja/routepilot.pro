/**
 * One plain sentence saying what each page is for, shown in the middle of the
 * header on a desktop. Keyed by the Inertia page component; a page that means
 * something different per role gives one per role (with `default` for the rest).
 * A test checks every page with the app header has one.
 */
type Role = 'super_admin' | 'tenant_admin' | 'agent' | 'customer';
type Description = string | Partial<Record<Role | 'default', string>>;

export const PAGE_DESCRIPTIONS: Record<string, Description> = {
    'admin/Ai': 'Choose the AI provider and model, and each company’s monthly allowance.',
    'admin/Billing': 'Every company’s subscription, trial and free account.',
    'assistant/Index': 'Ask about your routes, pools, customers and water — in plain words.',
    'balances/Index': 'Who owes what: charges, payments and invoices, customer by customer.',
    'dashboards/Grid': {
        tenant_admin: 'Your business at a glance. Customize to arrange the widgets.',
        agent: 'Your day at a glance: today’s route, your reports and the weather.',
        customer: 'Your pools, your next visit and your balance at a glance.',
        super_admin: 'The whole platform at a glance.',
    },
    'inbox/Index': 'Requests and leads waiting for an answer.',
    'inventory/Index': 'Your chemicals and supplies: what’s in stock, what techs used, and what’s running low.',
    'notifications/Index': 'Everything RoutePilot has told you.',
    'people/Index': 'Your customers and techs: their details, accounts and history.',
    'people/Platform': 'Every company and person on RoutePilot.',
    'pools/Index': 'Every pool you service: its equipment, plans, water history and how to get there.',
    'portal/Balance': 'What you owe, what you’ve paid, and paying your bill.',
    'portal/History': 'Every visit to your pools, with the full report.',
    'portal/Home': 'Your pool service at a glance.',
    'portal/Pools': 'Your pools and their latest water test.',
    'portal/Requests': 'Ask for a service, a pause or anything else, and see the answers.',
    'reports/Index': {
        default: 'Every visit report your techs filed: water tests, chemicals, photos and notes.',
        agent: 'The visit reports you’ve filed.',
    },
    'reports/Insights': 'How the business is doing: visits, revenue and trends over time.',
    'schedule/Index': {
        default: 'Plan each day’s routes: who goes where, and in what order.',
        agent: 'Your stops for the day, in order.',
    },
    'services/Index': 'The services you offer: what each visit includes and what it costs.',
    'settings/Appearance': 'How RoutePilot looks for you: light, dark, or follow your device.',
    'settings/Billing': 'Your RoutePilot plan, trial and payment details.',
    'settings/Company': 'Your company’s details, logo, email and payments.',
    'settings/Emails': 'Which emails you get — each one has its own switch.',
    'settings/Landing': 'Build your company’s website, with a live preview as you edit.',
    'settings/Password': 'Change the password you sign in with.',
    'settings/Profile': 'Your name, email and photo.',
    'settings/TwoFactor': 'Two-step sign-in, for extra security.',
    'work-orders/Index': 'Jobs to do later: repairs and extras raised in the field or by the office.',
};

/** What the page is for, for this person; null when there's nothing to say. */
/** Where a page says something else for a company that doesn't bill its customers here. */
const WITHOUT_BILLING: Record<string, Description> = {
    'dashboards/Grid': { customer: 'Your pools and your next visit at a glance.' },
    'settings/Company': 'Your company’s details, logo and email.',
};

/** Likewise for one that doesn't count its stock. */
const WITHOUT_INVENTORY: Record<string, Description> = {
    'inventory/Index': 'Your chemicals: the list techs pick from on a visit.',
};

function pick(d: Description | undefined, role: string | null | undefined): string | null {
    if (d === undefined) return null;
    if (typeof d === 'string') return d;
    return (role ? d[role as Role] : undefined) ?? d.default ?? null;
}

export function pageDescription(component: string, role: string | null | undefined, billing = true, inventory = true): string | null {
    return (
        (inventory ? null : pick(WITHOUT_INVENTORY[component], role)) ??
        (billing ? null : pick(WITHOUT_BILLING[component], role)) ??
        pick(PAGE_DESCRIPTIONS[component], role)
    );
}
