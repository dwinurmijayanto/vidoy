<?php
/**
 * API Endpoint untuk Download Video dari veidtr.com
 * Fixed Version - Auto Main Domain Detection via Redirect
 *
 * Changelog:
 * - detectMainDomain() kini pakai GET request kecil (range 0-0) bukan HEAD,
 *   karena banyak CDN server menolak HEAD dan mengembalikan redirect berbeda.
 * - fetchContent() sekarang menerima HTTP 200 dan 206 (partial content).
 * - Tambah fallback: jika GET range gagal, coba HEAD sebagai backup.
 * - extractBaseDomain() lebih robust, strip port jika ada.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Vid7Downloader {
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private $debug = false;
    private $domainCache = [];

    public function __construct($debug = false) {
        $this->debug = $debug;
    }

    private function log($message, $data = null) {
        if ($this->debug) {
            error_log($message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }

    // =========================================================
    //  AUTO DOMAIN DETECTION
    // =========================================================

    /**
     * Detect main domain by following redirects from input URL.
     *
     * Menggunakan GET request dengan Range: bytes=0-0 (bukan HEAD),
     * karena banyak CDN/video server:
     *   1. Menolak HEAD request (HTTP 405)
     *   2. Memberikan redirect berbeda untuk HEAD vs GET
     *
     * Fallback ke HEAD jika GET range gagal.
     * Fallback ke domain asal jika keduanya gagal.
     */
    private function detectMainDomain($url) {
        $inputDomain = $this->extractBaseDomain($url);

        if (isset($this->domainCache[$inputDomain])) {
            $this->log("Domain cache hit: $inputDomain -> " . $this->domainCache[$inputDomain]);
            return $this->domainCache[$inputDomain];
        }

        $this->log("Detecting main domain for: $inputDomain (url: $url)");

        // --- Attempt 1: GET dengan Range bytes=0-0 ---
        // Ini mengikuti redirect persis seperti browser biasa,
        // tapi hanya download 1 byte sehingga sangat ringan.
        $finalDomain = $this->detectViaGetRange($url);

        // --- Attempt 2: HEAD request sebagai fallback ---
        if (!$finalDomain || $finalDomain === $inputDomain) {
            $headDomain = $this->detectViaHead($url);
            // Pakai hasil HEAD jika berbeda dari input (artinya ada redirect nyata)
            if ($headDomain && $headDomain !== $inputDomain) {
                $finalDomain = $headDomain;
            }
        }

        // --- Fallback: pakai domain asal ---
        if (!$finalDomain) {
            $finalDomain = $inputDomain;
        }

        $this->log("Resolved: $inputDomain -> $finalDomain");

        $this->domainCache[$inputDomain] = $finalDomain;
        $this->domainCache[$finalDomain] = $finalDomain;

        return $finalDomain;
    }

    private function detectViaGetRange($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Range: bytes=0-0',   // hanya minta 1 byte, sangat ringan
                'Accept: */*',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
        ]);

        curl_exec($ch);
        $finalUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("GET range: HTTP $httpCode, final URL: $finalUrl");

        if ($curlError) {
            $this->log("GET range cURL error: $curlError");
            return null;
        }

        // HTTP 200, 206 (partial), atau 2xx lain dianggap sukses
        if ($httpCode >= 200 && $httpCode < 300 && $finalUrl) {
            return $this->extractBaseDomain($finalUrl);
        }

        return null;
    }

    private function detectViaHead($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_NOBODY         => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
        ]);

        curl_exec($ch);
        $finalUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("HEAD: HTTP $httpCode, final URL: $finalUrl");

        if ($curlError) {
            $this->log("HEAD cURL error: $curlError");
            return null;
        }

        if ($finalUrl) {
            return $this->extractBaseDomain($finalUrl);
        }

        return null;
    }

    /**
     * Normalize URL ke main domain yang terdeteksi.
     * Contoh: https://cdn2.videy.coach/d/abc  ->  https://vide.la/d/abc
     */
    private function normalizeToMainDomain($url, $mainDomain) {
        $normalized = preg_replace('/https?:\/\/[^\/]+/', 'https://' . $mainDomain, $url);
        $this->log("Normalized URL: $url  ->  $normalized");
        return $normalized;
    }

    // =========================================================
    //  URL HELPERS
    // =========================================================

    private function extractVideoId($url) {
        if (preg_match('/\/(?:e|d|v|watch)\/([a-z0-9]+)/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function isFolder($url) {
        return (bool) preg_match('/\/f\//i', $url);
    }

    private function extractFolderId($url) {
        if (preg_match('/\/f\/([a-z0-9]+)/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract base domain dari URL, strip port jika ada.
     * Contoh: https://cdn2.videy.coach:8080/d/xxx  ->  cdn2.videy.coach
     */
    private function extractBaseDomain($url) {
        if (preg_match('/https?:\/\/([^\/\?#]+)/', $url, $matches)) {
            // Strip port number jika ada (misal :8080)
            return preg_replace('/:\d+$/', '', $matches[1]);
        }
        return '';
    }

    // =========================================================
    //  FOLDER PROCESSING
    // =========================================================

    private function extractVideoUrlsFromFolder($folderUrl) {
        $this->log("Fetching folder: $folderUrl");

        $content = $this->fetchContent($folderUrl);
        if (!$content) {
            $this->log("ERROR: Failed to fetch content");
            return null;
        }

        $this->log("Content fetched successfully, length: " . strlen($content));

        $videos   = [];
        $seenIds  = [];

        $patterns = [
            '/href=["\']\\/([dev])\\/([a-z0-9]+)["\']/i',
            '/href=\\/([dev])\\/([a-z0-9]+)/i',
            '/<a[^>]*href=["\']\\/([dev])\\/([a-z0-9]+)["\']/i',
            '/https?:\\/\\/[^\\/]+\\/([dev])\\/([a-z0-9]+)/i',
            '/(?:onclick|data-[^=]*)=["\']*[^\'"]*\\/([dev])\\/([a-z0-9]+)/i',
        ];

        foreach ($patterns as $patternIndex => $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            $this->log("Pattern $patternIndex found " . count($matches) . " matches");

            foreach ($matches as $match) {
                if (count($match) < 3) continue;

                $path    = strtolower($match[1]);
                $videoId = $match[2];

                if (!in_array($path, ['d', 'e', 'v'])) continue;
                if (isset($seenIds[$videoId])) continue;

                $seenIds[$videoId] = true;
                $videos[] = [
                    'video_id' => $videoId,
                    'path'     => $path,
                    'url'      => "/{$path}/{$videoId}"
                ];

                $this->log("Found video: {$path}/{$videoId}");
            }
        }

        // Fallback: cari ID 8-12 karakter
        if (empty($videos)) {
            $this->log("Trying alternative search...");
            preg_match_all('/(?:\/|href=["\']\/)([dev])\/([a-z0-9]{8,12})/i', $content, $altMatches, PREG_SET_ORDER);

            foreach ($altMatches as $match) {
                $path    = strtolower($match[1]);
                $videoId = $match[2];

                if (isset($seenIds[$videoId])) continue;
                $seenIds[$videoId] = true;

                $videos[] = [
                    'video_id' => $videoId,
                    'path'     => $path,
                    'url'      => "/{$path}/{$videoId}"
                ];
                $this->log("Found video (alternative): {$path}/{$videoId}");
            }
        }

        $this->log("Total unique videos found: " . count($videos));
        return $videos;
    }

    private function extractVideoTitle($content, $videoId) {
        $pattern = '/href="\/[dev]\/' . preg_quote($videoId, '/') . '"[^>]*>.*?<strong>(.*?)<\/strong>/is';
        if (preg_match($pattern, $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        $pattern2 = '/<strong[^>]*>(.*?)<\/strong>[^<]*<[^>]*href="\/[dev]\/' . preg_quote($videoId, '/') . '"/is';
        if (preg_match($pattern2, $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return "Video {$videoId}";
    }

    public function processFolderBatch($folderUrl) {
        $folderId = $this->extractFolderId($folderUrl);
        if (!$folderId) {
            return [
                'success' => false,
                'error'   => 'Invalid folder URL format',
                'message' => 'Format URL folder tidak valid. Pastikan menggunakan format: https://domain/f/FOLDER_ID'
            ];
        }

        $mainDomain     = $this->detectMainDomain($folderUrl);
        $originalDomain = $this->extractBaseDomain($folderUrl);
        $normalizedUrl  = $this->normalizeToMainDomain($folderUrl, $mainDomain);

        $this->log("Processing folder: $folderId");
        $this->log("Original Domain : $originalDomain");
        $this->log("Main Domain     : $mainDomain");
        $this->log("Normalized URL  : $normalizedUrl");

        $folderContent = $this->fetchContent($normalizedUrl);
        if (!$folderContent) {
            return [
                'success'    => false,
                'error'      => 'Failed to fetch folder page',
                'message'    => 'Gagal mengambil halaman folder. Pastikan URL valid dan dapat diakses.',
                'debug_info' => [
                    'folder_id'   => $folderId,
                    'tried_url'   => $normalizedUrl,
                    'main_domain' => $mainDomain,
                ]
            ];
        }

        $this->log("Folder content length: " . strlen($folderContent));

        $videoList = $this->extractVideoUrlsFromFolder($normalizedUrl);
        if ($videoList === null) {
            return [
                'success'    => false,
                'error'      => 'Failed to extract videos from folder',
                'message'    => 'Gagal mengekstrak video dari folder',
                'debug_info' => ['content_preview' => substr($folderContent, 0, 500)]
            ];
        }

        if (empty($videoList)) {
            return [
                'success'      => false,
                'error'        => 'No videos found in this folder',
                'message'      => 'Tidak ada video yang ditemukan.',
                'folder_id'    => $folderId,
                'total_videos' => 0,
                'videos'       => [],
                'debug_info'   => [
                    'folder_url'      => $normalizedUrl,
                    'content_length'  => strlen($folderContent),
                    'content_preview' => substr($folderContent, 0, 1000)
                ]
            ];
        }

        $results      = [];
        $successCount = 0;
        $failCount    = 0;

        foreach ($videoList as $video) {
            $videoUrl = "https://{$mainDomain}" . $video['url'];
            $title    = $this->extractVideoTitle($folderContent, $video['video_id']);

            $this->log("Processing: " . $video['video_id']);

            $result = $this->getDownloadInfo($videoUrl);

            if ($result['success']) {
                $successCount++;
                $results[] = [
                    'success'      => true,
                    'video_id'     => $video['video_id'],
                    'title'        => $title,
                    'download_url' => $result['data']['download_url'],
                    'thumbnail'    => $result['data']['thumbnail'],
                    'embed_url'    => $result['data']['embed_url']
                ];
            } else {
                $failCount++;
                $results[] = [
                    'success'  => false,
                    'video_id' => $video['video_id'],
                    'title'    => $title,
                    'error'    => $result['error']
                ];
            }
        }

        return [
            'success'         => true,
            'type'            => 'folder',
            'folder_id'       => $folderId,
            'original_domain' => $originalDomain,
            'main_domain'     => $mainDomain,
            'total_videos'    => count($videoList),
            'processed'       => count($results),
            'success_count'   => $successCount,
            'fail_count'      => $failCount,
            'videos'          => $results
        ];
    }

    // =========================================================
    //  NETWORK HELPERS
    // =========================================================

    private function videoIdToIframeId($videoId) {
        return bin2hex($videoId);
    }

    /**
     * Fetch content dari URL.
     * Menerima HTTP 200 dan 206 (partial content) sebagai sukses.
     */
    private function fetchContent($url, $referer = null, $returnHeaders = false) {
        $ch = curl_init();

        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
        ];

        if ($referer) {
            $headers[] = 'Referer: ' . $referer;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADER         => $returnHeaders
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $this->log("Fetching: $url");
        $this->log("HTTP Code: $httpCode");

        if ($error) {
            $this->log("cURL error: $error");
            return null;
        }

        // Terima 200 (OK) dan 206 (Partial Content)
        if ($httpCode === 200 || $httpCode === 206) {
            return $response;
        }

        $this->log("Unexpected HTTP code: $httpCode");
        return null;
    }

    // =========================================================
    //  EXTRACTION HELPERS
    // =========================================================

    private function extractEmbedUrl($html, $videoId, $mainDomain) {
        $patterns = [
            '/playerPath\s*=\s*["\']([^"\']+)["\']/',
            '/fullURL\s*=\s*["\']([^"\']+)["\']/',
            '/https?:\/\/[^"\']+\/embed\.php\?[^"\']+/',
            '/src\s*=\s*["\']([^"\']*embed\.php[^"\']*)["\']/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $url = isset($matches[1]) ? $matches[1] : $matches[0];
                $this->log("Found embed URL: $url");
                return $url;
            }
        }

        $fallbackUrl = "https://{$mainDomain}/embed.php?bucket=temporary&id={$videoId}";
        $this->log("Using fallback embed URL: $fallbackUrl");
        return $fallbackUrl;
    }

    private function extractMp4Url($html) {
        $patterns = [
            '/<source\s+src=["\']([^"\']+\.mp4[^"\']*)["\']/',
            '/<source[^>]+src=["\']([^"\']+)["\'][^>]+type=["\']video\/mp4["\']/',
            '/https?:\/\/[^"\'<>\s]+\.mp4[^"\'<>\s]*/',
            '/src:\s*["\']([^"\']+\.mp4[^"\']*)["\']/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $url = isset($matches[1]) ? $matches[1] : $matches[0];
                $url = html_entity_decode($url);
                $this->log("Found MP4 URL: $url");
                return $url;
            }
        }

        return null;
    }

    private function extractPosterUrl($html) {
        if (preg_match('/poster=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function tryDirectEmbed($videoId, $mainDomain) {
        $embedUrl = "https://{$mainDomain}/embed.php?bucket=temporary&id={$videoId}";
        $this->log("Trying direct embed: $embedUrl");

        $content = $this->fetchContent($embedUrl);
        if (!$content) return null;

        $mp4Url = $this->extractMp4Url($content);
        if ($mp4Url) {
            return [
                'embed_url' => $embedUrl,
                'mp4_url'   => $mp4Url,
                'content'   => $content
            ];
        }

        return null;
    }

    // =========================================================
    //  MAIN DOWNLOAD FUNCTION
    // =========================================================

    public function getDownloadInfo($url) {
        if ($this->isFolder($url)) {
            $this->log("Detected folder URL (/f/), processing all videos in batch...");
            return $this->processFolderBatch($url);
        }

        $this->log("Detected single video URL, processing...");

        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            return [
                'success' => false,
                'error'   => 'Invalid URL format.',
                'message' => 'Format URL tidak valid. Gunakan format: https://domain/{e|d|v}/VIDEO_ID'
            ];
        }

        $originalDomain = $this->extractBaseDomain($url);
        $mainDomain     = $this->detectMainDomain($url);
        $normalizedUrl  = $this->normalizeToMainDomain($url, $mainDomain);

        $this->log("Video ID       : $videoId");
        $this->log("Original Domain: $originalDomain");
        $this->log("Main Domain    : $mainDomain");

        // Method 1: Direct embed (fastest)
        $directResult = $this->tryDirectEmbed($videoId, $mainDomain);
        if ($directResult && $directResult['mp4_url']) {
            $posterUrl = $this->extractPosterUrl($directResult['content']);

            return [
                'success' => true,
                'method'  => 'direct_embed',
                'data'    => [
                    'video_id'        => $videoId,
                    'original_domain' => $originalDomain,
                    'main_domain'     => $mainDomain,
                    'download_url'    => $directResult['mp4_url'],
                    'thumbnail'       => $posterUrl,
                    'embed_url'       => $directResult['embed_url'],
                    'title'           => "Video {$videoId}"
                ]
            ];
        }

        // Method 2: Via IP endpoint
        $iframeId  = $this->videoIdToIframeId($videoId);
        $ipUrl     = "https://{$mainDomain}/ip129jk?id={$iframeId}";
        $ipContent = $this->fetchContent($ipUrl, $normalizedUrl);

        if (!$ipContent) {
            return [
                'success' => false,
                'error'   => 'Failed to fetch IP endpoint',
                'message' => 'Gagal mengambil endpoint IP',
                'debug'   => $this->debug ? [
                    'video_id'        => $videoId,
                    'original_domain' => $originalDomain,
                    'main_domain'     => $mainDomain,
                    'iframe_id'       => $iframeId,
                    'ip_url'          => $ipUrl
                ] : null
            ];
        }

        $embedUrl = $this->extractEmbedUrl($ipContent, $videoId, $mainDomain);
        if (!$embedUrl) {
            $embedUrl = "https://{$mainDomain}/embed.php?bucket=temporary&id={$videoId}";
        }

        $embedContent = $this->fetchContent($embedUrl, $ipUrl);
        if (!$embedContent) {
            return [
                'success'   => false,
                'error'     => 'Failed to fetch embed page',
                'message'   => 'Gagal mengambil halaman embed',
                'tried_url' => $embedUrl
            ];
        }

        $mp4Url = $this->extractMp4Url($embedContent);
        if (!$mp4Url) {
            return [
                'success' => false,
                'error'   => 'Failed to extract video URL',
                'message' => 'Gagal mengekstrak URL video',
                'debug'   => $this->debug ? [
                    'embed_content_preview' => substr($embedContent, 0, 500)
                ] : null
            ];
        }

        $posterUrl = $this->extractPosterUrl($embedContent);

        return [
            'success' => true,
            'method'  => 'ip_endpoint',
            'data'    => [
                'video_id'        => $videoId,
                'original_domain' => $originalDomain,
                'main_domain'     => $mainDomain,
                'iframe_id'       => $iframeId,
                'download_url'    => $mp4Url,
                'thumbnail'       => $posterUrl,
                'embed_url'       => $embedUrl,
                'title'           => "Video {$videoId}"
            ]
        ];
    }

    // =========================================================
    //  PROXY DOWNLOAD
    // =========================================================

    public function proxyDownload($videoUrl, $filename = null, $refererDomain = null) {
        if (!$filename) {
            $filename = 'video_' . time() . '.mp4';
        }

        if (!$refererDomain) {
            $refererDomain = $this->extractBaseDomain($videoUrl) ?: 'veidtr.com';
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $videoUrl,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
                'Referer: https://' . $refererDomain . '/'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_WRITEFUNCTION  => function($ch, $data) {
                echo $data;
                return strlen($data);
            }
        ]);

        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        curl_exec($ch);
        curl_close($ch);
    }
}

// =========================================================
//  MAIN EXECUTION
// =========================================================

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $debug      = isset($_GET['debug']) && $_GET['debug'] == '1';
        $downloader = new Vid7Downloader($debug);

        // Test endpoint
        if (isset($_GET['test'])) {
            echo json_encode([
                'success'        => true,
                'message'        => 'API is working!',
                'timestamp'      => date('Y-m-d H:i:s'),
                'php_version'    => PHP_VERSION,
                'curl_available' => function_exists('curl_init')
            ], JSON_PRETTY_PRINT);
            exit;
        }

        // Get info
        if (isset($_GET['url']) && !isset($_GET['download'])) {
            $result = $downloader->getDownloadInfo($_GET['url']);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        // Proxy download
        elseif (isset($_GET['video_url']) && isset($_GET['download'])) {
            $videoUrl      = $_GET['video_url'];
            $filename      = $_GET['filename'] ?? null;
            $refererDomain = $_GET['referer'] ?? null;
            $downloader->proxyDownload($videoUrl, $filename, $refererDomain);
        }
        else {
            echo json_encode([
                'success' => false,
                'error'   => 'Missing parameters',
                'message' => 'Parameter URL tidak ditemukan',
                'usage'   => [
                    'get_info'       => '?url=https://DOMAIN/e/VIDEO_ID (single video)',
                    'get_folder'     => '?url=https://DOMAIN/f/FOLDER_ID (batch all videos)',
                    'get_info_debug' => '?url=https://DOMAIN/e/VIDEO_ID&debug=1',
                    'download'       => '?video_url=VIDEO_URL&download=1&filename=video.mp4'
                ],
                'notes' => [
                    'auto_domain'    => 'Main domain dideteksi otomatis via redirect dari URL input',
                    'subdomain_cdn'  => 'CDN subdomain (mis: cdn2.videy.coach) otomatis di-resolve ke domain utama',
                    '/d/ and /e/'   => 'Single video URLs',
                    '/f/'            => 'Folder URL – otomatis proses semua video secara batch'
                ]
            ], JSON_PRETTY_PRINT);
        }

    } else {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid request method',
            'message' => 'Hanya menerima GET request'
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
