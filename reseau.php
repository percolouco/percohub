<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PercoHub — Réseau</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0d1117] text-gray-100 min-h-screen font-mono">
  <nav class="sticky top-0 z-50 bg-[#161b22] border-b border-[#30363d] px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="/" class="text-gray-400 hover:text-white text-sm">← PercoHub</a>
      <span class="text-lg font-bold text-white">🌐 Réseau local</span>
    </div>
    <span id="meta" class="text-xs text-gray-500">—</span>
  </nav>
  <main class="max-w-[1200px] mx-auto px-4 py-6">
    <div class="flex flex-wrap gap-3 items-center mb-4 text-sm">
      <input id="q" type="search" placeholder="Filtrer (IP, nom, fabricant…)" class="bg-[#0d1117] border border-[#30363d] rounded px-3 py-1.5 w-72" />
      <label class="flex items-center gap-1 text-gray-400"><input id="only-online" type="checkbox" checked> connectés seulement</label>
      <label class="flex items-center gap-1 text-gray-400"><input id="only-unnamed" type="checkbox"> sans nom seulement</label>
      <span id="stats" class="text-gray-400 ml-auto"></span>
    </div>
    <div class="overflow-x-auto border border-[#30363d] rounded">
      <table class="w-full text-sm">
        <thead class="bg-[#161b22] text-left text-gray-400"><tr>
          <th class="p-2">État</th><th class="p-2">IP</th><th class="p-2">Nom</th><th class="p-2">Fabricant</th><th class="p-2">MAC</th><th class="p-2">Première vue</th><th class="p-2">Dernière vue</th>
        </tr></thead>
        <tbody id="rows" class="bg-[#161b22]"></tbody>
      </table>
    </div>
    <p class="text-xs text-gray-500 mt-3">Balayage toutes les 5 minutes depuis le NAS. Un fabricant vide ou « MAC aléatoire » = souvent un téléphone qui masque son adresse (Wi-Fi privé). Clique sur un nom pour le modifier.</p>
  </main>
<script>
let DEVICES = [];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt = iso => iso ? new Date(iso).toLocaleString('fr-FR', {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'}) : '—';

async function load() {
  const r = await fetch('/api.php?action=network');
  const d = await r.json();
  DEVICES = d.devices || [];
  document.getElementById('meta').textContent = d.scanned_at ? 'Dernier balayage : ' + fmt(d.scanned_at) : 'Aucun balayage';
  render();
}

function render() {
  const q = document.getElementById('q').value.toLowerCase();
  const onlyOn = document.getElementById('only-online').checked, onlyUn = document.getElementById('only-unnamed').checked;
  const list = DEVICES.filter(d => (!onlyOn || d.online) && (!onlyUn || !d.name) &&
    (!q || [d.ip, d.name, d.vendor, d.mac, d.hostname].join(' ').toLowerCase().includes(q)));
  const on = DEVICES.filter(d => d.online).length, un = DEVICES.filter(d => d.online && !d.name).length;
  document.getElementById('stats').textContent = `${on} connectés · ${un} sans nom · ${DEVICES.length} déjà vus`;
  document.getElementById('rows').innerHTML = list.map(d => `
    <tr class="border-t border-[#30363d]">
      <td class="p-2">${d.online ? '<span class="text-green-400">● en ligne</span>' : '<span class="text-gray-500">○ absent</span>'}</td>
      <td class="p-2">${esc(d.ip)}</td>
      <td class="p-2"><span class="cursor-pointer hover:underline ${d.name ? 'text-white' : 'text-amber-400'}" onclick="rename('${esc(d.mac)}')">${esc(d.name || d.hostname || 'à nommer')}</span></td>
      <td class="p-2 text-gray-300">${esc(d.vendor) || (d.random_mac ? '<span class="text-gray-500">MAC aléatoire</span>' : '—')}</td>
      <td class="p-2 text-xs text-gray-500">${esc(d.mac)}</td>
      <td class="p-2 text-xs text-gray-400">${fmt(d.first_seen)}</td>
      <td class="p-2 text-xs text-gray-400">${fmt(d.last_seen)}</td>
    </tr>`).join('') || '<tr><td colspan="7" class="p-6 text-gray-500">Aucun appareil.</td></tr>';
}

async function rename(mac) {
  const d = DEVICES.find(x => x.mac === mac);
  const name = prompt(`Nom pour ${d.ip} (${d.vendor || 'fabricant inconnu'}) — vide pour effacer :`, d.name || '');
  if (name === null) return;
  const r = await fetch('/api.php?action=network_name', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-PercoHub': '1'}, body: JSON.stringify({mac, name})});
  if ((await r.json()).ok) { d.name = name.trim(); render(); } else alert('Échec de l\'enregistrement');
}

['q', 'only-online', 'only-unnamed'].forEach(id => document.getElementById(id).addEventListener('input', render));
load(); setInterval(load, 60000);
</script>
</body>
</html>
