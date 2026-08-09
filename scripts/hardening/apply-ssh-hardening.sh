#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run with sudo: sudo $0" >&2
  exit 1
fi

TARGET="/etc/ssh/sshd_config.d/99-rivaify-hardening.conf"
BACKUP="${TARGET}.bak.$(date +%Y%m%d%H%M%S)"

if [[ "${RIVAIFY_CONFIRM_SSH_HARDENING:-}" != "I_HAVE_TESTED_VPN_SSH" ]]; then
  cat >&2 <<'MSG'
Refusing to change SSH hardening until VPN SSH has been tested.

Checklist:
  1. Connect to OpenVPN from a second terminal/device.
  2. Confirm SSH works through VPN: ssh temasre24@10.8.0.1
  3. Confirm provider console/recovery access exists.
  4. Re-run with:
     sudo RIVAIFY_CONFIRM_SSH_HARDENING=I_HAVE_TESTED_VPN_SSH scripts/hardening/apply-ssh-hardening.sh
MSG
  exit 1
fi

if [[ -f "${TARGET}" ]]; then
  cp "${TARGET}" "${BACKUP}"
fi

cat > "${TARGET}" <<'EOF'
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
KbdInteractiveAuthentication no
PermitEmptyPasswords no

AllowUsers temasre24

X11Forwarding no
AllowAgentForwarding no
AllowTcpForwarding no
PermitTunnel no
GatewayPorts no

MaxAuthTries 3
MaxSessions 2
LoginGraceTime 20
ClientAliveInterval 300
ClientAliveCountMax 2
EOF

sshd -t
systemctl reload ssh

echo "SSH hardening applied at ${TARGET}."
echo "Keep this session open and verify a new SSH login before disconnecting."
