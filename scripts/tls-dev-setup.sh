#!/usr/bin/env bash
# Dev TLS: mkcert → .dev-certs/ (localhost, *.localhost, LAN IP).
# Запуск: ./scripts/tls-dev-setup.sh
# Docker: docker compose -f docker-compose.yml -f docker-compose.tls-dev.yml up -d proxy

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
CERT_DIR="${ROOT_DIR}/.dev-certs"
META_FILE="${CERT_DIR}/meta.yaml"

if ! command -v mkcert >/dev/null 2>&1; then
    echo "mkcert не найден. Установите:" >&2
    echo "  macOS:   brew install mkcert && mkcert -install" >&2
    echo "  Linux:   см. https://github.com/FiloSottile/mkcert#installation" >&2
    echo "  Windows: choco install mkcert (или scoop), затем mkcert -install" >&2
    exit 1
fi

mkcert -install 2>/dev/null || true

detect_lan_ip() {
    local ip=""
    local uname_s
    uname_s="$(uname -s 2>/dev/null || echo "")"

    if [ "$uname_s" = "Darwin" ]; then
        ip="$(ipconfig getifaddr en0 2>/dev/null || true)"
        if [ -z "$ip" ]; then
            ip="$(ipconfig getifaddr en1 2>/dev/null || true)"
        fi
    fi

    if [ "$uname_s" = "Linux" ]; then
        ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi

    if [ -z "$ip" ]; then
        ip="$(ip route get 1.1.1.1 2>/dev/null | awk '{for (i=1;i<=NF;i++) if ($i=="src") {print $(i+1); exit}}' || true)"
    fi

    echo "$ip"
}

LAN_IP="$(detect_lan_ip)"
SAN_EXTRA=()
if [ -n "$LAN_IP" ]; then
    SAN_EXTRA+=("$LAN_IP")
    echo "LAN IP: ${LAN_IP}"
else
    echo "LAN IP не определён — только localhost и *.localhost"
fi

mkdir -p "$CERT_DIR"

DOMAINS=(localhost 127.0.0.1 ::1 "*.localhost")
if [ "${#SAN_EXTRA[@]}" -gt 0 ]; then
    DOMAINS+=("${SAN_EXTRA[@]}")
fi

mkcert -cert-file "${CERT_DIR}/cert.pem" -key-file "${CERT_DIR}/key.pem" "${DOMAINS[@]}"

PLATFORM="unknown"
case "$(uname -s)" in
    Darwin) PLATFORM="mac" ;;
    Linux) PLATFORM="linux" ;;
    MINGW*|MSYS*|CYGWIN*) PLATFORM="windows" ;;
esac

{
    echo "# Сгенерировано scripts/tls-dev-setup.sh — не править вручную"
    echo "provider: mkcert"
    echo "platform: ${PLATFORM}"
    echo "generated_at: \"$(date -u +"%Y-%m-%dT%H:%M:%SZ")\""
    echo "cert_file: cert.pem"
    echo "key_file: key.pem"
    echo "san:"
    echo "  - localhost"
    echo "  - 127.0.0.1"
    echo "  - \"*.localhost\""
    if [ -n "$LAN_IP" ]; then
        echo "  - ${LAN_IP}"
    fi
} > "$META_FILE"

echo ""
echo "Готово: ${CERT_DIR}/cert.pem, key.pem"
echo "Подключите proxy:"
echo "  cd \"${ROOT_DIR}\""
echo "  docker compose -f docker-compose.yml -f docker-compose.tls-dev.yml up -d proxy"
if [ -n "$LAN_IP" ]; then
    echo "HTTPS в LAN: https://${LAN_IP}:8443/"
fi
