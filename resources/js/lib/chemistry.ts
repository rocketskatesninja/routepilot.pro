/**
 * Offline chemistry engine — a faithful TypeScript port of the authoritative
 * PHP `App\Services\ChemistryService`, so the field PWA computes LSI + dosing
 * on-device with no network round trip.
 *
 * The PHP implementation is the source of truth; the golden vectors in
 * tests/Unit/ChemistryGoldenVectorsTest.php and the parity test alongside this
 * file (chemistry.test.ts) must agree. Change the math here ONLY in lockstep
 * with the PHP engine.
 *
 * The one non-portable piece is trend history (a DB query in PHP). Here
 * `analyzeTrends` takes the history as an argument; offline it's empty, so no
 * trend adjustment is applied — a safe no-op, not a divergence.
 */

export interface Range {
    min: number;
    max: number;
    unit: string;
    label: string;
}

export type Reading = Record<string, number | null | undefined>;

export interface PoolLike {
    volume_gallons?: number | null;
    sanitizer?: string | null;
    custom_target_ranges?: Record<string, { min?: number; max?: number }> | null;
}

interface DailyWeather {
    precipitation_probability_max?: number | null;
    temperature_2m_max?: number | null;
    temperature_2m_min?: number | null;
    uv_index_max?: number | null;
    wind_speed_10m_max?: number | null;
}
export interface Weather {
    daily?: DailyWeather[];
}

export interface Recommendation {
    parameter: string;
    chemical: string;
    amount: number;
    original_amount?: number;
    unit: string;
    urgency: string;
    action?: string;
    notes: string[];
    adjustments?: string[];
    was_adjusted?: boolean;
    combined?: boolean;
}

/** Global ideal ranges; per-pool overrides merge on top (see targetsFor). Order matters. */
export const DEFAULT_RANGES: Record<string, Range> = {
    free_chlorine: { min: 1.0, max: 3.0, unit: 'ppm', label: 'Free Chlorine' },
    combined_chlorine: { min: 0.0, max: 0.5, unit: 'ppm', label: 'Combined Chlorine' },
    ph: { min: 7.2, max: 7.6, unit: '', label: 'pH' },
    alkalinity: { min: 80.0, max: 120.0, unit: 'ppm', label: 'Total Alkalinity' },
    calcium_hardness: { min: 200.0, max: 400.0, unit: 'ppm', label: 'Calcium Hardness' },
    cyanuric_acid: { min: 30.0, max: 80.0, unit: 'ppm', label: 'Cyanuric Acid (CYA)' },
    salt: { min: 2700.0, max: 3400.0, unit: 'ppm', label: 'Salt' },
    temperature: { min: 78.0, max: 84.0, unit: 'F', label: 'Temperature' },
};

/** Reading parameter → historical column name (where they differ). */
const COLUMN_MAP: Record<string, string> = { temperature: 'water_temperature' };

/** PHP-compatible round: half away from zero, to `precision` decimals. */
function round(value: number, precision = 0): number {
    const f = 10 ** precision;
    const x = value * f;
    // Nudge against binary FP error before the half-away-from-zero step.
    const r = x >= 0 ? Math.floor(x + 0.5 + 1e-9) : Math.ceil(x - 0.5 - 1e-9);
    return r / f;
}

const num = (v: unknown, fallback = 0): number => (v === null || v === undefined || v === '' ? fallback : Number(v));

/** Effective targets for a pool: defaults with per-pool min/max overrides merged in. */
export function targetsFor(pool: PoolLike): Record<string, Range> {
    const ranges: Record<string, Range> = {};
    for (const [k, v] of Object.entries(DEFAULT_RANGES)) ranges[k] = { ...v };

    for (const [param, override] of Object.entries(pool.custom_target_ranges ?? {})) {
        if (!ranges[param]) continue;
        if (override?.min !== undefined && override.min !== null) ranges[param].min = Number(override.min);
        if (override?.max !== undefined && override.max !== null) ranges[param].max = Number(override.max);
    }
    return ranges;
}

