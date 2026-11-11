import { type NavItem } from '@/types';
import {
    Banknote,
    BarChart3,
    Bot,
    Building2,
    CalendarDays,
    ClipboardList,
    CreditCard,
    FileText,
    FlaskConical,
    Globe,
    Home,
    Inbox,
    LayoutGrid,
    Map,
    ShieldCheck,
    Users,
    Waves,
    Wrench,
} from 'lucide-vue-next';

/**
 * Role-adaptive navigation. Each role sees only its own items; tenant_admin
 * is the densest, so its menu is split into labelled sections (Operations /
 * Customers / Business) with Dashboard and Assistant pinned on top, ungrouped.
 * Shorter menus stay a single unlabelled section. Every role has Assistant
 * right under its home link.
 */
export type NavSection = { label?: string; items: NavItem[] };

export const navByRole: Record<string, NavSection[]> = {
    super_admin: [
        {
            items: [
                { title: 'Platform', href: '/dashboard', icon: ShieldCheck },
                { title: 'Assistant', href: '/assistant', icon: Bot },
                { title: 'People', href: '/people', icon: Users },
                { title: 'Billing', href: '/platform/billing', icon: CreditCard },
                { title: 'AI', href: '/platform/ai', icon: Bot },
            ],
        },
    ],
    tenant_admin: [
        {
            items: [
                { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
                { title: 'Assistant', href: '/assistant', icon: Bot },
            ],
        },
        {
            label: 'Operations',
            items: [
                { title: 'Schedule', href: '/schedule', icon: CalendarDays },
                { title: 'Reports', href: '/reports', icon: FileText },
                { title: 'Work orders', href: '/work-orders', icon: Wrench },
                { title: 'Inventory', href: '/inventory', icon: FlaskConical },
            ],
        },
        {
            label: 'Customers',
            items: [
                { title: 'Requests', href: '/inbox', icon: Inbox },
                { title: 'People', href: '/people', icon: Users },
                { title: 'Pools', href: '/pools', icon: Waves },
                { title: 'Services', href: '/services', icon: ClipboardList },
                { title: 'Balances', href: '/balances', icon: Banknote },
            ],
        },
        {
            label: 'Business',
            items: [
                { title: 'Insights', href: '/insights', icon: BarChart3 },
                { title: 'Company', href: '/company', icon: Building2 },
                { title: 'Subscription', href: '/billing', icon: CreditCard },
                { title: 'Landing page', href: '/company/landing', icon: Globe },
            ],
        },
    ],
    agent: [
        {
            items: [
                { title: 'Today', href: '/dashboard', icon: CalendarDays },
                { title: 'Assistant', href: '/assistant', icon: Bot },
                { title: 'My Route', href: '/schedule', icon: Map },
                { title: 'Reports', href: '/reports', icon: FileText },
                { title: 'Work orders', href: '/work-orders', icon: Wrench },
            ],
        },
    ],
    customer: [
        {
            items: [
                { title: 'Home', href: '/dashboard', icon: Home },
                { title: 'Assistant', href: '/assistant', icon: Bot },
                { title: 'My Pools', href: '/my-pools', icon: Waves },
                { title: 'Service History', href: '/history', icon: FileText },
                { title: 'Balance', href: '/balance', icon: Banknote },
                { title: 'Services', href: '/requests', icon: ClipboardList },
            ],
        },
    ],
};

/** Every page in a role's menu (what the assistant may link to with [[page:…]]). */
export function navPaths(role: string): string[] {
    return (navByRole[role] ?? []).flatMap((section) => section.items.map((item) => item.href));
}

/** A menu page's name for this role ("Schedule"), or null when it isn't in their menu. */
export function navTitle(role: string, path: string): string | null {
    for (const section of navByRole[role] ?? []) {
        const item = section.items.find((i) => i.href === path);
        if (item) return item.title;
    }
    return null;
}
