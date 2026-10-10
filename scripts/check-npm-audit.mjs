#!/usr/bin/env node
/**
 * npm audit gate with a documented allowlist.
 *
 * Why this exists instead of a bare `npm audit --audit-level=high`:
 * a small number of advisories live in *dev-only* transitive dependencies that
 * have **no upstream fix published yet**. A bare audit would fail CI forever (or
 * force us to lower the severity threshold, which would also hide real issues).
 * This script keeps the high/critical gate for everything else and only waives
 * the specific advisories listed below — each with a date and a reason, so the
 * exception is visible, reviewable, and easy to retire once upstream ships a fix.
 *
 * Retire an entry the moment a fix exists: delete it here and run `npm update`.
 * Dependabot / the monthly audit sweep will surface the fix.
 *
 * Usage: node scripts/check-npm-audit.mjs
 * Exit 0 = clean (modulo the allowlist). Exit 1 = an un-waived high/critical.
 */
import { execSync } from 'node:child_process';

// GHSA id -> human reason. Keep this list as short as possible.
const ALLOW = {
    'GHSA-vfj7-8cjw-p6xm':
        'braces <=3.0.3 stack-exhaustion DoS. Dev-only: reaches us solely via ' +
        'eslint-plugin tooling (@vue/eslint-config-typescript -> fast-glob -> micromatch -> braces), ' +
        'used to glob our own source during lint. Not in the shipped bundle and not reachable by ' +
        'untrusted input. No upstream fix: 3.0.3 is the latest braces release and the advisory covers ' +
        'all versions. Added 2026-10-10 — remove when braces > 3.0.3 ships.',
};

// Severities that gate CI. Matches the old `--audit-level=high`.
const GATING = new Set(['high', 'critical']);

function runAudit() {
    try {
        return execSync('npm audit --json', { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    } catch (err) {
        // npm audit exits non-zero when it finds vulnerabilities; the JSON is still on stdout.
        if (err.stdout) return err.stdout;
        throw err;
    }
}

const report = JSON.parse(runAudit());
const vulns = report.vulnerabilities ?? {};

// Collect the actual advisory objects (the object-typed `via` entries). Package
// rows that are only vulnerable *through* a dependency carry string `via`s and
// no advisory of their own, so this naturally de-dupes to the root advisories.
const advisories = new Map(); // ghsa id -> {title, severity, url}
for (const v of Object.values(vulns)) {
    for (const via of v.via ?? []) {
        if (typeof via !== 'object' || !via.url) continue;
        const id = via.url.split('/').pop();
        advisories.set(id, { title: via.title, severity: via.severity, url: via.url });
    }
}

const waived = [];
const violations = [];
for (const [id, a] of advisories) {
    if (!GATING.has(a.severity)) continue;
    if (ALLOW[id]) waived.push({ id, ...a });
    else violations.push({ id, ...a });
}

if (waived.length) {
    console.log(`\nWaived (documented, no upstream fix): ${waived.length}`);
    for (const w of waived) console.log(`  • [${w.severity}] ${w.id} — ${w.title}`);
}

if (violations.length) {
    console.error(`\n✗ ${violations.length} un-waived high/critical advisory(ies):`);
    for (const v of violations) console.error(`  • [${v.severity}] ${v.id} — ${v.title}\n    ${v.url}`);
    console.error('\nFix by upgrading the dependency. If there is genuinely no upstream fix and it is');
    console.error('dev-only, add it to the ALLOW list in scripts/check-npm-audit.mjs with a reason.');
    process.exit(1);
}

console.log('\n✓ npm audit gate passed (no un-waived high/critical advisories).');
