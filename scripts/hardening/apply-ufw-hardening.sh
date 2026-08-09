#!/usr/bin/env bash
set -euo pipefail

VPN_SUBNET="${VPN_SUBNET:-10.8.0.0/24}"
VPN_PORT="${VPN_PORT:-1194}"
SSH_PORT="${SSH_PORT:-22}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run with sudo: sudo $0" >&2
  exit 1
fi

if ! command -v ufw >/dev/null 2>&1; then
  echo "ufw is not installed. Install ufw or translate these rules to nftables/iptables." >&2
  exit 1
fi

if [[ "${RIVAIFY_CONFIRM_FIREWALL:-}" != "I_HAVE_TESTED_VPN_SSH" ]]; then
  cat >&2 <<MSG
Refusing to apply firewall changes until VPN SSH has been tested.

This model keeps public ${VPN_PORT}/udp open, allows SSH only from ${VPN_SUBNET},
and denies public SSH. Re-run with:

  sudo RIVAIFY_CONFIRM_FIREWALL=I_HAVE_TESTED_VPN_SSH VPN_SUBNET=${VPN_SUBNET} scripts/hardening/apply-ufw-hardening.sh
MSG
  exit 1
fi

ufw --force reset
ufw default deny incoming
ufw default deny routed
ufw default allow outgoing

ufw allow "${VPN_PORT}/udp" comment "OpenVPN"
ufw allow from "${VPN_SUBNET}" to any port "${SSH_PORT}" proto tcp comment "SSH over VPN only"

ufw --force enable
ufw status verbose