/** Langelier Saturation Index for a reading. */
export function calculateLSI(reading: Reading): number {
    const temp = num(reading.temperature, 80);
    const ph = num(reading.ph, 7.4);
    const alkalinity = num(reading.alkalinity, 100);
    const calcium = num(reading.calcium_hardness, 250);
    const tds = num(reading.salt, 1000); // salt doubles as TDS proxy

    const tempC = ((temp - 32) * 5) / 9;
    const a = (Math.log10(tds) - 1) / 10;
    const b = -13.12 * Math.log10(tempC + 273) + 34.55;
    const c = Math.log10(Math.max(calcium, 1)) - 0.4;
    const d = Math.log10(Math.max(alkalinity, 1));

    const phs = 9.3 + a + b - (c + d);
    return round(ph - phs, 2);
}

export interface LSIStatus {
    status: string;
    color: string;
    label: string;
    description: string;
}

export function getLSIStatus(lsi: number): LSIStatus {
    if (lsi < -0.3) {
        return { status: 'corrosive', color: 'red', label: 'Corrosive', description: 'Water is aggressive and may damage surfaces/equipment.' };
    }
    if (lsi > 0.3) {
        return { status: 'scaling', color: 'amber', label: 'Scale-forming', description: 'Water may deposit calcium scale on surfaces.' };
    }
    return { status: 'balanced', color: 'green', label: 'Balanced', description: 'Water chemistry is in ideal balance.' };
}

/** Combined chlorine (chloramines) is derived: total − free. */
function withCombinedChlorine(reading: Reading): Reading {
    const total = reading.total_chlorine;
    const free = reading.free_chlorine;
    if (total !== null && total !== undefined && free !== null && free !== undefined && reading.combined_chlorine === undefined) {
        return { ...reading, combined_chlorine: Math.max(0, Number(total) - Number(free)) };
    }
    return reading;
}

export interface ParamAnalysis {
    value: number;
    status: string;
    label: string;
    unit: string;
    min: number;
    max: number;
}

export function analyzeReading(reading: Reading, ranges: Record<string, Range> = DEFAULT_RANGES): Record<string, ParamAnalysis> {
    const r = withCombinedChlorine(reading);
    const analysis: Record<string, ParamAnalysis> = {};

    for (const [param, range] of Object.entries(ranges)) {
        const value = r[param];
        if (value === null || value === undefined) continue;

        let status = 'normal';
        if (value < range.min) status = 'low';
        if (value > range.max) status = 'high';

        analysis[param] = { value: Number(value), status, label: range.label, unit: range.unit, min: range.min, max: range.max };
    }
    return analysis;
}

export interface Trend {
    direction: string;
    average: number;
    readings_count: number;
    out_of_range_count: number;
    is_chronic: boolean;
}
export interface Trends {
    has_history: boolean;
    parameters: Record<string, Trend>;
}

/**
 * Trend analysis over recent readings. `history` is newest-first, each entry a
 * column-keyed reading (matching the DB columns). Offline this is typically
 * empty → has_history false, no adjustment.
 */
export function analyzeTrends(history: Reading[], currentReading: Reading, ranges: Record<string, Range> = DEFAULT_RANGES): Trends {
    const current = withCombinedChlorine(currentReading);
    if (history.length === 0) return { has_history: false, parameters: {} };

    const parameters: Record<string, Trend> = {};

    for (const [param, range] of Object.entries(ranges)) {
        const currentValue = current[param];
        if (currentValue === null || currentValue === undefined) continue;

        const values: number[] =
            param === 'combined_chlorine'
                ? history
                      .map((r) => {
                          const total = r.total_chlorine;
                          const free = r.free_chlorine;
                          return total !== null && total !== undefined && free !== null && free !== undefined
                              ? Math.max(0, Number(total) - Number(free))
                              : null;
                      })
                      .filter((v): v is number => v !== null)
                : history
                      .map((r) => r[COLUMN_MAP[param] ?? param])
                      .filter((v): v is number => v !== null && v !== undefined)
                      .map(Number);

        if (values.length === 0) continue;

        const avg = values.reduce((s, v) => s + v, 0) / values.length;
        const outOfRange = values.filter((v) => v < range.min || v > range.max).length;
        const cur = Number(currentValue);

        const direction = cur > avg * 1.05 ? 'rising' : cur < avg * 0.95 ? 'falling' : 'stable';

        parameters[param] = {
            direction,
            average: round(avg, 1),
            readings_count: values.length,
            out_of_range_count: outOfRange,
            is_chronic: outOfRange >= 3,
        };
    }

    return { has_history: true, parameters };
}

