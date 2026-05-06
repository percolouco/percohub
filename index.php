<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PercoHub — Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/style.css" />
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            base:  '#0d1117',
            card:  '#161b22',
            border:'#30363d',
            muted: '#8b949e',
          }
        }
      }
    }
  </script>
</head>
<body class="dark bg-[#0d1117] text-gray-100 min-h-screen font-mono">

  <!-- ── Navbar ─────────────────────────────────────────────────────────────── -->
  <nav class="sticky top-0 z-50 bg-[#161b22] border-b border-[#30363d] px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="text-2xl">🏠</span>
      <span class="text-lg font-bold text-white tracking-tight">PercoHub</span>
      <span class="text-xs text-gray-500 ml-2 hidden sm:block">Dashboard centralisé</span>
    </div>
    <div class="flex items-center gap-4">
      <!-- Filtres catégories -->
      <div id="filter-bar" class="flex gap-2 flex-wrap justify-end hidden sm:flex"></div>

      <!-- Stats globales -->
      <div id="global-stats" class="flex items-center gap-3 text-sm">
        <span class="text-gray-500">—</span>
      </div>

      <!-- Refresh -->
      <div class="flex items-center gap-2">
        <span id="refresh-timer" class="text-xs text-gray-600">—</span>
        <button id="btn-refresh" onclick="loadAll(true)" title="Rafraîchir maintenant"
          class="text-gray-400 hover:text-white transition text-sm border border-[#30363d] rounded px-2 py-1">
          ↺
        </button>
      </div>
    </div>
  </nav>

  <!-- ── Main ───────────────────────────────────────────────────────────────── -->
  <main class="max-w-[1600px] mx-auto px-4 py-6">

    <!-- ── Mes Applications ────────────────────────────────────────────────────── -->
    <section class="mb-10">
      <!-- En-tête de section -->
      <div class="section-header">
        <h2 class="section-title">🚀 Mes Applications</h2>
        <span class="section-count">5</span>
      </div>

      <!-- Grille des applications -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- App 1: CronHub -->
        <div class="app-card group">
          <div class="flex flex-col h-full">
            <!-- Icône & Titre -->
            <div class="flex items-center gap-3 mb-3">
              <span class="text-3xl">⏰</span>
              <h3 class="text-base font-bold text-white">CronHub</h3>
            </div>
            
            <!-- Description -->
            <p class="text-sm text-gray-400 mb-4 flex-grow">
              Gestionnaire de cron jobs
            </p>
            
            <!-- Bouton -->
            <a href="https://cronhub.nas.percolouco.com" target="_blank" rel="noopener noreferrer"
               class="app-btn">
              Ouvrir
              <span class="text-xs">→</span>
            </a>
          </div>
        </div>

        <!-- App 2: GarageManager -->
        <div class="app-card group">
          <div class="flex flex-col h-full">
            <!-- Icône & Titre -->
            <div class="flex items-center gap-3 mb-3">
              <span class="text-3xl">🚗</span>
              <h3 class="text-base font-bold text-white">GarageManager</h3>
            </div>
            
            <!-- Description -->
            <p class="text-sm text-gray-400 mb-4 flex-grow">
              Gestion véhicules & entretiens
            </p>
            
            <!-- Bouton -->
            <a href="https://garage.nas.percolouco.com" target="_blank" rel="noopener noreferrer"
               class="app-btn">
              Ouvrir
              <span class="text-xs">→</span>
            </a>
          </div>
        </div>

        <!-- App 3: PercoMemo -->
        <div class="app-card group">
          <div class="flex flex-col h-full">
            <!-- Icône & Titre -->
            <div class="flex items-center gap-3 mb-3">
              <span class="text-3xl">📝</span>
              <h3 class="text-base font-bold text-white">PercoMemo</h3>
            </div>

            <!-- Description -->
            <p class="text-sm text-gray-400 mb-4 flex-grow">
              Mémos & notes multimédia
            </p>

            <!-- Bouton -->
            <a href="https://memo.nas.percolouco.com" target="_blank" rel="noopener noreferrer"
               class="app-btn">
              Ouvrir
              <span class="text-xs">→</span>
            </a>
          </div>
        </div>

        <!-- App 4: Planka -->
        <div class="app-card group">
          <div class="flex flex-col h-full">
            <!-- Icône & Titre -->
            <div class="flex items-center gap-3 mb-3">
              <span class="text-3xl">📋</span>
              <h3 class="text-base font-bold text-white">Planka</h3>
            </div>

            <!-- Description -->
            <p class="text-sm text-gray-400 mb-4 flex-grow">
              Kanban & projets
            </p>

            <!-- Bouton -->
            <a href="https://planka.nas.percolouco.com" target="_blank" rel="noopener noreferrer"
               class="app-btn">
              Ouvrir
              <span class="text-xs">→</span>
            </a>
          </div>
        </div>

        <!-- App 5: PrintVault -->
        <div class="app-card group">
          <div class="flex flex-col h-full">
            <!-- Icône & Titre -->
            <div class="flex items-center gap-3 mb-3">
              <span class="text-3xl">🖨️</span>
              <h3 class="text-base font-bold text-white">PrintVault</h3>
            </div>

            <!-- Description -->
            <p class="text-sm text-gray-400 mb-4 flex-grow">
              Fichiers 3D (STL, GCode)
            </p>

            <!-- Bouton -->
            <a href="https://printvault.nas.percolouco.com" target="_blank" rel="noopener noreferrer"
               class="app-btn">
              Ouvrir
              <span class="text-xs">→</span>
            </a>
          </div>
        </div>

      </div>
    </section>

    <!-- Loader -->
    <div id="loader" class="flex flex-col items-center justify-center py-24 gap-4">
      <div class="w-8 h-8 border-4 border-violet-500 border-t-transparent rounded-full animate-spin"></div>
      <p class="text-gray-500 text-sm">Chargement des services…</p>
    </div>

    <!-- Error -->
    <div id="error-panel" class="hidden bg-red-900/30 border border-red-700 rounded-lg p-6 text-center">
      <p class="text-red-400 text-lg">⚠️ Impossible de contacter l'API</p>
      <p id="error-msg" class="text-gray-500 text-sm mt-2"></p>
    </div>

    <!-- Dashboard content -->
    <div id="dashboard" class="hidden space-y-10"></div>

  </main>

  <!-- ── Modal Docker détail ─────────────────────────────────────────────────── -->
  <div id="modal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" onclick="closeModal(event)">
    <div class="bg-[#161b22] border border-[#30363d] rounded-xl max-w-lg w-full p-6 relative">
      <button onclick="document.getElementById('modal').classList.add('hidden')"
        class="absolute top-4 right-4 text-gray-500 hover:text-white text-xl">✕</button>
      <div id="modal-content"></div>
    </div>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
