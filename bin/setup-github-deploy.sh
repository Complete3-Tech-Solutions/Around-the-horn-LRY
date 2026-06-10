#!/usr/bin/env bash
# One-time setup: push code + configure GitHub Actions secrets.
# Usage:  GITHUB_TOKEN=ghp_xxxx bash bin/setup-github-deploy.sh
set -euo pipefail

REPO="Complete3-Tech-Solutions/Around-the-horn-LRY"
APP_DIR="/www/wwwroot/innovatealabama.c3tech.app"
DEPLOY_KEY_FILE="/root/.ssh/github_actions_deploy"

cd "$APP_DIR"

if [ -z "${GITHUB_TOKEN:-}" ]; then
  echo "ERROR: Set GITHUB_TOKEN to a GitHub PAT with repo + admin:repo_hook (or actions secrets) scope."
  echo "  GITHUB_TOKEN=ghp_xxxx bash bin/setup-github-deploy.sh"
  exit 1
fi

echo "[setup] configuring gh CLI"
echo "$GITHUB_TOKEN" | gh auth login --with-token

echo "[setup] pushing main branch"
git push "https://x-access-token:${GITHUB_TOKEN}@github.com/${REPO}.git" main

echo "[setup] setting GitHub Actions secrets"
gh secret set DEPLOY_HOST --repo "$REPO" --body "24.227.108.162"
gh secret set DEPLOY_USER --repo "$REPO" --body "root"
gh secret set DEPLOY_SSH_KEY --repo "$REPO" < "$DEPLOY_KEY_FILE"

echo "[setup] done — future pushes to main will auto-deploy"
