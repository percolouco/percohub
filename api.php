<?php
/**
 * PercoHub API
 * Retourne le catalogue des services avec leur statut en temps réel.
 * - Docker containers : via `docker inspect`
 * - Services HTTP (projets PHP) : via curl ping
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'all';

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Noms de tous les conteneurs Docker existants. */
function allDockerNames(): array {
    $raw = shell_exec("docker ps -a --format '{{.Names}}' 2>/dev/null");
    return array_values(array_filter(array_map('trim', explode("\n", (string) $raw))));
}

/** Conteneurs du catalogue sur lesquels on a le droit d'agir (services applicatifs uniquement). */
function controllableContainers(array $config): array {
    $protected = $config['protected'] ?? [];
    $ok = [];
    foreach ($config['categories'] as $cat) {
        if (!empty($cat['readonly']) || ($cat['id'] ?? '') === 'vpn') continue;
        foreach ($cat['services'] as $svc) {
            $name = $svc['docker'] ?? null;
            if (is_string($name) && empty($svc['self']) && !in_array($name, $protected, true)) $ok[$name] = true;
        }
    }
    return array_keys($ok);
}

/** Tous les conteneurs référencés dans le catalogue. */
function catalogContainers(array $config): array {
    $names = [];
    foreach ($config['categories'] as $cat) {
        foreach ($cat['services'] as $svc) {
            $d = $svc['docker'] ?? null;
            if (is_string($d)) $names[] = $d;
            elseif (is_array($d)) array_push($names, ...$d);
        }
    }
    return $names;
}

function dockerStatus(string $name): array {
    $raw = shell_exec("docker inspect --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{end}}' " . escapeshellarg($name) . " 2>/dev/null");
    if (empty($raw)) return ['status' => 'absent', 'health' => null];

    $parts = explode('|', trim($raw));
    $status = $parts[0] ?? 'unknown';
    $health = ($parts[1] ?? '') ?: null;
    if ($health === '') $health = null;

    return ['status' => $status, 'health' => $health];
}

function httpPing(string $url, int $timeout = 3): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'PercoHub/1.0',
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $time = (int) (curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    curl_close($ch);

    $ok = $code >= 200 && $code < 400;
    return ['http_code' => $code, 'ok' => $ok, 'ms' => $time];
}

function resolveServiceStatus(array $service): array {
    $dockerName = $service['docker'] ?? null;
    $url        = $service['url']    ?? null;

    // Self-check: ce service est le dashboard lui-même → toujours online
    if (!empty($service['self'])) {
        return ['online' => true, 'status_text' => 'online', 'docker' => null, 'http' => null];
    }

    $result = [
        'online'      => false,
        'status_text' => 'inconnu',
        'docker'      => null,
        'http'        => null,
    ];

    // 1. Docker check
    if ($dockerName) {
        $d = dockerStatus($dockerName);
        $result['docker'] = $d;

        if ($d['status'] === 'running') {
            $result['online']      = true;
            $result['status_text'] = $d['health'] === 'healthy' ? 'healthy' : 'running';
        } elseif ($d['status'] === 'absent') {
            $result['status_text'] = 'absent';
        } else {
            $result['status_text'] = $d['status']; // exited, paused, restarting...
        }
    }

    // 2. HTTP check (for PHP projects without docker, or in addition)
    if ($url && !$dockerName) {
        $h = httpPing($url);
        $result['http']        = $h;
        $result['online']      = $h['ok'];
        $result['status_text'] = $h['ok'] ? 'online' : ($h['http_code'] > 0 ? "http {$h['http_code']}" : 'offline');
    }

    return $result;
}

// ─── Routes ───────────────────────────────────────────────────────────────────