const pct = (factor: number): string => `${round(factor * 100)}%`;

/** Base dosage for an out-of-range parameter, scaled by pool volume. */
function getBaseDosage(param: string, data: ParamAnalysis, volumeGallons: number, sanitizer: string): Recommendation | null {
    const factor = volumeGallons / 10000;

    switch (param) {
        case 'free_chlorine':
            return data.status === 'low'
                ? {
                      parameter: data.label,
                      chemical: sanitizer === 'salt' ? 'Run chlorine generator' : 'Granular Chlorine (Cal-Hypo)',
                      amount: round(2.0 * factor, 1),
                      unit: sanitizer === 'salt' ? 'hours extra runtime' : 'oz',
                      urgency: 'high',
                      notes: [],
                  }
                : null;
        case 'ph':
            return {
                parameter: data.label,
                chemical: data.status === 'high' ? 'Muriatic Acid' : 'Soda Ash',
                amount: round((data.status === 'high' ? 8.0 : 6.0) * factor, 1),
                unit: 'oz',
                urgency: 'medium',
                notes: [],
            };
        case 'alkalinity':
            return data.status === 'low'
                ? {
                      parameter: data.label,
                      chemical: 'Sodium Bicarbonate (Baking Soda)',
                      amount: round(1.5 * factor, 1),
                      unit: 'lbs',
                      urgency: 'medium',
                      notes: [],
                  }
                : {
                      parameter: data.label,
                      chemical: 'Muriatic Acid',
                      amount: round(12.0 * factor, 1),
                      unit: 'oz',
                      urgency: 'medium',
                      notes: ['Add in small increments with pump running. Retest after 4 hours.'],
                  };
        case 'calcium_hardness':
            return data.status === 'low'
                ? {
                      parameter: data.label,
                      chemical: 'Calcium Chloride',
                      amount: round(1.25 * factor, 1),
                      unit: 'lbs',
                      urgency: 'low',
                      notes: ['Dissolve in bucket of water before adding to pool.'],
                  }
                : null;
        case 'cyanuric_acid':
            return data.status === 'low'
                ? {
                      parameter: data.label,
                      chemical: 'Cyanuric Acid (Stabilizer)',
                      amount: round(1.0 * factor, 1),
                      unit: 'lbs',
                      urgency: 'medium',
                      notes: ['Add through skimmer with pump running. Takes 3-5 days to dissolve fully.'],
                  }
                : null;
        case 'salt':
            return data.status === 'low'
                ? {
                      parameter: data.label,
                      chemical: 'Pool Salt',
                      amount: round(40.0 * factor, 1),
                      unit: 'lbs',
                      urgency: 'low',
                      notes: ['Broadcast around pool perimeter. Takes 24 hours to circulate fully.'],
                  }
                : null;
        default:
            return null;
    }
}

const max = (rows: DailyWeather[], key: keyof DailyWeather, fallback: number): number => {
    const vals = rows.map((r) => r[key]).filter((v): v is number => v !== null && v !== undefined);
    return vals.length ? Math.max(...vals) : fallback;
};
const min = (rows: DailyWeather[], key: keyof DailyWeather, fallback: number): number => {
    const vals = rows.map((r) => r[key]).filter((v): v is number => v !== null && v !== undefined);
    return vals.length ? Math.min(...vals) : fallback;
};

