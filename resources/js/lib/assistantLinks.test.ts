import { describe, expect, it } from 'vitest';
import officeGuide from '../../help/office.md?raw';
import { linkUrl, renderParts } from './assistantLinks';
import { navPaths } from './nav';

describe('assistant links', () => {
    it('links office records to their pages', () => {
        expect(renderParts('See [[pool:12:Main Pool]] and [[day:20261117:Tuesday]].', 'tenant_admin')).toEqual([
            { kind: 'text', text: 'See ' },
            { kind: 'link', text: 'Main Pool', url: '/pools?selected=12' },
            { kind: 'text', text: ' and ' },
            { kind: 'link', text: 'Tuesday', url: '/schedule?date=2026-11-17' },
            { kind: 'text', text: '.' },
        ]);
        expect(linkUrl('tenant_admin', 'lead', '7')).toBe('/inbox?tab=leads&selected=7');
    });

    it('only links a page that is in that account’s own menu', () => {
        expect(linkUrl('tenant_admin', 'page', '/schedule')).toBe('/schedule');
        expect(linkUrl('customer', 'page', '/requests')).toBe('/requests');
        expect(linkUrl('customer', 'page', '/people')).toBeNull();
        expect(linkUrl('agent', 'page', '/balances')).toBeNull();
    });

    it('never links a record type a customer or tech can’t open', () => {
        for (const role of ['customer', 'agent', 'super_admin']) {
            expect(renderParts('[[customer:5:Jane Doe]]', role)).toEqual([{ kind: 'text', text: 'Jane Doe' }]);
        }
    });

    it('never links outside the app, whatever the reply says', () => {
        const reply =
            '[[page:https://evil.example/x?d=1:Click]] [[page://evil.example:Click]] [[customer:5x:Jane]] [link](https://evil.example) ![img](https://evil.example/p.png)';
        const parts = renderParts(reply, 'tenant_admin');
        expect(parts.some((p) => p.kind === 'link')).toBe(false);
        expect(parts.map((p) => p.text).join('')).not.toContain('<');
        expect(parts.map((p) => p.text).join('')).not.toContain('evil.example/p.png');
        expect(renderParts('Open it [here](https://app.example.com/schedule/1).', 'tenant_admin')).toEqual([{ kind: 'text', text: 'Open it here.' }]);
    });

    it('copes with a link the model got slightly wrong, and never shows raw brackets', () => {
        expect(renderParts('Go to [[schedule:/schedule]] now', 'tenant_admin')).toEqual([
            { kind: 'text', text: 'Go to ' },
            { kind: 'link', text: 'Schedule', url: '/schedule' },
            { kind: 'text', text: ' now' },
        ]);
        expect(renderParts('[[/pools]]', 'tenant_admin')).toEqual([{ kind: 'link', text: 'Pools', url: '/pools' }]);
        expect(
            renderParts('[[Main Pool]] and [[pool:Main Pool]]', 'tenant_admin')
                .map((p) => p.text)
                .join(''),
        ).toBe('Main Pool and Main Pool');
        expect(renderParts('[[schedule:/schedule]]', 'customer')).toEqual([{ kind: 'text', text: '/schedule' }]); // not in a customer's menu
    });

    it('keeps **bold** and plain text', () => {
        expect(renderParts('A **big** deal', 'customer')).toEqual([
            { kind: 'text', text: 'A ' },
            { kind: 'bold', text: 'big' },
            { kind: 'text', text: ' deal' },
        ]);
    });
});

describe('office guide', () => {
    it('covers every page in the office menu', () => {
        const missing = navPaths('tenant_admin').filter((path) => !officeGuide.includes(`(${path})`));
        expect(missing).toEqual([]);
    });
});
