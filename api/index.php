<?php

header('Content-Type: application/json; charset=utf-8');

// Izinkan akses lintas origin bila endpoint ini dipanggil dari frontend berbeda
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// ============================
// Konfigurasi
// ============================
const BASE_DOMAIN = 'https://vdy.to';
const REQUEST_TIMEOUT = 15; // detik
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

// ============================
// Helper: kirim response JSON lalu keluar
// ============================
function respond(array $data, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================
// 1. Ambil ID video dari parameter "url" atau "id"
// ============================
function extractVideoId(string $urlOrId): ?string
{
    // Kalau input sudah berupa ID polos (tanpa slash/protocol)
    if (preg_match('/^[a-zA-Z0-9]+$/', $urlOrId)) {
        return $urlOrId;
    }

    // Kalau input berupa URL: https://vdy.to/d/f5fn2v5mxec8
    if (preg_match('#/d/([a-zA-Z0-9]+)#', $urlOrId, $m)) {
        return $m[1];
    }

    // Kalau input berupa URL embed: https://vdy.to/embed.php?id=f5fn2v5mxec8
    $parts = parse_url($urlOrId);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $q);
        if (!empty($q['id'])) {
            return $q['id'];
        }
    }

    return null;
}

// ============================
// 2. Ambil HTML halaman embed via cURL
// ============================
function fetchHtml(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => REQUEST_TIMEOUT,
        CURLOPT_USERAGENT      => USER_AGENT,
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml',
            'Referer: ' . BASE_DOMAIN . '/',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $html = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$html, $err, $code];
}

// ============================
// 3. Parse video src & poster dari HTML
// ============================
function parseVideoInfo(string $html): ?array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true); // biar gak spam warning HTML "invalid"
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Cari <video id="player" ...>
    $videoNodes = $xpath->query('//video[@id="player"]');
    if ($videoNodes->length === 0) {
        // fallback: cari tag <video> apa saja
        $videoNodes = $xpath->query('//video');
    }
    if ($videoNodes->length === 0) {
        return null;
    }

    $videoEl = $videoNodes->item(0);
    $poster  = $videoEl->getAttribute('poster') ?: null;

    // Cari <source src="...">
    $sourceNodes = $xpath->query('.//source', $videoEl);
    $src = null;
    if ($sourceNodes->length > 0) {
        $src = $sourceNodes->item(0)->getAttribute('src') ?: null;
    } else {
        // fallback: kadang src langsung di tag <video src="...">
        $src = $videoEl->getAttribute('src') ?: null;
    }

    if (!$src) {
        return null;
    }

    return [
        'video_url'     => $src,
        'thumbnail_url' => $poster,
    ];
}

// ============================
// MAIN
// ============================
$inputUrl = $_GET['url'] ?? $_GET['id'] ?? null;

if (!$inputUrl) {
    respond([
        'success' => false,
        'message' => 'Parameter "url" atau "id" wajib diisi. Contoh: ?url=https://vdy.to/d/f5fn2v5mxec8',
    ], 400);
}

$videoId = extractVideoId($inputUrl);

if (!$videoId) {
    respond([
        'success' => false,
        'message' => 'ID video tidak dapat dikenali dari input yang diberikan.',
    ], 400);
}

$embedUrl = BASE_DOMAIN . '/embed.php?id=' . urlencode($videoId);

[$html, $curlErr, $httpCode] = fetchHtml($embedUrl);

if ($html === false || $curlErr) {
    respond([
        'success' => false,
        'message' => 'Gagal mengambil halaman embed.',
        'error'   => $curlErr,
    ], 502);
}

if ($httpCode >= 400) {
    respond([
        'success' => false,
        'message' => 'Halaman embed mengembalikan status error.',
        'http_code' => $httpCode,
    ], 502);
}

$info = parseVideoInfo($html);

if (!$info) {
    respond([
        'success' => false,
        'message' => 'Tidak dapat menemukan elemen video pada halaman embed. Struktur halaman mungkin sudah berubah.',
    ], 404);
}

respond([
    'success'       => true,
    'id'            => $videoId,
    'video_url'     => $info['video_url'],
    'thumbnail_url' => $info['thumbnail_url'],
]);