function getWeatherAdjustments(param: string, data: ParamAnalysis, weather: Weather, reading: Reading): { factor: number; reason: string }[] {
    const adjustments: { factor: number; reason: string }[] = [];
    const daily = weather.daily ?? [];
    const maxRainProb = max(daily, 'precipitation_probability_max', 0);
    const maxTemp = max(daily, 'temperature_2m_max', 80);
    const maxUV = max(daily, 'uv_index_max', 5);

    if (param === 'free_chlorine' && data.status === 'low') {
        if (maxRainProb > 50) {
            const factor = maxRainProb > 80 ? 0.4 : 0.2;
            adjustments.push({ factor, reason: `+${pct(factor)} — rain expected, will dilute chlorine` });
        }
        if (maxTemp > 90 || maxUV > 7) {
            const factor = maxTemp > 100 ? 0.35 : 0.2;
            adjustments.push({ factor, reason: `+${pct(factor)} — high heat/UV burns chlorine faster` });
        }
        const cya = num(reading.cyanuric_acid, 40);
        if (cya < 30 && maxUV > 5) {
            adjustments.push({ factor: 0.15, reason: '+15% — low CYA with sun exposure (no UV protection)' });
        }
    }

    if (param === 'alkalinity' && data.status === 'low' && maxRainProb > 50) {
        const factor = maxRainProb > 80 ? 0.25 : 0.15;
        adjustments.push({ factor, reason: `+${pct(factor)} — rain will further dilute alkalinity` });
    }

    return adjustments;
}

export function getWeatherAlerts(weather: Weather): { severity: string; message: string }[] {
    const alerts: { severity: string; message: string }[] = [];
    const daily = weather.daily ?? [];
    const maxWind = max(daily, 'wind_speed_10m_max', 0);
    const minTemp = min(daily, 'temperature_2m_min', 50);

    if (maxWind > 25) alerts.push({ severity: 'info', message: 'High winds expected — anticipate extra debris in pools.' });
    if (minTemp <= 32) alerts.push({ severity: 'warning', message: 'Freeze risk — advise running pumps continuously to prevent pipe damage.' });

    return alerts;
}

const needsDrainRefill = (param: string, data: ParamAnalysis): boolean =>
    (param === 'calcium_hardness' || param === 'cyanuric_acid') && data.value > data.max;

const drainRefillSkeleton = (data: ParamAnalysis): Recommendation => ({
    parameter: data.label,
    chemical: 'Partial Drain & Refill',
    amount: 0.0,
    unit: '',
    urgency: 'high',
    action: 'drain_refill',
    notes: [],
});

function joinParameters(...labels: string[]): string {
    const parts: string[] = [];
    for (const label of labels) {
        for (const raw of label.split(' & ')) {
            const part = raw.trim();
            if (part !== '' && !parts.includes(part)) parts.push(part);
        }
    }
    return parts.join(' & ');
}

function applyTrendAdjustment(rec: Recommendation, data: ParamAnalysis, trendData: Trend | undefined, adjustments: string[]): void {
    if (!trendData) return;
    if (trendData.is_chronic) {
        rec.urgency = 'high';
        rec.notes.push(`${data.label} has been out of range in ${trendData.out_of_range_count} of last ${trendData.readings_count} visits.`);
    }
    if (trendData.direction === 'stable' && data.status !== 'normal') {
        rec.amount *= 1.15;
        adjustments.push('+15% — previous treatment did not improve level');
    }
}

function markDrainRefillIfNeeded(rec: Recommendation, param: string, data: ParamAnalysis): void {
    if (param === 'calcium_hardness' && data.value > data.max) {
        rec.action = 'drain_refill';
        rec.notes.push('Calcium hardness is too high to treat chemically. Partial drain and refill recommended.');
        rec.notes.push('Re-test water chemistry after the refill before adding any chemicals.');
    }
    if (param === 'cyanuric_acid' && data.value > data.max) {
        rec.action = 'drain_refill';
        rec.notes.push('CYA is too high to reduce chemically. Partial drain and refill recommended.');
        rec.notes.push('Re-test water chemistry after the refill before adding any chemicals.');
    }
}

