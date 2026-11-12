import { describe, expect, it } from 'vitest';
import customerGuide from '../../help/customer.md?raw';
import officeGuide from '../../help/office.md?raw';
import platformGuide from '../../help/platform.md?raw';
import techGuide from '../../help/tech.md?raw';
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
            expect(linkUrl(role, 'pool', '5')).toBeNull();
        }
        expect(linkUrl('agent', 'workorder', '3')).toBe('/work-orders?selected=3');
        expect(linkUrl('customer', 'visit', '9')).toBe('/history?selected=9');
        expect(linkUrl('customer', 'workorder', '3')).toBeNull();
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

    it('shows *italic* without its stars, but leaves sums and bullets alone', () => {
        expect(renderParts('The *Anderson Pool* is worst', 'customer')).toEqual([
            { kind: 'text', text: 'The ' },
            { kind: 'italic', text: 'Anderson Pool' },
            { kind: 'text', text: ' is worst' },
        ]);
        expect(renderParts('2 * 3 * 4', 'customer')).toEqual([{ kind: 'text', text: '2 * 3 * 4' }]);
        expect(renderParts('* first\n* second', 'customer')).toEqual([{ kind: 'text', text: '* first\n* second' }]);
        expect(renderParts('**Bold** then *it*.', 'customer').map((p) => p.kind)).toEqual(['bold', 'text', 'italic', 'text']);
    });

    it('keeps **bold** and plain text', () => {
        expect(renderParts('A **big** deal', 'customer')).toEqual([
            { kind: 'text', text: 'A ' },
            { kind: 'bold', text: 'big' },
            { kind: 'text', text: ' deal' },
        ]);
    });
});

describe('how-to guides', () => {
    it.each([
        ['tenant_admin', officeGuide],
        ['agent', techGuide],
        ['customer', customerGuide],
        ['super_admin', platformGuide],
    ])('the %s guide covers every page in that menu', (role, guide) => {
        const missing = navPaths(role).filter((path) => !guide.includes(`(${path})`));
        expect(missing).toEqual([]);
    });
});
