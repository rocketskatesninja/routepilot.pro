import { describe, expect, it } from 'vitest';
import { badgeLabels } from './badges';

describe('badgeLabels', () => {
    const picked = ['pay_online', 'autopay', 'veteran_owned'];

    it('shows every picked badge for a company billing here', () => {
        expect(badgeLabels(picked)).toEqual(expect.arrayContaining(['Pay your bill online', 'Autopay available']));
    });

    it('drops the payment claims when the company bills another way', () => {
        const labels = badgeLabels(picked, false);

        expect(labels).not.toContain('Pay your bill online');
        expect(labels).not.toContain('Autopay available');
        expect(labels).toHaveLength(1);
    });
});
