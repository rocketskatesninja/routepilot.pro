import { describe, expect, it } from 'vitest';
import rawVectors from './__fixtures__/chemistry-vectors.json';
import { analyzeReading, calculateLSI, fullAnalysis, getLSIStatus, type PoolLike, type Reading, type Weather } from './chemistry';

interface Vector {
    name: string;
    reading: Reading;
    pool: PoolLike;
    weather: Weather | null;
    expected: ReturnType<typeof fullAnalysis>;
}
const vectors = rawVectors as unknown as Vector[];

/** PHP serializes an empty associative array as `[]`; JS uses `{}`. Same meaning — reconcile for comparison. */
function normalize(expected: Vector['expected']): Vector['expected'] {
    if (Array.isArray(expected.trends?.parameters) && (expected.trends.parameters as unknown[]).length === 0) {
        expected.trends.parameters = {};
    }
    return expected;
}

/**
 * Parity contract: the TS port must reproduce, exactly, the output of the
 * authoritative PHP ChemistryService. The fixture is generated from that PHP
 * engine (regenerate it if the PHP math intentionally changes).
 */
describe('chemistry engine parity with the PHP golden fixture', () => {
    for (const v of vectors) {
        it(v.name, () => {
            expect(fullAnalysis(v.reading, v.pool, { weather: v.weather })).toEqual(normalize(v.expected));
        });
    }
});

// The same LSI golden vectors asserted in tests/Unit/ChemistryGoldenVectorsTest.php.
describe('LSI golden vectors', () => {
    it('typical balanced-ish pool', () => {
        expect(calculateLSI({ temperature: 80, ph: 7.4, alkalinity: 100, calcium_hardness: 250, salt: 1000 })).toBe(-0.16);
    });

    it('warm salt pool trending scale-forming', () => {
        expect(calculateLSI({ temperature: 90, ph: 7.8, alkalinity: 120, calcium_hardness: 400, salt: 3200 })).toBe(0.58);
    });

    it('an empty reading uses documented fallbacks', () => {
        expect(calculateLSI({})).toBe(-0.16);
    });

    it('status boundaries are exclusive at ±0.3', () => {
        expect(getLSIStatus(-0.31).status).toBe('corrosive');
        expect(getLSIStatus(-0.3).status).toBe('balanced');
        expect(getLSIStatus(0.0).status).toBe('balanced');
        expect(getLSIStatus(0.3).status).toBe('balanced');
        expect(getLSIStatus(0.31).status).toBe('scaling');
    });
});

describe('reading analysis', () => {
    it('classifies low / normal / high and derives combined chlorine', () => {
        const a = analyzeReading({ free_chlorine: 1.0, total_chlorine: 3.0, ph: 7.8, alkalinity: 100 });
        expect(a.combined_chlorine).toMatchObject({ value: 2.0, status: 'high' });
        expect(a.free_chlorine.status).toBe('normal');
        expect(a.ph.status).toBe('high');
        expect(a.alkalinity.status).toBe('normal');
        expect(a.salt).toBeUndefined();
    });
});
