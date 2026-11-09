import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';
import { PAGE_DESCRIPTIONS, pageDescription } from './pageDescriptions';

const PAGES = join(__dirname, '..', 'pages');
const vueFiles = (dir: string): string[] =>
    readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? vueFiles(join(dir, f)) : f.endsWith('.vue') ? [join(dir, f)] : []));

describe('page descriptions', () => {
    it('every page with the app header says what it’s for', () => {
        const withHeader = vueFiles(PAGES)
            .filter((f) => readFileSync(f, 'utf8').includes('<AppLayout'))
            .map((f) => relative(PAGES, f).replace(/\.vue$/, ''));
        expect(withHeader.length).toBeGreaterThan(20);
        expect(withHeader.filter((c) => !(c in PAGE_DESCRIPTIONS))).toEqual([]);
    });

    it('picks the role’s own sentence, else the default', () => {
        expect(pageDescription('schedule/Index', 'agent')).toBe('Your stops for the day, in order.');
        expect(pageDescription('schedule/Index', 'tenant_admin')).toContain('Plan each day');
        expect(pageDescription('inbox/Index', 'tenant_admin')).toContain('waiting for an answer');
        expect(pageDescription('nope/Missing', 'agent')).toBeNull();
    });
});

describe('pageDescription without customer billing', () => {
    it('leaves the balance out of what a customer is told', () => {
        expect(pageDescription('dashboards/Grid', 'customer')).toContain('balance');
        expect(pageDescription('dashboards/Grid', 'customer', false)).not.toContain('balance');
    });

    it('falls back to the usual sentence everywhere else', () => {
        expect(pageDescription('dashboards/Grid', 'tenant_admin', false)).toBe(pageDescription('dashboards/Grid', 'tenant_admin'));
        expect(pageDescription('schedule/Index', 'tenant_admin', false)).toBe(pageDescription('schedule/Index', 'tenant_admin'));
    });
});

describe('pageDescription without inventory tracking', () => {
    it('calls the page what it is', () => {
        expect(pageDescription('inventory/Index', 'tenant_admin')).toContain('in stock');
        expect(pageDescription('inventory/Index', 'tenant_admin', true, false)).not.toContain('stock');
    });

    it('leaves every other page alone', () => {
        expect(pageDescription('schedule/Index', 'tenant_admin', true, false)).toBe(pageDescription('schedule/Index', 'tenant_admin'));
    });
});
