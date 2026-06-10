#!/bin/bash
# Cloudflare Tunnel setup for innovatealabama.c3tech.app
# Bypasses the upstream gateway that terminates HTTPS with a bad certificate.
#
# Prerequisites (Cloudflare dashboard — Zero Trust > Networks > Tunnels):
#   1. Create a tunnel named "innovate-alabama"
#   2. Add a Public Hostname: innovatealabama.c3tech.app -> http://127.0.0.1:80
#   3. Copy the install token from the tunnel page
#
# Usage:
#   sudo bash setup-cloudflare-tunnel.sh <TUNNEL_TOKEN>

set -euo pipefail

if [ "${1:-}" = "" ]; then
  echo "Usage: sudo bash $0 <CLOUDFLARE_TUNNEL_TOKEN>"
  echo ""
  echo "Get the token from:"
  echo "  Cloudflare Zero Trust > Networks > Tunnels > innovate-alabama > Install connector"
  exit 1
fi

TOKEN="$1"

if ! command -v cloudflared >/dev/null 2>&1; then
  echo "Installing cloudflared..."
  curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | gpg --dearmor -o /usr/share/keyrings/cloudflare-main.gpg
  echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared jammy main" > /etc/apt/sources.list.d/cloudflared.list
  apt-get update -qq && apt-get install -y cloudflared
fi

cloudflared service uninstall 2>/dev/null || true
cloudflared service install "$TOKEN"
systemctl enable cloudflared
systemctl restart cloudflared
systemctl status cloudflared --no-pager

echo ""
echo "Tunnel installed. Verify at: https://innovatealabama.c3tech.app/admin"
