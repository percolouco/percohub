# PercoHub 🏠

Dashboard centralisé pour accéder à tous les services et projets du homelab de Perco.

## Stack

- **Backend** : PHP 8 (lecture `services.json`, check Docker + HTTP)
- **Frontend** : Tailwind CSS CDN + Vanilla JS
- **Données** : `services.json` — catalogue éditable manuellement

## Démarrage

```bash
nohup php -S 0.0.0.0:9300 -t /home/perco/projects/percohub/ > /tmp/percohub-php.log 2>&1 &
```

**URL locale** : http://192.168.1.29:9300

## Fonctionnalités

- ✅ Vue centralisée de tous les services (Docker + projets PHP)
- ✅ Status live (Docker inspect + HTTP ping)
- ✅ Catégories : Mes Projets / Infrastructure / Média / Domotique / Productivité / VPN Firefox
- ✅ Filtre par catégorie (navbar)
- ✅ Stats globales (online/offline)
- ✅ Auto-refresh toutes les 30s
- ✅ Clic sur une carte → ouvre l'URL dans un nouvel onglet
- ✅ Dark theme cohérent avec les autres projets

## Structure

```
percohub/
├── index.php       # Page principale (shell HTML)
├── api.php         # Backend : statuts Docker + HTTP
├── services.json   # Catalogue des services (à éditer)
├── assets/
│   ├── style.css   # Dark theme custom
│   └── app.js      # Frontend : rendu, refresh, filtres
└── README.md
```

## Ajouter un service

Éditer `services.json` — ajouter un objet dans le tableau `services` de la bonne catégorie :

```json
{
  "name": "Mon Service",
  "desc": "Description courte",
  "url": "http://192.168.1.29:PORT",
  "docker": "nom-du-container",
  "tags": ["tag1", "tag2"]
}
```

- `url` : null si pas d'interface web
- `docker` : null si ce n'est pas un container Docker (ex: serveur PHP)
- Si `docker` est renseigné → status via `docker inspect`
- Si seulement `url` → status via HTTP ping

## Ajouter une catégorie

Dans `services.json`, ajouter dans le tableau `categories` :

```json
{
  "id": "ma-categorie",
  "name": "Ma Catégorie",
  "icon": "🔌",
  "color": "violet",
  "services": [...]
}
```

**Couleurs disponibles** : violet, slate, amber, emerald, sky, orange, rose

## API

| Endpoint | Description |
|----------|-------------|
| `/api.php?action=all` | Tous les services avec statuts |
| `/api.php?action=config` | Catalogue brut (sans checks) |
| `/api.php?action=docker` | Liste tous les containers Docker |

## Catégories actuelles

| Catégorie | Services |
|-----------|----------|
| 🛠️ Mes Projets | PercoHub, CronHub, Todo / Mémos, LocOutil, GarageManager, PercoMemo, PrintVault |
| 🔧 Infrastructure | Portainer, Traefik, Grafana, InfluxDB, HomelabDash, Gitea, Gitea Runner, Watchtower, CronMaster |
| 🎬 Média | Plex, Jellyseerr, Sonarr, Radarr, Lidarr, Prowlarr, Jackett, qBittorrent, FlareSolverr |
| 🏠 Domotique | Home Assistant, ESPhome, Syncthing, Grott, Bresser Live, Mosquitto, Frigate, CamWatch |
| 💼 Productivité | Immich, Nextcloud, VSCode, Planka, Actual Budget |
| 🦊 VPN Firefox | Firefox — retak, Firefox — coucouze, Firefox — roxxor, Firefox — xouz, Firefox — nale |
