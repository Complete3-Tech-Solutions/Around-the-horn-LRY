// Concurrent voter load test — no dependencies, just Node 18+ (uses global fetch).
//
//   node loadtest/loadtest.mjs <shortCode> <concurrency>
//   BASE=http://localhost CODE=round1 N=75 node loadtest/loadtest.mjs
//
// Each simulated voter uses its own cookie jar (a distinct "device"), fetches
// the real vote form (to grab the CSRF token), then POSTs a vote — exactly what
// a phone does. Reports success rate, latency percentiles and throughput.

const BASE = process.env.BASE || 'http://localhost';
const CODE = process.env.CODE || process.argv[2] || 'round1';
const N = parseInt(process.env.N || process.argv[3] || '75', 10);

async function oneVoter(i) {
  const t0 = performance.now();
  const url = `${BASE}/poll/${CODE}`;
  try {
    const g = await fetch(url, { redirect: 'manual' });
    const html = await g.text();
    const cookie = (g.headers.getSetCookie?.() || []).map((c) => c.split(';')[0]).join('; ');
    const m = html.match(/name="vote\[_token\]"[^>]*\bvalue="([^"]+)"/);
    if (!m) return { ok: false, status: g.status, reason: 'no-form/closed', ms: performance.now() - t0 };
    const body = new URLSearchParams({ 'vote[choice]': String(1 + (i % 3)), 'vote[_token]': m[1] });
    // Stateless CSRF is same-origin based, so a real browser's Origin/Referer
    // are what authorize the POST. Send them explicitly from this headless test.
    const p = await fetch(url, {
      method: 'POST',
      redirect: 'manual',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Cookie: cookie,
        Origin: BASE,
        Referer: url,
        'Sec-Fetch-Site': 'same-origin',
      },
      body,
    });
    const ms = performance.now() - t0;
    return { ok: p.status === 302 || p.status === 200, status: p.status, ms };
  } catch (e) {
    return { ok: false, status: 0, reason: String(e.cause?.code || e.message), ms: performance.now() - t0 };
  }
}

async function run() {
  console.log(`Load test → ${N} concurrent voters on ${BASE}/poll/${CODE}\n`);
  const t0 = performance.now();
  const results = await Promise.all(Array.from({ length: N }, (_, i) => oneVoter(i)));
  const wall = performance.now() - t0;

  const ok = results.filter((r) => r.ok).length;
  const lat = results.map((r) => r.ms).sort((a, b) => a - b);
  const pct = (q) => lat[Math.min(lat.length - 1, Math.floor(q * lat.length))].toFixed(0);

  console.log(`Wall time:   ${wall.toFixed(0)} ms`);
  console.log(`Success:     ${ok}/${N}  (${((ok / N) * 100).toFixed(1)}%)`);
  console.log(`Latency ms:  p50=${pct(0.5)}  p95=${pct(0.95)}  max=${lat[lat.length - 1].toFixed(0)}`);
  console.log(`Throughput:  ${(N / (wall / 1000)).toFixed(1)} votes/sec`);

  const errs = results.filter((r) => !r.ok);
  if (errs.length) {
    const by = {};
    for (const e of errs) { const k = e.reason || `HTTP ${e.status}`; by[k] = (by[k] || 0) + 1; }
    console.log('Non-success:', by);
  }
}
run();
