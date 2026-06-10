# Around the Horn — Innovate Alabama × Sloss Tech

Live audience-voting app for an **"Around the Horn"–style founder debate**: three founders, four
voted rounds, a running cross-round scoreboard, and a winner/closing screen — shown on the LED wall
via **OBS browser sources** and voted on from phones via **QR code**.

A themed, hardened **fork of [OpenStreamPoll](https://github.com/yoanbernabeu/OpenStreamPoll)** (MIT).

![status](https://img.shields.io/badge/status-event--ready-0043e8) ![license](https://img.shields.io/badge/license-MIT-black)

## Quick start

```bash
docker compose up -d --build
#   …if host port 80 is taken:  HTTP_PORT=8088 docker compose up -d --build
docker compose exec innovate-poll php bin/console doctrine:database:create --if-not-exists
docker compose exec innovate-poll php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec innovate-poll php bin/console app:event:setup moderator '<strong-password>'
```

Then open **`/admin`** (moderator login), and add **`/obs`** and **`/obs/qr`** as OBS browser sources.

## What's in the box

- **Four rounds** (Why this industry / Why Alabama / Mythbusters / The hardest part), each with the
  three founders as fixed options.
- **Moderator control deck** at `/admin`: Go LIVE / Close a round (one live at a time), reveal the
  winner, and toggle **Light/Dark** for the whole UI + broadcast.
- **/obs broadcast display**: live vote bars (brand blue, cyan leader), the three founders + headshots,
  a persistent cross-round **scoreboard**, a winner + charity closing screen, a persistent logo, and
  selectable backgrounds (`?bg=transparent|white|chroma|stage`) so it composites over a video feed.
- **/obs/qr**: auto-updates to the active round's vote URL.
- **Hardened for a public event**: per-device cookie voting + rate limiting, admin behind login + CSRF,
  no default credentials, SQLite WAL, digest-pinned base images. Verified at **75 concurrent voters (100%)**.

## Documentation

- **[README_EVENT.md](README_EVENT.md)** — on-site crew runbook (deploy, run-of-show, URLs, license + font status).
- **[CLAUDE.md](CLAUDE.md)** — full architecture, dev guide, and **critical gotchas** (read before changing the build).

## Before the real event

Edit **`src/Event/EventConfig.php`** (real founders + round questions), drop real headshots in
`public/img/founders/`, add the licensed Stardust fonts to `public/fonts/`, override `APP_SECRET`, and
create your own admin user — then `app:event:setup --reset`. Full checklist in [CLAUDE.md](CLAUDE.md).

## License

MIT — see [LICENSE](LICENSE) and [NOTICE](NOTICE). Fork of OpenStreamPoll © 2024 Yoan Bernabeu.
Innovate Alabama brand assets (logo, favicon, palette, type) are the property of Innovate Alabama.