$configFile = __DIR__ . '/services.json';
if (!file_exists($configFile)) {
    echo json_encode(['error' => 'services.json introuvable'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    echo json_encode(['error' => 'services.json invalide'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'status') {
    // Statut d'un seul service
    $catId  = $_GET['cat']     ?? null;
    $svcIdx = (int)($_GET['idx'] ?? -1);

    $found = null;
    foreach ($config['categories'] as $cat) {
        if ($cat['id'] === $catId && isset($cat['services'][$svcIdx])) {
            $found = $cat['services'][$svcIdx];
            break;
        }
    }

    if (!$found) {
        echo json_encode(['error' => 'service introuvable'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(resolveServiceStatus($found), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'all') {
    // Tous les services avec leur statut
    $output = ['categories' => [], 'generated_at' => date('c'), 'total' => 0, 'online' => 0];

    // Conteneurs Docker absents du catalogue : affichés automatiquement dans « Non classés »
    $unclassified = array_values(array_diff(allDockerNames(), catalogContainers($config)));
    if ($unclassified) {
        $config['categories'][] = [
            'id' => 'unclassified', 'name' => 'Non classés', 'icon' => '❓', 'color' => 'amber', 'readonly' => true,
            'services' => array_map(fn($n) => [
                'name' => $n, 'desc' => 'Conteneur absent du catalogue (à classer dans services.json)', 'docker' => $n, 'tags' => ['non-classé'],
            ], $unclassified),
        ];
    }
    $controllable = controllableContainers($config);

    foreach ($config['categories'] as $cat) {
        $catOut = [
            'id'       => $cat['id'],
            'name'     => $cat['name'],
            'icon'     => $cat['icon'],
            'color'    => $cat['color'],
            'collapsed' => !empty($cat['collapsed']),
            'services' => [],
        ];

        foreach ($cat['services'] as $svc) {
            $statusInfo = resolveServiceStatus($svc);

            $catOut['services'][] = array_merge($svc, [
                'status' => $statusInfo,
                'controllable' => is_string($svc['docker'] ?? null) && in_array($svc['docker'], $controllable, true),
            ]);

            $output['total']++;
            if ($statusInfo['online']) $output['online']++;
        }

        $output['categories'][] = $catOut;
    }

    $output['offline'] = $output['total'] - $output['online'];

    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'control') {
    // Démarrer / arrêter / redémarrer un conteneur applicatif du catalogue (POST + en-tête anti-CSRF)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_PERCOHUB'] ?? '') !== '1') {
        http_response_code(403);
        echo json_encode(['error' => 'requête refusée'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $in   = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = (string) ($in['container'] ?? '');
    $op   = (string) ($in['op'] ?? '');
    if (!in_array($op, ['start', 'stop', 'restart'], true) || !in_array($name, controllableContainers($config), true)) {
        http_response_code(403);
        echo json_encode(['error' => 'action non autorisée sur ce conteneur'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $timeout = 20;
    foreach ($config['categories'] as $cat) {
        foreach ($cat['services'] as $svc) {
            if (($svc['docker'] ?? null) === $name && isset($svc['stop_timeout'])) $timeout = (int) $svc['stop_timeout'];
        }
    }
    $cmd = $op === 'start' ? 'docker start' : "docker $op -t $timeout";
    exec("$cmd " . escapeshellarg($name) . ' 2>&1', $out, $code);
    error_log("PercoHub: $op $name -> code $code (" . ($_SERVER['PHP_AUTH_USER'] ?? $_SERVER['REMOTE_ADDR'] ?? '?') . ')');
    echo json_encode(['ok' => $code === 0, 'container' => $name, 'op' => $op, 'output' => implode("\n", $out)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'config') {
    // Catalogue brut sans status checks
    echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'docker') {
    // Liste tous les containers Docker
    $raw = shell_exec("docker ps -a --format '{{.Names}}|{{.Image}}|{{.Status}}|{{.Ports}}' 2>/dev/null");
    $containers = [];
    foreach (explode("\n", trim($raw)) as $line) {
        if (empty($line)) continue;
        $parts = explode('|', $line);
        $containers[] = [
            'name'   => $parts[0] ?? '',
            'image'  => $parts[1] ?? '',
            'status' => $parts[2] ?? '',
            'ports'  => $parts[3] ?? '',
        ];
    }
    echo json_encode(['containers' => $containers, 'count' => count($containers)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'network') {
    // Inventaire du réseau local (écrit par scanner/netscan.py sur l'hôte) + noms donnés à la main
    $net = json_decode((string) @file_get_contents('/data/network.json'), true) ?: ['scanned_at' => null, 'devices' => []];
    $names = json_decode((string) @file_get_contents('/data/names.json'), true) ?: [];
    foreach ($net['devices'] as &$d) { $d['name'] = $names[$d['mac']] ?? ''; }
    echo json_encode($net, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'network_name') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_PERCOHUB'] ?? '') !== '1') {
        http_response_code(403);
        echo json_encode(['error' => 'requête refusée'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $in   = json_decode(file_get_contents('php://input'), true) ?: [];
    $mac  = strtolower((string) ($in['mac'] ?? ''));
    $name = trim(mb_substr((string) ($in['name'] ?? ''), 0, 60));
    if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
        http_response_code(400);
        echo json_encode(['error' => 'adresse MAC invalide'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $names = json_decode((string) @file_get_contents('/data/names.json'), true) ?: [];
    if ($name === '') unset($names[$mac]); else $names[$mac] = $name;
    $ok = file_put_contents('/data/names.json', json_encode($names, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'action invalide. Utilisez: all, status, config, docker, control, network'], JSON_UNESCAPED_UNICODE);
