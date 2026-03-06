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
let lastData = null;

// ── Init ───────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
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

  data.categories.forEach(cat => {
    if (activeFilter !== 'all' && cat.id !== activeFilter) return;

    const section = document.createElement('section');
    section.dataset.cat = cat.id;

    // Header
    const onlineCount = cat.services.filter(s => s.status?.online).length;
    section.innerHTML = `
      <div class="section-header">
        <span class="text-xl">${cat.icon}</span>
        <span class="section-title">${cat.name}</span>
        <span class="section-count">${cat.services.length} services</span>
        <span class="text-xs text-gray-600 ml-auto">${onlineCount}/${cat.services.length} en ligne</span>
      </div>
      <div class="services-grid" id="grid-${cat.id}"></div>
    `;

    dash.appendChild(section);

    const grid = section.querySelector(`#grid-${cat.id}`);
    cat.services.forEach(svc => {
      grid.appendChild(buildCard(svc, cat.color));
    });
  });

  dash.classList.remove('hidden');
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
    <span class="stat-pill border-emerald-800 text-emerald-400 bg-emerald-900/20">
      <span class="status-dot online"></span> ${online}
    </span>
    <span class="stat-pill border-red-800 text-red-400 bg-red-900/20">
      <span class="status-dot offline"></span> ${offline}
    </span>
    <span class="stat-pill border-[#30363d] text-gray-500">
      ${total} services
    </span>
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
