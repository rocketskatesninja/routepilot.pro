import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    role?: string | null;
    permissions?: string[];
}

export interface Tenant {
    id: number;
    name: string;
    slug: string;
    brand_color: string | null;
    logo_path: string | null;
    timezone: string;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    tenant: Tenant | null;
    flash: { success: string | null; error: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    tenant_id: number | null;
    first_name: string;
    last_name: string | null;
    name: string;
    email: string;
    role: string;
    avatar?: string;
    avatar_path?: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
