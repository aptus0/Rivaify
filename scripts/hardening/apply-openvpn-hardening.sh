#!/usr/bin/env bash
set -euo pipefail

SERVER_NAME="${OPENVPN_SERVER_NAME:-karacabey}"
CONF="/etc/openvpn/server/${SERVER_NAME}.conf"
EASYRSA_DIR="${EASYRSA_DIR:-/etc/openvpn/easy-rsa}"
CRL_SOURCE="${EASYRSA_DIR}/pki/crl.pem"
CRL_TARGET="/etc/openvpn/server/crl.pem"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run with sudo: sudo $0" >&2
  exit 1
fi

if [[ ! -f "${CONF}" ]]; then
  echo "OpenVPN config not found: ${CONF}" >&2
  exit 1
fi

cp "${CONF}" "${CONF}.bak.$(date +%Y%m%d%H%M%S)"

# Rivaify private admin should not expose the whole LAN to VPN clients.
sed -i '/^push "route 192\.168\.1\.0 255\.255\.255\.0"/d' "${CONF}"

grep -q '^tls-version-min 1\.2$' "${CONF}" || echo 'tls-version-min 1.2' >> "${CONF}"
grep -q '^tls-crypt ta\.key$' "${CONF}" || echo 'tls-crypt ta.key' >> "${CONF}"

if [[ -x "${EASYRSA_DIR}/easyrsa" ]]; then
  (cd "${EASYRSA_DIR}" && ./easyrsa gen-crl)
elif command -v easyrsa >/dev/null 2>&1; then
  (cd "${EASYRSA_DIR}" && easyrsa gen-crl)
else
  echo "EasyRSA not found; skipping CRL generation." >&2
fi

if [[ -f "${CRL_SOURCE}" ]]; then
  install -o nobody -g nogroup -m 0644 "${CRL_SOURCE}" "${CRL_TARGET}"
  grep -q '^crl-verify crl\.pem$' "${CONF}" || echo 'crl-verify crl.pem' >> "${CONF}"
fi

openvpn --config "${CONF}" --verb 0 --test-crypto >/dev/null 2>&1 || true
systemctl restart "openvpn-server@${SERVER_NAME}"
systemctl --no-pager --full status "openvpn-server@${SERVER_NAME}" || true

echo "OpenVPN hardening applied. LAN route removed; CRL configured when available."
