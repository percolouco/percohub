/**
 * PercoHub — Frontend JS
 * Charge les services depuis api.php, rend les catégories/cards, auto-refresh.
 */

// ── Config ─────────────────────────────────────────────────────────────────

const REFRESH_INTERVAL = 30; // secondes
const API_BASE = '/api.php';

// ── State ──────────────────────────────────────────────────────────────────

let refreshTimer = null;
let countdown = REFRESH_INTERVAL;
let activeFilter = 'all';
let statusFilter = 'all'; // all | online | offline
let searchQuery = '';
let lastData = null;

// ── Init ───────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  initSearch();
  loadAll();
  startCountdown();
});

// ── Load ───────────────────────────────────────────────────────────────────

async function loadAll(forceShow = false) {
  if (forceShow) {
    showLoader();
  }

  try {
    const res = await fetch(`${API_BASE}?action=all`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    lastData = data;

    renderDashboard(data);
    renderGlobalStats(data);
    renderFilterBar(data);
    hideLoader();
    resetCountdown();

  } catch (err) {
    showError(err.message);
  }
}

// ── Render ─────────────────────────────────────────────────────────────────

function renderDashboard(data) {
  const dash = document.getElementById('dashboard');
  dash.innerHTML = '';

  let shown = 0;

  data.categories.forEach(cat => {
    if (activeFilter !== 'all' && cat.id !== activeFilter) return;

    const services = cat.services.filter(matchesFilters);
    if (services.length === 0) return;
    shown += services.length;

    const section = document.createElement('section');
    section.dataset.cat = cat.id;

    // Header
    const onlineCount = services.filter(s => s.status?.online).length;
    section.innerHTML = `
      <div class="section-header">
        <span class="text-xl">${cat.icon}</span>
        <span class="section-title">${cat.name}</span>
        <span class="section-count">${services.length} services</span>
        <span class="text-xs text-gray-600 ml-auto">${onlineCount}/${services.length} en ligne</span>
      </div>
      <div class="services-grid" id="grid-${cat.id}"></div>
    `;

    dash.appendChild(section);

    const grid = section.querySelector(`#grid-${cat.id}`);
    services.forEach(svc => {
      grid.appendChild(buildCard(svc, cat.color));
    });
  });

  const appsShown = applyAppsFilter();
  document.getElementById('no-results').classList.toggle('hidden', shown + appsShown > 0);
  dash.classList.remove('hidden');
}

// ── Recherche & filtres ────────────────────────────────────────────────────

function normalize(str) {
  return (str || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function matchesFilters(svc) {
  if (statusFilter === 'online' && !svc.status?.online) return false;
  if (statusFilter === 'offline' && svc.status?.online) return false;

  const q = normalize(searchQuery).trim();
  if (!q) return true;
  const hay = normalize([svc.name, svc.desc, svc.note, svc.docker, svc.url, ...(svc.tags || [])].join(' '));
  return q.split(/\s+/).every(word => hay.includes(word));
}

// Section statique « Mes Applications » : filtrée par la recherche uniquement
// (pas de statut), masquée quand on filtre par statut ou par catégorie.
function applyAppsFilter() {
  const section = document.getElementById('apps-section');
  if (!section) return 0;
  const q = normalize(searchQuery).trim();
  let visible = 0;
  section.querySelectorAll('.app-card').forEach(card => {
    const hay = normalize(card.textContent);
    const ok = !q || q.split(/\s+/).every(word => hay.includes(word));
    card.classList.toggle('hidden', !ok);
    if (ok) visible++;
  });
  const hideSection = statusFilter !== 'all' || activeFilter !== 'all' || visible === 0;
  section.classList.toggle('hidden', hideSection);
  return hideSection ? 0 : visible;
}

function initSearch() {
  const input = document.getElementById('search-input');
  const clear = document.getElementById('search-clear');

  const update = () => {
    searchQuery = input.value;
    clear.classList.toggle('hidden', !searchQuery);
    if (lastData) renderDashboard(lastData);
  };
  input.addEventListener('input', update);
  clear.addEventListener('click', () => { input.value = ''; update(); input.focus(); });

  document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement !== input && !e.ctrlKey && !e.metaKey) {
      e.preventDefault();
      input.focus();
    } else if (e.key === 'Escape' && document.activeElement === input) {
      input.value = '';
      update();
      input.blur();
    }
  });
}

function setStatusFilter(status) {
  statusFilter = statusFilter === status ? 'all' : status;
  if (lastData) {
    renderDashboard(lastData);
    renderGlobalStats(lastData);
  }
}

