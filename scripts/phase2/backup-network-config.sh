#!/usr/bin/env bash
# Ngunda Wireless - Phase 2 backup of CURRENT network configuration.
# Only WRITES to /var/backups/nws/<timestamp>/ . It does NOT change any live config.
# Run only after you have reviewed this script:   sudo bash backup-network-config.sh
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then echo "Run with sudo." >&2; exit 1; fi

TS="$(date +%Y%m%d-%H%M%S)"
DEST="/var/backups/nws/network-$TS"
mkdir -p "$DEST"
chmod 700 /var/backups/nws "$DEST"

# Config paths (copied only if they exist)
for p in /etc/netplan /etc/NetworkManager/system-connections /etc/systemd/network \
         /etc/hostapd /etc/dnsmasq.conf /etc/dnsmasq.d /etc/opennds /etc/nftables.conf \
         /etc/iptables /etc/ufw /etc/sysctl.conf /etc/sysctl.d /etc/resolv.conf \
         /etc/systemd/resolved.conf /etc/hosts /etc/ssh/sshd_config /etc/ssh/sshd_config.d; do
  [ -e "$p" ] && cp -a --parents "$p" "$DEST/" || true
done

# Live state snapshots (so we can compare and roll back)
{ ip addr; ip route; ip -6 route; } > "$DEST/ip-state.txt" 2>&1 || true
nft list ruleset > "$DEST/nft-ruleset.txt" 2>&1 || true
iptables-save > "$DEST/iptables-save.txt" 2>&1 || true
sysctl net.ipv4.ip_forward > "$DEST/sysctl-forward.txt" 2>&1 || true
systemctl list-unit-files --state=enabled > "$DEST/enabled-units.txt" 2>&1 || true

tar -C /var/backups/nws -czf "/var/backups/nws/network-$TS.tar.gz" "network-$TS"
chmod 600 "/var/backups/nws/network-$TS.tar.gz"
echo "Backup written: $DEST and /var/backups/nws/network-$TS.tar.gz"
echo "Copy the .tar.gz off this machine (USB / scp) before Phase 3."
