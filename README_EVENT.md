# Around the Horn — Innovate Alabama × Sloss Tech

Live audience-voting app for the "Around the Horn"–style founder debate. One moderator, three votable
founders, four audience-voted rounds, a running scoreboard, and a winner/closing
screen — all driven from a single admin control deck and surfaced to the LED wall
through URL-addressable OBS browser sources.

---

## ⏱️ One-paragraph runbook (on-site crew)

> On the show laptop, run `docker compose up -d --build`, then
> `docker compose exec innovate-poll php bin/console doctrine:database:create --if-not-exists`,
> `… doctrine:migrations:migrate --no-interaction`, and
> `… app:event:setup moderator '<strong-password>'` to seed the four rounds and create the
> moderator login. In OBS, add two **Browser Sources**: the stage at
> **`http://<laptop-ip>/obs`** (default transparent background so it keys over the
> camera feed; append `?bg=white`, `?bg=chroma`, or `?bg=stage` if you prefer) and the
> QR at **`http://<laptop-ip>/obs/qr`** (auto-updates to the live round). Open
> **`http://<laptop-ip>/admin`**, log in, and run the show from the **Run of Show**
> deck: click **Go LIVE** on Round 1 (audience scans the on-screen QR and votes),
> **Close round** when the segment ends (the scoreboard awards the point), then Go LIVE
> on the next round; after Round 4 click **Reveal winner** to show the champion and their
> charity. Audience phones must be able to reach `<laptop-ip>` (same Wi-Fi/LAN, or use a
> public domain — see below). That's it.

---

## URLs

| Purpose | URL | Notes |
|---|---|---|
| OBS stage (LED wall) | `/obs` | Live round + bars + scoreboard + winner. `?bg=transparent` (default), `?bg=white`, `?bg=chroma` (green key), `?bg=stage` (opaque branded fill) |
| OBS QR source | `/obs/qr` | Auto-updates to the active round's vote URL every 2s |
| Admin / moderator deck | `/admin` | Login required. Activate/close rounds, flip the winner screen |
| Audience vote | `/poll/round1` … `/poll/round4` | Normally reached by scanning the QR; the QR points here automatically |

