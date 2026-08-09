#!/usr/bin/env bash
set -euo pipefail

CLIENT_NAME="${1:-}"
SERVER_NAME="${OPENVPN_SERVER_NAME:-karacabey}"
EASYRSA_DIR="${EASYRSA_DIR:-/etc/openvpn/easy-rsa}"
CRL_TARGET="/etc/openvpn/server/crl.pem"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run with sudo: sudo $0 client-name" >&2
  exit 1
fi

if [[ -z "${CLIENT_NAME}" ]]; then
  echo "Usage: sudo $0 client-name" >&2
  exit 1
fi

cd "${EASYRSA_DIR}"
./easyrsa --batch revoke "${CLIENT_NAME}"
./easyrsa gen-crl
install -o nobody -g nogroup -m 0644 pki/crl.pem "${CRL_TARGET}"
systemctl restart "openvpn-server@${SERVER_NAME}"

echo "Revoked ${CLIENT_NAME} and refreshed ${CRL_TARGET}."
