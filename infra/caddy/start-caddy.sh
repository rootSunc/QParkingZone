#!/bin/sh
set -eu

if [ -z "${DOMAIN:-}" ]; then
  echo "DOMAIN must be set" >&2
  exit 1
fi

if [ -n "${WWW_DOMAIN:-}" ]; then
  cat > /etc/caddy/Caddyfile <<EOF
${WWW_DOMAIN} {
  redir https://${DOMAIN}{uri} permanent
}

$(cat /etc/caddy/Caddyfile.tmpl)
EOF
else
  cp /etc/caddy/Caddyfile.tmpl /etc/caddy/Caddyfile
fi

exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
