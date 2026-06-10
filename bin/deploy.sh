#!/usr/bin/env bash
# Production deploy — called by GitHub Actions on push to main.
set -euo pipefail

APP_DIR="/www/wwwroot/innovatealabama.c3tech.app"
REPO_URL="https://github.com/Complete3-Tech-Solutions/Around-the-horn-LRY.git"
BRANCH="main"

cd "$APP_DIR"

echo "[deploy] $(date -Is) starting"

if [ ! -d .git ]; then
  echo "[deploy] initializing git repository"
  git init
  git remote add origin "$REPO_URL"
fi

git fetch origin "$BRANCH"
git checkout -B "$BRANCH" "origin/$BRANCH"
git reset --hard "origin/$BRANCH"

echo "[deploy] building and restarting containers"
docker compose up -d --build

echo "[deploy] running database migrations"
docker compose exec -T innovate-poll php bin/console doctrine:migrations:migrate --no-interaction

echo "[deploy] $(date -Is) complete"
