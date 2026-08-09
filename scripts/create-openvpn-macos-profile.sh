#!/usr/bin/env bash
set -euo pipefail

CLIENT_NAME="${1:-macos-rivaify}"
REMOTE_HOST="${OPENVPN_REMOTE_HOST:-195.87.234.145}"
REMOTE_PORT="${OPENVPN_REMOTE_PORT:-1194}"
REMOTE_PROTO="${OPENVPN_REMOTE_PROTO:-udp}"
EASYRSA_DIR="${EASYRSA_DIR:-/etc/openvpn/easy-rsa}"
SERVER_DIR="${SERVER_DIR:-/etc/openvpn/server}"
OUTPUT_DIR="${OUTPUT_DIR:-}"
CLIENT_KEY_PASSWORD="${OPENVPN_CLIENT_KEY_PASSWORD:-}"
EASYRSA_ARGS=(--batch)
BUILD_ARGS=("${CLIENT_NAME}")

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run with sudo: sudo $0 ${CLIENT_NAME}" >&2
  exit 1
fi

if [[ -z "${OUTPUT_DIR}" ]]; then
  INVOKING_USER="${SUDO_USER:-$(id -un)}"
  INVOKING_HOME="$(getent passwd "${INVOKING_USER}" | cut -d: -f6)"
  if [[ -d "${INVOKING_HOME}/Desktop" ]]; then
    OUTPUT_DIR="${INVOKING_HOME}/Desktop"
  elif [[ -d "${INVOKING_HOME}/Masaüstü" ]]; then
    OUTPUT_DIR="${INVOKING_HOME}/Masaüstü"
  else
    OUTPUT_DIR="${INVOKING_HOME}"
  fi
fi

mkdir -p "${OUTPUT_DIR}"

cd "${EASYRSA_DIR}"
if [[ -n "${CLIENT_KEY_PASSWORD}" ]]; then
  EASYRSA_ARGS+=(--passout=pass:"${CLIENT_KEY_PASSWORD}")
else
  echo "Set OPENVPN_CLIENT_KEY_PASSWORD to create a password-protected client key." >&2
  echo "Refusing to create a nopass VPN profile by default." >&2
  echo "If this is a deliberate emergency exception, set OPENVPN_ALLOW_NOPASS=true." >&2
  if [[ "${OPENVPN_ALLOW_NOPASS:-false}" != "true" ]]; then
    exit 1
  fi
  BUILD_ARGS+=(nopass)
fi

./easyrsa "${EASYRSA_ARGS[@]}" build-client-full "${BUILD_ARGS[@]}"

OUTPUT_FILE="${OUTPUT_DIR}/${CLIENT_NAME}.ovpn"

cat > "${OUTPUT_FILE}" <<EOF
client
dev tun
proto ${REMOTE_PROTO}
remote ${REMOTE_HOST} ${REMOTE_PORT}
resolv-retry infinite
nobind
persist-key
persist-tun
remote-cert-tls server
cipher AES-256-GCM
auth SHA256
auth-nocache
verb 3
key-direction 1

<ca>
$(cat "${SERVER_DIR}/ca.crt")
</ca>
<cert>
$(awk '/BEGIN CERTIFICATE/,/END CERTIFICATE/' "${EASYRSA_DIR}/pki/issued/${CLIENT_NAME}.crt")
</cert>
<key>
$(cat "${EASYRSA_DIR}/pki/private/${CLIENT_NAME}.key")
</key>
<tls-crypt>
$(cat "${SERVER_DIR}/ta.key")
</tls-crypt>
EOF

chmod 600 "${OUTPUT_FILE}"
if [[ -n "${SUDO_USER:-}" ]]; then
  chown "${SUDO_USER}:${SUDO_USER}" "${OUTPUT_FILE}" 2>/dev/null || true
fi

echo "Created ${OUTPUT_FILE}"
echo "Mac import: OpenVPN Connect / Tunnelblick ile bu .ovpn dosyasını aç."
echo "Optional Mac hosts entry for private admin DNS:"
echo "  sudo sh -c 'grep -q \"ins.rivaify.com\" /etc/hosts || echo \"10.8.0.1 ins.rivaify.com\" >> /etc/hosts'"