Replace the host with the show laptop's LAN IP (e.g. `http://192.168.1.50/obs`) or a public
domain. **The QR encodes whatever host the `/obs` page itself was loaded from**, so always
open `/obs` and `/obs/qr` using the same hostname the phones will use (the LAN IP / domain,
**not** `localhost`, or phones can't reach it).

## Deploy

```bash
docker compose up -d --build
docker compose exec innovate-poll php bin/console doctrine:database:create --if-not-exists
docker compose exec innovate-poll php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec innovate-poll php bin/console app:event:setup moderator '<strong-password>'
```

> **Host port:** defaults to `:80`. If `:80` is already taken on the host (e.g. a dev machine running another server), set `HTTP_PORT` first — `HTTP_PORT=8088 docker compose up -d` — and the app is at `http://localhost:8088`.

- **Localhost demo** (current default): works out of the box at `http://localhost` over HTTP.
- **On-site LAN**: no config change needed — just open `/obs` via the laptop's LAN IP so the
  QR encodes that IP. Make sure the venue Wi-Fi lets phones reach the laptop (no client isolation).
- **Public domain + HTTPS**: in `compose.yaml` set `SERVER_NAME=yourdomain.com`, re-add the
  `443:443` port, point DNS at the host — FrankenPHP provisions Let's Encrypt automatically.
- **Always** override `APP_SECRET` for a real deploy: `APP_SECRET=$(openssl rand -hex 32)` in a
  `.env.local` (or host env) next to `compose.yaml`.

## Run of show (from `/admin`)

1. **Go LIVE** on Round 1 → the QR + stage switch to it; audience scans and votes.
2. Talk the segment. The bars update live (blue, with the leader highlighted cyan).
3. **Close round** → the point is awarded to the round winner; the scoreboard updates.
4. Repeat for Rounds 2–4 (only one round is ever live at a time — this is enforced).
5. After Round 4, **Reveal winner** → the stage shows the audience champion + their charity.
   **Resume live** / **Intro** flip the stage back if needed.

---

## License

**MIT.** OpenStreamPoll is published under the MIT License (`LICENSE`, © 2024 Yoan Bernabeu),
which **permits commercial use, modification, distribution, and rebranding** as long as the
copyright + license text are retained — which they are (see `LICENSE` and `NOTICE`). The
upstream `composer.json` said `"license": "proprietary"`, but that was an unset Symfony skeleton
default contradicted by the actual MIT `LICENSE` file and README; we corrected it to `MIT`.
**No licensing blocker for this commissioned, rebranded deployment.** (Innovate Alabama's own
brand assets — `logo.svg`, `favicon.png`, palette, type — are Innovate Alabama's property, not
covered by MIT, and are used here for this activation.)

## Fonts — ACTION NEEDED before launch

The kit specifies **Stardust Condensed / Stardust Local** (200) for display and Times New Roman
(400) for body, but **the Stardust font files are not in the brand-kit zip**. They are *not*
shipped here. Drop `StardustCondensed.woff2` and `StardustLocal.woff2` into `public/fonts/`
(the `@font-face` rules already reference those exact paths) and rebuild — no code change needed.
Until then the UI renders the **approved fallback**: `"Stardust Condensed","Stardust Local",
system-ui, sans-serif` for headings, and a system sans for body. Per the brief, **Times New Roman
is deliberately NOT used as the default UI font** (only via the opt-in `.font-serif-body` class).
See `public/fonts/README-FONTS.txt`.

## Placeholders to confirm with Innovate Alabama

- **Founder names/sectors/charities** are placeholders from the client review mockup (Dana Reaves /
  AgTech, Marcus Hale / Advanced Manufacturing, Priya Nair / Logistics). Edit **one file** —
  `src/Event/EventConfig.php` — then re-run `app:event:setup --reset`.
- **Headshots** are generated initials placeholders (`public/img/founders/f1..f3.svg`). Replace with
  real photos at the same paths.
- **Founder #4** (the out-of-state ecosystem expert) is treated as **moderator/panel-side, not on the
  ballot** (3 votable founders) — confirm this is intended.
- Adding the optional 5th "Final" round from the mockup is a one-entry edit in `EventConfig::rounds()`.

## Hardening notes (public event)

- **Pinned fork**, not a live dependency. App deps are locked (`composer.lock`, `package-lock.json`);
  we build our own image and do **not** pull `yoanbernabeu/openstreampoll:latest`. Do **not** run
  `composer update` / `docker pull` / `npm update` during the event.
- **Admin is auth-gated** (`/admin` requires login). All write/round-control endpoints are POST +
  CSRF and sit under `/admin`. The only audience-reachable write is the vote POST.
- **No default credentials**: the upstream `admin/adminpass` + `user/userpass` fixtures were removed.
- **Anti-stuffing**: per-device cookie identity (fixes the old IP+User-Agent fingerprint that wrongly
  blocked phones sharing the venue NAT) + one-vote-per-round + a cache-backed rate limiter
  (tight per device, generous per source IP for the shared NAT).
- **SQLite tuned for concurrency** (WAL + busy_timeout) for the burst of votes per round.
- Residual risk: fully anonymous QR voting can't be perfectly stuffing-proof; the above *reduces* it.
  For higher assurance, add a per-QR token or a lightweight challenge (out of scope here).

## Load / latency testing (Node 18+, no deps)

With a round LIVE:
```bash
node loadtest/loadtest.mjs round1 75      # 75 concurrent voters
node loadtest/qr-latency.mjs              # end-to-end scan→vote timing
```

**Verified on the build (PHP 8.5.7 / FrankenPHP worker, SQLite WAL):**
- 75 concurrent voters → **75/75 success (100%)**, p50 ≈ 600 ms, p95 ≈ 615 ms, ~112 votes/sec, zero "database is locked".
- QR scan→vote end-to-end ≈ **150 ms** (QR fetch ~120 ms, vote page ~10 ms, POST ~16 ms).
- Activate→vote→close→score→standby→winner flow and per-device dedup all confirmed live.
