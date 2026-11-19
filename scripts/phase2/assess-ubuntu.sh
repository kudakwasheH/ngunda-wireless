#!/usr/bin/env bash
# Ngunda Wireless - Phase 2 hardware & Ubuntu assessment.
# READ-ONLY: this script changes NOTHING on the system. It only reads and prints.
# Usage:  bash assess-ubuntu.sh        (run as your normal user; uses sudo -n only if passwordless)
# Output: ~/nws-assessment-<timestamp>.txt

OUT="$HOME/nws-assessment-$(date +%Y%m%d-%H%M%S).txt"
SUDO=""
if [ "$(id -u)" -ne 0 ] && sudo -n true 2>/dev/null; then SUDO="sudo -n"; fi

section() { printf '\n===== %s =====\n' "$1"; }
run() { printf '$ %s\n' "$*"; "$@" 2>&1 || echo "(command failed or unavailable)"; }

{
section "TIMESTAMP"; date -Is
section "OS"; run lsb_release -a; run uname -a; run uptime
section "HARDWARE (DMI via /sys, no root needed)"
for f in sys_vendor product_name product_version bios_vendor bios_version bios_date; do
  printf '%s: ' "$f"; cat "/sys/class/dmi/id/$f" 2>/dev/null || echo n/a
done
section "CPU / MEMORY"; run lscpu; run free -h
section "STORAGE"; run lsblk -f; run df -hT -x tmpfs -x devtmpfs
echo "-- Disk encryption (crypto_LUKS present = unattended boot needs a plan):"
lsblk -rno NAME,FSTYPE 2>/dev/null | grep -i crypto_LUKS || echo "no LUKS volumes detected"
section "TPM present?"; ls /sys/class/tpm 2>/dev/null || echo "no TPM device node"

section "NETWORK INTERFACES"; run ip -br link; run ip -br addr; run ip route; run ip -6 route
section "ETHERNET LINK"
for i in $(ls /sys/class/net | grep -v '^lo$'); do
  if [ -d "/sys/class/net/$i/wireless" ]; then echo "$i: wireless"; else
    printf '%s: ' "$i"; command -v ethtool >/dev/null && ethtool "$i" 2>/dev/null | grep -E 'Speed|Link detected' | tr '\n' ' '; echo; fi
done
section "USB / PCI NETWORK DEVICES"; run lsusb; run bash -c "lspci -nnk | grep -iA3 -E 'network|ethernet'"
section "WIRELESS CAPABILITY (need 'AP' in supported modes)"
if command -v iw >/dev/null; then
  run iw dev
  iw list 2>/dev/null | awk '/Wiphy/ {print} /Supported interface modes/ {f=1;print;next} f&&/^\t\t \*/ {print} f&&!/^\t\t \*/ {f=0}'
  echo "-- Interface combinations (max APs / channels):"
  iw list 2>/dev/null | sed -n '/valid interface combinations/,/Device supports/p' | head -n 20
  echo "-- Bands / 5GHz / regulatory:"; run iw reg get
  iw list 2>/dev/null | grep -E 'Band [0-9]|VHT Capabilities|HE Iftypes' | head
else echo "iw not installed (apt install iw is a read-only-safe package, but not done by this script)"; fi
run rfkill list
section "KERNEL DRIVERS FOR WIRELESS"; run bash -c "lsmod | grep -Ei 'mt76|iwl|ath|rtl|brcm|8812|8821'"

section "NETWORK MANAGER / NETPLAN (read only)"
run systemctl is-active systemd-networkd NetworkManager systemd-resolved
ls -l /etc/netplan 2>/dev/null; $SUDO cat /etc/netplan/*.yaml 2>/dev/null || echo "(cannot read netplan yaml without root)"
section "DNS (port 53 conflict check - systemd-resolved stub binds 127.0.0.53)"
run resolvectl status
section "LISTENING PORTS"; $SUDO ss -tulpn 2>&1 || ss -tuln
section "FIREWALL (current rules)"
run bash -c "$SUDO nft list ruleset 2>&1 | head -n 120"
run bash -c "$SUDO iptables -S 2>&1 | head -n 60"
run bash -c "ufw status 2>&1 | head -n 20"
section "FORWARDING"; run sysctl net.ipv4.ip_forward net.ipv4.conf.all.rp_filter
section "SSH EXPOSURE"; run bash -c "ss -tln | grep ':22 '"
section "RELEVANT PACKAGES"
for p in hostapd dnsmasq opennds nftables nginx mysql-server mariadb-server php8.3-fpm php8.1-fpm composer python3 python3-venv iw ethtool fail2ban ufw netplan.io; do
  printf '%-16s ' "$p"; dpkg -s "$p" >/dev/null 2>&1 && dpkg -s "$p" | awk '/^Version/{print $2}' || echo "not installed"
done
run python3 --version; run php --version
section "TIME SYNC"; run timedatectl
section "WAN REACHABILITY (outbound ping only)"
run ping -c 3 -W 2 1.1.1.1
run ping -c 3 -W 2 google.com
section "KNOWN NETWORK CONFIG FILES PRESENT"
ls -ld /etc/hostapd /etc/dnsmasq.conf /etc/dnsmasq.d /etc/opennds /etc/nftables.conf /etc/netplan 2>&1
echo
echo "Assessment complete. File: $OUT"
} 2>&1 | tee "$OUT"