function buildCard(svc, catColor) {
  const status = svc.status || {};
  const isOnline = status.online;
  const statusText = status.status_text || 'inconnu';
  const hasUrl = !!svc.url;
  const hasNote = !!svc.note;

  // Status dot class
  let dotClass = 'loading';
  let badgeClass = 'badge-gray';
  let badgeText = statusText;

  if (statusText === 'running' || statusText === 'online') {
    dotClass = 'online'; badgeClass = 'badge-green'; badgeText = 'running';
  } else if (statusText === 'healthy') {
    dotClass = 'online'; badgeClass = 'badge-green'; badgeText = 'healthy';
  } else if (statusText === 'offline' || statusText === 'absent' || statusText === 'exited') {
    dotClass = 'offline'; badgeClass = 'badge-red';
  } else if (statusText === 'restarting') {
    dotClass = 'warn'; badgeClass = 'badge-yellow';
  } else if (statusText === 'inconnu') {
    dotClass = 'loading'; badgeClass = 'badge-gray';
  }

  // Response time badge
  let timeBadge = '';
  if (status.http?.ms && status.http.ms > 0) {
    timeBadge = `<span class="badge badge-blue">${status.http.ms}ms</span>`;
  }

  // Docker badge
  let dockerBadge = '';
  if (svc.docker) {
    dockerBadge = `<span class="badge badge-gray" title="Container: ${svc.docker}">🐳</span>`;
  }

  // Note
  let noteBadge = hasNote ? `<span class="note-tag" title="${svc.note}">${svc.note}</span>` : '';

  // Tags
  const tagsHtml = (svc.tags || []).map(t =>
    `<span class="text-xs text-gray-600 mr-1">#${t}</span>`
  ).join('');

  const card = document.createElement('div');
  card.className = `service-card cat-${catColor} ${hasUrl ? 'has-link' : ''}`;

  if (hasUrl) {
    card.setAttribute('onclick', `openService('${svc.url}', '${svc.name}')`);
    card.setAttribute('title', `Ouvrir ${svc.name}`);
  }

  card.innerHTML = `
    <div class="flex items-start justify-between gap-2 mb-2">
      <div class="flex items-center gap-2 min-w-0">
        <span class="status-dot ${dotClass}" title="${statusText}"></span>
        <span class="font-semibold text-sm text-white truncate">${svc.name}</span>
      </div>
      <div class="flex items-center gap-1.5 flex-shrink-0">
        ${dockerBadge}
        <span class="badge ${badgeClass}">${badgeText}</span>
        ${timeBadge}
      </div>
    </div>
    <p class="text-xs text-gray-500 mb-2 leading-relaxed">${svc.desc}</p>
    <div class="flex items-center justify-between gap-2 flex-wrap">
      <div class="flex flex-wrap gap-1">${tagsHtml}</div>
      ${noteBadge}
      ${hasUrl ? `<span class="text-xs text-gray-700 truncate max-w-[140px]" title="${svc.url}">${formatUrl(svc.url)}</span>` : ''}
    </div>
  `;

  return card;
}

// ── Global stats ───────────────────────────────────────────────────────────

function renderGlobalStats(data) {
  const el = document.getElementById('global-stats');
  const total = data.total || 0;
  const online = data.online || 0;
  const offline = total - online;

  el.innerHTML = `
    <button type="button" onclick="setStatusFilter('online')" title="Afficher uniquement les services en ligne"
      class="stat-pill clickable border-emerald-800 text-emerald-400 bg-emerald-900/20 ${statusFilter === 'online' ? 'active' : ''}">
      <span class="status-dot online"></span> ${online}
    </button>
    <button type="button" onclick="setStatusFilter('offline')" title="Afficher uniquement les services hors ligne"
      class="stat-pill clickable border-red-800 text-red-400 bg-red-900/20 ${statusFilter === 'offline' ? 'active' : ''}">
      <span class="status-dot offline"></span> ${offline}
    </button>
    <button type="button" onclick="setStatusFilter('all')" title="Tout afficher"
      class="stat-pill clickable border-[#30363d] text-gray-500">
      ${total} services
    </button>
  `;
}

// ── Filter bar ─────────────────────────────────────────────────────────────

function renderFilterBar(data) {
  const bar = document.getElementById('filter-bar');
  bar.innerHTML = `
    <button class="filter-btn ${activeFilter === 'all' ? 'active' : ''}" onclick="setFilter('all')">Tout</button>
  `;

  data.categories.forEach(cat => {
    const btn = document.createElement('button');
    btn.className = `filter-btn ${activeFilter === cat.id ? 'active' : ''}`;
    btn.textContent = `${cat.icon} ${cat.name}`;
    btn.onclick = () => setFilter(cat.id);
    bar.appendChild(btn);
  });
}

function setFilter(id) {
  activeFilter = id;
  if (lastData) {
    renderDashboard(lastData);
    renderFilterBar(lastData);
  }
}

// ── Countdown & refresh ────────────────────────────────────────────────────

function startCountdown() {
  refreshTimer = setInterval(() => {
    countdown--;
    const el = document.getElementById('refresh-timer');
    if (el) el.textContent = `↻ ${countdown}s`;

    if (countdown <= 0) {
      loadAll();
    }
  }, 1000);
}

function resetCountdown() {
  countdown = REFRESH_INTERVAL;
}

// ── Helpers ────────────────────────────────────────────────────────────────

function openService(url, name) {
  window.open(url, '_blank', 'noopener');
}

function formatUrl(url) {
  try {
    const u = new URL(url);
    return u.host + (u.port ? '' : '') + (u.pathname !== '/' ? u.pathname : '');
  } catch {
    return url;
  }
}

function showLoader() {
  document.getElementById('loader').classList.remove('hidden');
  document.getElementById('dashboard').classList.add('hidden');
  document.getElementById('error-panel').classList.add('hidden');
}

function hideLoader() {
  document.getElementById('loader').classList.add('hidden');
}

function showError(msg) {
  document.getElementById('loader').classList.add('hidden');
  document.getElementById('error-panel').classList.remove('hidden');
  document.getElementById('error-msg').textContent = msg;
}

function closeModal(e) {
  if (e.target === document.getElementById('modal')) {
    document.getElementById('modal').classList.add('hidden');
  }
}
