// A small load test with no dependencies: N requests, C at a time, per URL. Prints requests per second and latency percentiles.
// Usage: BASE=http://127.0.0.1:8000 N=600 C=30 node docs/tools/load-test.js /r/your-restaurant / /login
// Run it against a copy of your install with a server that has several PHP workers (PHP-FPM, or PHP_CLI_SERVER_WORKERS=8 php -S ...).
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const N = parseInt(process.env.N || '600');
const C = parseInt(process.env.C || '30');
const urls = process.argv.slice(2).length ? process.argv.slice(2) : ['/'];

async function hit(url, times) {
  let sent = 0, failed = 0; const t = [];
  const start = performance.now();
  await Promise.all(Array.from({ length: C }, async () => {
    while (sent < N) {
      sent++;
      const s = performance.now();
      try { const r = await fetch(BASE + url, { redirect: 'manual' }); await r.arrayBuffer(); if (r.status >= 500) failed++; } catch (e) { failed++; }
      t.push(performance.now() - s);
    }
  }));
  const secs = (performance.now() - start) / 1000;
  t.sort((a, b) => a - b);
  const p = (q) => t[Math.min(t.length - 1, Math.floor(t.length * q))].toFixed(0);
  console.log(`${url.padEnd(28)} ${(N / secs).toFixed(0).padStart(5)} req/s   p50 ${p(0.5)} ms   p95 ${p(0.95)} ms   p99 ${p(0.99)} ms   errors ${failed}`);
}
(async () => { for (const u of urls) { await hit(u); } })();
