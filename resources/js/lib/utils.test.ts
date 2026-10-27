import { describe, expect, it } from 'vitest';
import { isEmail } from './utils';

describe('isEmail', () => {
    it('takes ordinary addresses', () => {
        for (const ok of ['jane@example.com', 'jane.doe+pool@mail.example.co.uk', "o'brien@example.org"]) expect(isEmail(ok)).toBe(true);
    });

    it('refuses what the server would', () => {
        for (const bad of [
            '',
            'jane',
            'jane@',
            '@example.com',
            'jane@example',
            'jane doe@example.com',
            'jane..doe@example.com',
            '.jane@example.com',
            'jane@@example.com',
            'jane@-example.com',
        ])
            expect(isEmail(bad)).toBe(false);
    });
});
