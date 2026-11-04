#!/usr/bin/env python3
"""Inventaire du réseau local : balayage ping + table ARP, fabricant par adresse MAC, historique (première / dernière vue).

Écrit /opt/container/percohub/data/network.json, lu par la page « Réseau » de PercoHub (qui y ajoute les noms donnés à la main).
"""
import datetime as dt, json, os, re, socket, subprocess, tempfile

IFACE, NET = "enp1s0f1", "192.168.1.0/24"
DATA = "/opt/container/percohub/data"
STATE, OUT = f"{DATA}/devices.json", f"{DATA}/network.json"
PREFIXES = "/usr/share/nmap/nmap-mac-prefixes"


def vendors() -> dict[str, str]:
    out = {}
    for line in open(PREFIXES, errors="replace"):
        if line[:1] != "#" and len(line) > 7:
            out[line[:6].upper()] = line[7:].strip()
    return out


def sweep() -> None:
    subprocess.run("seq 1 254 | xargs -P 64 -I{} ping -c1 -W1 -q 192.168.1.{} >/dev/null 2>&1", shell=True)


def neighbours() -> dict[str, str]:
    raw = subprocess.run(["ip", "-4", "neigh", "show", "dev", IFACE], capture_output=True, text=True).stdout
    res = {}
    for line in raw.splitlines():
        m = re.match(r"(\S+) lladdr (\S+) (\S+)", line)
        if m and m.group(3) in ("REACHABLE", "STALE", "DELAY", "PROBE"):
            res[m.group(2).lower()] = m.group(1)
    return res


def hostname(ip: str) -> str:
    try:
        return socket.gethostbyaddr(ip)[0]
    except OSError:
        return ""


def main() -> None:
    now = dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")
    sweep()
    seen = neighbours()
    own = subprocess.run(["cat", f"/sys/class/net/{IFACE}/address"], capture_output=True, text=True).stdout.strip().lower()
    seen[own] = "192.168.1.29"
    try:
        state = json.load(open(STATE))
    except (OSError, ValueError):
        state = {}
    v = vendors()
    for mac, ip in seen.items():
        d = state.setdefault(mac, {"first_seen": now})
        d.update(ip=ip, last_seen=now, vendor=v.get(mac.replace(":", "").upper()[:6], ""),
                 random_mac=mac[1] in "26ae", hostname=hostname(ip) or d.get("hostname", ""))
    devices = [dict(mac=m, online=m in seen, **d) for m, d in state.items()]
    devices.sort(key=lambda d: tuple(int(x) for x in d["ip"].split(".")))
    for path, obj in ((STATE, state), (OUT, {"scanned_at": now, "devices": devices})):
        fd, tmp = tempfile.mkstemp(dir=DATA)
        with os.fdopen(fd, "w") as f:
            json.dump(obj, f, ensure_ascii=False, indent=1)
        os.chmod(tmp, 0o644)
        os.replace(tmp, path)


main()
