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

    foreach ($config['categories'] as $cat) {
        $catOut = [
            'id'       => $cat['id'],
            'name'     => $cat['name'],
            'icon'     => $cat['icon'],
            'color'    => $cat['color'],
            'services' => [],
        ];

        foreach ($cat['services'] as $svc) {
            $statusInfo = resolveServiceStatus($svc);

            $catOut['services'][] = array_merge($svc, [
                'status' => $statusInfo,
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

echo json_encode(['error' => 'action invalide. Utilisez: all, status, config, docker'], JSON_UNESCAPED_UNICODE);
