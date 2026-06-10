# CLAUDE.md — Around the Horn (Innovate Alabama × Sloss Tech)

Guidance for **Claude Code** (and humans) working on this repository. **Read this first.**

---

## What this is

A themed, hardened **fork of [OpenStreamPoll](https://github.com/yoanbernabeu/OpenStreamPoll)** (MIT)
customized for a live, "Around the Horn"–style **founder debate** at Sloss Tech for **Innovate Alabama**.
One moderator, **three votable founders**, **four audience-voted rounds**, a running cross-round
**scoreboard**, and a **winner/closing screen** — all driven from one admin control deck and surfaced to
the LED wall through **URL-addressable OBS browser sources**.

- Forked from upstream commit `a0d03b68e8ae63ef7628e9d3fa5c558d45365e4b`.
- Customizations are MIT (see `LICENSE`). Innovate Alabama brand assets are theirs (see `NOTICE`).

## Tech stack

- **PHP 8.x / Symfony 7.2**, served by **FrankenPHP** (Caddy-based, worker mode), in **Docker**.
- **SQLite** (single file, WAL) via **Doctrine ORM + migrations**.
- Frontend: **Twig + Alpine.js + HTMX + Tailwind CSS v3 + daisyUI v4** (Symfony AssetMapper/importmap, no webpack).
- QR codes via `chillerlan/php-qrcode`.

> There is **no local PHP/Composer** assumption — everything runs through Docker.

## Run it

```bash
# from the repo root
docker compose up -d --build
#   …if host :80 is taken (common on dev machines):  HTTP_PORT=8088 docker compose up -d --build
docker compose exec innovate-poll php bin/console doctrine:database:create --if-not-exists
docker compose exec innovate-poll php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec innovate-poll php bin/console app:event:setup moderator '<strong-password>'
```

App at `http://localhost` (or `:8088`). The on-site crew runbook is **`README_EVENT.md`**.

## URLs

| Path | Purpose |
|---|---|
| `/admin` | Moderator control deck (login required) |
| `/obs` | Broadcast display, OBS browser source — `?bg=transparent\|white\|chroma\|stage` |
| `/obs/qr` | Auto-updating QR, OBS browser source |
| `/poll/round1`…`round4` | Audience vote pages (reached via the QR) |

## Architecture map

**Controllers** (`src/Controller`)
- `ObsController` — `/obs`, `/obs/results` (2 s HTMX fragment), `/obs/qr`, `/obs/qr/results`
- `PollController` — `/poll/{shortCode}`: vote form + submit (cookie identity + rate limit)
- `AdminController` — `/admin` index (control-deck data) + poll CRUD
- `RoundController` — `/admin` round activate/close, `/admin` event screen + theme (POST + CSRF)
- `SecurityController`, `HomeController`

**Services / config** (`src/Service`, `src/Event`)
- **`App\Event\EventConfig`** — **SINGLE SOURCE OF TRUTH**: founders + round definitions. **Edit this for the real event.**
- `Service\Scoreboard\ScoreboardService` — cross-round tally, live per-founder results, champion
- `Service\Event\EventStateService` — persisted moderator state (**screen** + **theme**) as JSON on the SQLite volume
- `Service\Visitor\VisitorService` — per-device cookie voter id (`resolveVoterId`)
- `Service\Security\VoteRateLimiter` — cache-backed fixed-window limiter (no extra dependency)
- `Service\Poll\PollService`, `Service\Qr\QrService`, `Service\User\UserService`
- `src\Doctrine\SqlitePragmaListener` — sets SQLite WAL/busy_timeout on connect

**Entities** — `Poll` (title + `question1..5` options + `roundNumber` + `startAt/endAt/isDraft`), `Vote` (poll, `voterId`, `choice`, `createdAt`), `User`.

**Templates** — `templates/obs/*` (stage + scoreboard + qr), `templates/poll/*` (vote/success/error), `templates/admin/*` (index + `_control_deck`), `templates/base*.html.twig`, `templates/components/*`.

## The event model

- A **round** = one `Poll`, tagged with `roundNumber` 1..4. `title` = the debate question; `question1/2/3` = the three founders in **fixed ballot order** (choice index → founder).
- **Exactly one poll is "active" at a time** (`PollService::findOneActive` uses `getOneOrNullResult`). `RoundController::activate` drafts all others to enforce this.
- **Scoreboard**: a round is "decided" once it is no longer the active poll and has votes; the founder(s) with the most votes get **+1** (ties split). See `ScoreboardService`.
- **/obs screens**: `auto` (derived from poll/scoreboard state), `winner`, `intro` — moderator-controlled via `EventStateService`.
- **Theme**: `innovate` (light, default) | `innovate-dark` — the moderator toggle drives admin + audience pages **and** `/obs` + `/obs/qr`.

## Configure for the real event (the only edits normally needed)

1. **`src/Event/EventConfig.php`** — real founder names/sectors/initials/charities + round questions/myths. (Placeholders from the client review mockup are in there now.)
2. **`public/img/founders/f1..f3.svg`** — replace with real headshots (keep the same paths).
3. **`public/fonts/`** — drop `StardustCondensed.woff2` + `StardustLocal.woff2` (NOT in the brand kit; fallback is active). See `public/fonts/README-FONTS.txt`.
4. **`APP_SECRET`** — override for prod via host env or `.env.local` (`openssl rand -hex 32`). **Never** use the committed default in production.
5. **Admin user** — create your own with `app:create-user`; don't ship a demo account.

Then re-seed: `docker compose exec innovate-poll php bin/console app:event:setup --reset`

## CRITICAL GOTCHAS (read before touching the build)

- **Tailwind MUST stay on v3.** `config/packages/symfonycasts_tailwind.yaml` pins `binary_version: v3.4.16`. The bundle otherwise downloads the **latest** CLI (v4), which **ignores `tailwind.config.js` + the daisyUI plugin** → compiles CSS with **no daisyUI** → every admin/audience page renders **unstyled**. If the UI looks broken after a build, check this first.
- **CSS is content-hashed** (AssetMapper). After **any** rebuild the asset filenames change; a browser tab opened before the rebuild references 404'd assets and looks unstyled → **hard-refresh (Ctrl+Shift+R)**. Only matters during dev — a single event-day build is stable.
- **CSRF is stateless/same-origin** (`config/packages/csrf.yaml`: `submit`/`authenticate`/`logout`). Forms render `value="csrf-token"` (a sentinel) and validation is by `Origin`/`Referer`. **Headless POSTs (load tests) must send `Origin` + `Referer`.** The round/event admin controls (`round{id}`, `event_screen`, `event_theme`) use **stateful** session tokens.
- **SQLite single-writer is the scaling ceiling.** Fine for ~300–500 bursty voters (WAL on). For 1000+ or sustained writes, switch `DATABASE_URL` to **Postgres/MySQL** (this is a Doctrine app; `.env` has commented DSNs).
- **Host port 80** may be taken (IIS / another server). Use `HTTP_PORT=8088` — compose maps `${HTTP_PORT:-80}:80`.
- **DB + moderator state live on the `var/sqlite` Docker volume** (`DATABASE_URL` points there; `EventStateService` writes `event-state.json` there). Don't delete the volume mid-event.
- **Deprecations are silenced in prod** (`config/packages/monolog.yaml`) — PHP 8.5 / DBAL emit noisy deprecations otherwise.

## Hardening (already in place)

- Admin behind `ROLE_USER` login + CSRF. **No default credentials** (upstream `admin/adminpass` fixtures removed).
- **Per-device cookie voter id** (fixes the upstream IP+UA fingerprint that collapsed phones on a shared NAT) + one-vote-per-round + rate limiter.
- **Base images pinned by digest** (`Dockerfile`); `composer.lock` + `package-lock.json` pinned; we build our **own** image, not upstream `:latest`.

## Testing

```bash
node loadtest/loadtest.mjs round1 75   # 75 concurrent voters  (verified 100% success, p95 ~615 ms)
node loadtest/qr-latency.mjs           # scan→vote end-to-end  (~150 ms)
```
Run with a round LIVE; set `BASE` / `HTTP_PORT` env to match your host.

## Server sizing (≈300 voters)

Bursty workload (each phone = 1 page load + 1 vote/round). **4 vCPU / 4 GB recommended** (2/2 minimum). The ceiling is SQLite, not CPU/RAM — go Postgres to scale past ~500. Details in `README_EVENT.md`.

## Conventions

- **Brand palette** (Innovate Alabama kit): white `#ffffff`, black `#000000`, blue `#0043e8` (primary / result bars), cyan `#00B2E3` (logo / leading-option highlight), `#28f2e6` (glow/hover tint **only**), gray `#9a9a9a`. **Square buttons** (radius 0). **3 px** spacing grid. Display font = Stardust (with a fallback stack); Times only via the `.font-serif-body` opt-in class.
- **daisyUI themes** live in `tailwind.config.js` (`innovate` light + `innovate-dark`). Brand CSS variables (`--ia-*`) in `assets/styles/app.css`. The `/obs` + `/obs/qr` displays use **hand-written** `assets/styles/obs.css` / `qr.css` (independent of daisyUI).
- Keep changes minimal and match the surrounding Symfony/Twig idiom. PHP CS = `@Symfony` (`make qa-cs-fixer`, `make qa-phpstan`).

## License

MIT (`LICENSE`). Fork of OpenStreamPoll © 2024 Yoan Bernabeu. Innovate Alabama brand assets are their property (`NOTICE`).
