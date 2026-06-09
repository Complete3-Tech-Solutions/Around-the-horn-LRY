// End-to-end QR "scan → vote" latency probe (server-side timings).
//
//   BASE=http://localhost node loadtest/qr-latency.mjs
//
// Mirrors what a phone does after scanning: read the active QR target, open the
// vote page, submit a vote. The QR fragment exposes the active poll's shortCode
// (so this can run without decoding the image). Activate a round first.

const BASE = process.env.BASE || 'http://localhost';

async function step(label, fn) {
  const t = performance.now();
  const r = await fn();
  return { label, ms: performance.now() - t, r };
}

async function run() {
  const total0 = performance.now();

  const a = await step('GET /obs/qr/results', () => fetch(`${BASE}/obs/qr/results`).then((r) => r.text()));
  const code = (a.r.match(/qrx-code">([^<]+)</) || [])[1];
  if (!code) {
    console.log('No active poll — activate a round in /admin first.');
    return;
  }

  let cookie = '';
  let token = '';
  const b = await step(`GET /poll/${code}`, async () => {
    const res = await fetch(`${BASE}/poll/${code}`);
    const h = await res.text();
    cookie = (res.headers.getSetCookie?.() || []).map((c) => c.split(';')[0]).join('; ');
    token = (h.match(/name="vote\[_token\]"[^>]*\bvalue="([^"]+)"/) || [])[1] || '';
    return h;
  });

  const c = await step('POST vote', () =>
    fetch(`${BASE}/poll/${code}`, {
      method: 'POST',
      redirect: 'manual',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        Cookie: cookie,
        Origin: BASE,
        Referer: `${BASE}/poll/${code}`,
        'Sec-Fetch-Site': 'same-origin',
      },
      body: new URLSearchParams({ 'vote[choice]': '1', 'vote[_token]': token }),
    }));

  const total = performance.now() - total0;
  console.log(`Active poll: ${code}`);
  for (const s of [a, b, c]) console.log(`  ${s.label.padEnd(22)} ${s.ms.toFixed(0)} ms`);
  console.log(`End-to-end (server side):  ${total.toFixed(0)} ms`);
  console.log(`POST status: ${c.r.status}  (302 = vote accepted)`);
}
run();
