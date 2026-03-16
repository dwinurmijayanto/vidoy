<?php
/**
 * API Endpoint untuk Download Video dari veidtr.com
 * Fixed Version - Auto Main Domain Detection via Redirect
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Vid7Downloader {
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private $debug = false;

    // Cache hasil deteksi domain agar tidak fetch ulang untuk domain yang sama
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
     * Returns the final domain after all redirects (e.g. "veidtr.com").
     * Falls back to the original domain if no redirect found.
     */
    private function detectMainDomain($url) {
        $inputDomain = $this->extractBaseDomain($url);

        // Return cached result if already resolved for this domain
        if (isset($this->domainCache[$inputDomain])) {
            $this->log("Domain cache hit: $inputDomain -> " . $this->domainCache[$inputDomain]);
            return $this->domainCache[$inputDomain];
        }

        $this->log("Detecting main domain for: $inputDomain");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,   // ikuti semua redirect
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_NOBODY         => true,   // HEAD request – cukup ambil header
            CURLOPT_HTTPHEADER     => [
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
        ]);

        curl_exec($ch);
        $finalUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // URL setelah redirect
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("Redirect check HTTP $httpCode, final URL: $finalUrl");

        if ($curlError) {
            $this->log("cURL error during domain detection: $curlError");
        }

        // Ambil domain dari URL akhir
        $finalDomain = $finalUrl ? $this->extractBaseDomain($finalUrl) : $inputDomain;

        // Jika tidak berubah (misal redirect ke path yang sama), pakai inputDomain
        if (empty($finalDomain)) {
            $finalDomain = $inputDomain;
        }

        $this->log("Resolved main domain: $inputDomain -> $finalDomain");

        // Simpan ke cache (keduanya arah)
        $this->domainCache[$inputDomain] = $finalDomain;
        $this->domainCache[$finalDomain] = $finalDomain; // domain utama ke dirinya sendiri

        return $finalDomain;
    }

    /**
     * Normalize URL ke main domain yang terdeteksi.
     * Contoh: https://vidi64.com/f/abc  ->  https://veidtr.com/f/abc
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

    private function extractBaseDomain($url) {
        if (preg_match('/https?:\/\/([^\/]+)/', $url, $matches)) {
            return $matches[1];
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

    /**
     * Process folder – deteksi main domain otomatis lalu proses semua video.
     */
    public function processFolderBatch($folderUrl) {
        $folderId = $this->extractFolderId($folderUrl);
        if (!$folderId) {
            return [
                'success' => false,
                'error'   => 'Invalid folder URL format',
                'message' => 'Format URL folder tidak valid. Pastikan menggunakan format: https://domain/f/FOLDER_ID'
            ];
        }

        // Auto-detect main domain via redirect
        $mainDomain    = $this->detectMainDomain($folderUrl);
        $originalDomain = $this->extractBaseDomain($folderUrl);
        $normalizedUrl  = $this->normalizeToMainDomain($folderUrl, $mainDomain);

        $this->log("Processing folder: $folderId");
        $this->log("Original Domain : $originalDomain");
        $this->log("Main Domain     : $mainDomain");
        $this->log("Normalized URL  : $normalizedUrl");

        // Fetch folder content
        $folderContent = $this->fetchContent($normalizedUrl);
        if (!$folderContent) {
            return [
                'success'    => false,
                'error'      => 'Failed to fetch folder page',
                'message'    => 'Gagal mengambil halaman folder. Pastikan URL valid dan dapat diakses.',
                'debug_info' => [
                    'folder_id'  => $folderId,
                    'tried_url'  => $normalizedUrl,
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

        if ($httpCode !== 200) {
            $this->log("Error: " . $error);
            return null;
        }

        return $response;
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

        // Fallback dengan main domain yang terdeteksi
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
        // Folder mode
        if ($this->isFolder($url)) {
            $this->log("Detected folder URL (/f/), processing all videos in batch...");
            return $this->processFolderBatch($url);
        }

        $this->log("Detected single video URL, processing...");

        // Step 1: Extract video ID
        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            return [
                'success' => false,
                'error'   => 'Invalid URL format.',
                'message' => 'Format URL tidak valid. Gunakan format: https://domain/{e|d|v}/VIDEO_ID'
            ];
        }

        // Step 2: Auto-detect main domain via redirect
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
                    'video_id'       => $videoId,
                    'original_domain'=> $originalDomain,
                    'main_domain'    => $mainDomain,
                    'download_url'   => $directResult['mp4_url'],
                    'thumbnail'      => $posterUrl,
                    'embed_url'      => $directResult['embed_url'],
                    'title'          => "Video {$videoId}"
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
                'success'    => false,
                'error'      => 'Failed to fetch embed page',
                'message'    => 'Gagal mengambil halaman embed',
                'tried_url'  => $embedUrl
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
        $debug       = isset($_GET['debug']) && $_GET['debug'] == '1';
        $downloader  = new Vid7Downloader($debug);

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
                    'auto_domain' => 'Main domain dideteksi otomatis via redirect dari URL input',
                    '/d/ and /e/' => 'Single video URLs',
                    '/f/'         => 'Folder URL – otomatis proses semua video secara batch'
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