function mergeSameChemical(recommendations: Recommendation[]): Recommendation[] {
    const rank: Record<string, number> = { high: 3, medium: 2, low: 1 };
    const merged = new Map<string, Recommendation>();

    recommendations.forEach((rec, i) => {
        const key = (rec.amount ?? 0) > 0 ? `mix:${rec.chemical}|${rec.unit}` : `solo:${i}`;
        const into = merged.get(key);
        if (!into) {
            merged.set(key, rec);
            return;
        }
        into.parameter = joinParameters(into.parameter ?? '', rec.parameter ?? '');
        into.amount = round(into.amount + rec.amount, 1);
        into.original_amount = round((into.original_amount ?? into.amount) + (rec.original_amount ?? rec.amount), 1);
        into.notes = Array.from(new Set([...(into.notes ?? []), ...(rec.notes ?? [])]));
        into.adjustments = [...(into.adjustments ?? []), ...(rec.adjustments ?? [])];
        into.was_adjusted = Boolean(into.was_adjusted) || Boolean(rec.was_adjusted);
        if ((rank[rec.urgency] ?? 0) > (rank[into.urgency] ?? 0)) into.urgency = rec.urgency;
        into.combined = true;
        merged.set(key, into);
    });

    return Array.from(merged.values());
}

function suppressForDrainRefill(recommendations: Recommendation[]): Recommendation[] {
    if (!recommendations.some((r) => r.action === 'drain_refill')) return recommendations;
    const kept = recommendations.filter((r) => r.action === 'drain_refill');
    for (const rec of kept) rec.notes.push('Other chemistry adjustments are skipped — they may not be needed after the refill.');
    return kept;
}

function sortByUrgency(recommendations: Recommendation[]): Recommendation[] {
    const rank: Record<string, number> = { high: 0, medium: 1, normal: 2 };
    // Stable sort (matches PHP 8 usort); preserves insertion order within a tier.
    return recommendations
        .map((r, i) => [r, i] as const)
        .sort((a, b) => (rank[a[0].urgency] ?? 2) - (rank[b[0].urgency] ?? 2) || a[1] - b[1])
        .map(([r]) => r);
}

export function generateRecommendations(
    reading: Reading,
    pool: PoolLike,
    analysis: Record<string, ParamAnalysis>,
    trends: Trends,
    weather?: Weather | null,
): Recommendation[] {
    const recommendations: Recommendation[] = [];
    const volumeGallons = pool.volume_gallons ?? 10000;
    const sanitizer = pool.sanitizer ?? 'chlorine';

    for (const [param, data] of Object.entries(analysis)) {
        if (data.status === 'normal') continue;

        let rec = getBaseDosage(param, data, volumeGallons, sanitizer);
        if (!rec) {
            if (!needsDrainRefill(param, data)) continue;
            rec = drainRefillSkeleton(data);
        }

        const originalAmount = rec.amount;
        const adjustments: string[] = [];

        applyTrendAdjustment(rec, data, trends.parameters[param], adjustments);
        if (weather) {
            for (const adj of getWeatherAdjustments(param, data, weather, reading)) {
                rec.amount *= 1 + adj.factor;
                adjustments.push(adj.reason);
            }
        }
        markDrainRefillIfNeeded(rec, param, data);

        rec.original_amount = originalAmount;
        rec.amount = round(rec.amount, 1);
        rec.adjustments = adjustments;
        rec.was_adjusted = adjustments.length > 0;

        recommendations.push(rec);
    }

    return sortByUrgency(suppressForDrainRefill(mergeSameChemical(recommendations)));
}

export interface FullAnalysis {
    lsi: { value: number } & LSIStatus;
    parameters: Record<string, ParamAnalysis>;
    trends: Trends;
    recommendations: Recommendation[];
    weather_alerts: { severity: string; message: string }[];
}

/** Full on-device analysis, mirroring ChemistryService::fullAnalysis. */
export function fullAnalysis(reading: Reading, pool: PoolLike, options: { weather?: Weather | null; history?: Reading[] } = {}): FullAnalysis {
    const ranges = targetsFor(pool);
    const lsi = calculateLSI(reading);
    const parameters = analyzeReading(reading, ranges);
    const trends = analyzeTrends(options.history ?? [], reading, ranges);
    const recommendations = generateRecommendations(reading, pool, parameters, trends, options.weather);
    const weatherAlerts = options.weather ? getWeatherAlerts(options.weather) : [];

    return {
        lsi: { value: lsi, ...getLSIStatus(lsi) },
        parameters,
        trends,
        recommendations,
        weather_alerts: weatherAlerts,
    };
}
