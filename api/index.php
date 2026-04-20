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
 * - [NEW] processFolderBatch() kini mendukung pagination (?p=1, ?p=2, ...)
 *         Loop otomatis hingga halaman yang tidak mengandung video.
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

    private function detectMainDomain($url) {
        $inputDomain = $this->extractBaseDomain($url);

        if (isset($this->domainCache[$inputDomain])) {
            $this->log("Domain cache hit: $inputDomain -> " . $this->domainCache[$inputDomain]);
            return $this->domainCache[$inputDomain];
        }

        $this->log("Detecting main domain for: $inputDomain (url: $url)");

        $finalDomain = $this->detectViaGetRange($url);

        if (!$finalDomain || $finalDomain === $inputDomain) {
            $headDomain = $this->detectViaHead($url);
            if ($headDomain && $headDomain !== $inputDomain) {
                $finalDomain = $headDomain;
            }
        }

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
                'Range: bytes=0-0',
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

    private function extractBaseDomain($url) {
        if (preg_match('/https?:\/\/([^\/\?#]+)/', $url, $matches)) {
            return preg_replace('/:\d+$/', '', $matches[1]);
        }
        return '';
    }

    /**
     * Build folder page URL.
     * Halaman 1 bisa diakses tanpa parameter maupun dengan ?p=1.
     * Halaman 2+ menggunakan ?p=N.
     */
    private function buildFolderPageUrl($baseFolderUrl, $page) {
        // Hapus parameter ?p yang mungkin sudah ada di URL sebelumnya
        $cleanUrl = preg_replace('/[?&]p=\d+/', '', $baseFolderUrl);
        $cleanUrl = rtrim($cleanUrl, '?&');

        if ($page <= 1) {
            return $cleanUrl;
        }

        $separator = (strpos($cleanUrl, '?') !== false) ? '&' : '?';
        return $cleanUrl . $separator . 'p=' . $page;
    }

    // =========================================================
    //  FOLDER PROCESSING
    // =========================================================

    private function extractVideoUrlsFromFolder($folderUrl) {
        $this->log("Fetching folder page: $folderUrl");

        $content = $this->fetchContent($folderUrl);
        if (!$content) {
            $this->log("ERROR: Failed to fetch content");
            return null;
        }

        $this->log("Content fetched, length: " . strlen($content));

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

        $this->log("Total videos on this page: " . count($videos));

        // Kembalikan juga raw content agar bisa dipakai untuk extract title
        return ['videos' => $videos, 'content' => $content];
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

        // URL dasar folder tanpa parameter paginasi
        $baseFolderUrl = "https://{$mainDomain}/f/{$folderId}";

        $this->log("Processing folder : $folderId");
        $this->log("Original Domain  : $originalDomain");
        $this->log("Main Domain      : $mainDomain");
        $this->log("Base Folder URL  : $baseFolderUrl");

        // -------------------------------------------------------
        //  PAGINATION LOOP
        //  Mulai dari halaman 1, lanjutkan sampai halaman kosong.
        // -------------------------------------------------------
        $allVideos    = [];
        $seenVideoIds = [];
        $pageData     = [];   // simpan content per halaman untuk extract title
        $page         = 1;
        $maxPages     = 100;  // safety cap – hindari loop tak terbatas

        while ($page <= $maxPages) {
            $pageUrl = $this->buildFolderPageUrl($baseFolderUrl, $page);
            $this->log("Fetching page $page: $pageUrl");

            $pageResult = $this->extractVideoUrlsFromFolder($pageUrl);

            if ($pageResult === null) {
                // Gagal fetch – anggap sudah habis
                $this->log("Page $page: failed to fetch, stopping pagination");
                break;
            }

            $pageVideos = $pageResult['videos'];

            if (empty($pageVideos)) {
                // Halaman kosong – tidak ada video lagi
                $this->log("Page $page: no videos found, stopping pagination");
                break;
            }

            $this->log("Page $page: found " . count($pageVideos) . " video(s)");

            // Simpan content halaman ini untuk lookup judul
            $pageData[$page] = $pageResult['content'];

            // Tambahkan video baru (hindari duplikat lintas halaman)
            foreach ($pageVideos as $video) {
                if (!isset($seenVideoIds[$video['video_id']])) {
                    $seenVideoIds[$video['video_id']] = $page;
                    $allVideos[] = array_merge($video, ['page' => $page]);
                }
            }

            $page++;
        }

        $totalPages = $page - 1;
        $this->log("Pagination done. Pages scanned: $totalPages, total videos: " . count($allVideos));

        if (empty($allVideos)) {
            return [
                'success'      => false,
                'error'        => 'No videos found in this folder',
                'message'      => 'Tidak ada video yang ditemukan di folder ini.',
                'folder_id'    => $folderId,
                'total_pages'  => $totalPages,
                'total_videos' => 0,
                'videos'       => []
            ];
        }

        // -------------------------------------------------------
        //  PROSES TIAP VIDEO
        // -------------------------------------------------------
        $results      = [];
        $successCount = 0;
        $failCount    = 0;

        foreach ($allVideos as $video) {
            $videoUrl    = "https://{$mainDomain}" . $video['url'];
            $pageContent = $pageData[$video['page']] ?? '';
            $title       = $this->extractVideoTitle($pageContent, $video['video_id']);

            $this->log("Processing video: " . $video['video_id'] . " (page " . $video['page'] . ")");

            $result = $this->getDownloadInfo($videoUrl);

            if ($result['success']) {
                $successCount++;
                $results[] = [
                    'success'      => true,
                    'video_id'     => $video['video_id'],
                    'title'        => $title,
                    'page'         => $video['page'],
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
                    'page'     => $video['page'],
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
            'total_pages'     => $totalPages,
            'total_videos'    => count($allVideos),
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
                    'get_folder'     => '?url=https://DOMAIN/f/FOLDER_ID (batch ALL pages)',
                    'get_info_debug' => '?url=https://DOMAIN/e/VIDEO_ID&debug=1',
                    'download'       => '?video_url=VIDEO_URL&download=1&filename=video.mp4'
                ],
                'notes' => [
                    'auto_domain'   => 'Main domain dideteksi otomatis via redirect dari URL input',
                    'subdomain_cdn' => 'CDN subdomain otomatis di-resolve ke domain utama',
                    '/d/ and /e/'   => 'Single video URLs',
                    '/f/'           => 'Folder URL – otomatis loop semua halaman (?p=1, ?p=2, ...) sampai habis'
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
