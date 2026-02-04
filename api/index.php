<?php
/**
 * API Endpoint untuk Download Video dari vid7.online
 * Fixed Version - Updated extraction patterns
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class Vid7Downloader {
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private $debug = false;
    
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
    
    /**
     * Normalize domain to main working domain
     */
    private function normalizeToMainDomain($url) {
        // Replace any domain with vid7.online (main working domain)
        $normalized = preg_replace('/https?:\/\/[^\/]+/', 'https://vid7.online', $url);
        $this->log("Normalized URL: $normalized");
        return $normalized;
    }
    
    /**
     * Extract video ID dan domain dari URL
     */
    private function extractVideoId($url) {
        // Support multiple path patterns: /e/, /d/, /v/, /watch/, dll
        // Exclude /f/ karena itu folder
        if (preg_match('/\/(?:e|d|v|watch)\/([a-z0-9]+)/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Check if URL is folder (not video)
     */
    private function isFolder($url) {
        return preg_match('/\/f\//i', $url);
    }
    
    /**
     * Extract folder ID from URL
     */
    private function extractFolderId($url) {
        if (preg_match('/\/f\/([a-z0-9]+)/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Extract all video URLs from folder page
     */
    private function extractVideoUrlsFromFolder($folderUrl) {
        $this->log("Fetching folder: $folderUrl");
        
        $content = $this->fetchContent($folderUrl);
        if (!$content) {
            return null;
        }
        
        // Extract all video links with pattern: href="/d/VIDEO_ID" or href="/e/VIDEO_ID"
        preg_match_all('/href="\/([dev])\/([a-z0-9]+)"/i', $content, $matches, PREG_SET_ORDER);
        
        if (empty($matches)) {
            return [];
        }
        
        $videos = [];
        $seenIds = [];
        
        foreach ($matches as $match) {
            $path = $match[1]; // d, e, or v
            $videoId = $match[2];
            
            // Avoid duplicates
            if (isset($seenIds[$videoId])) {
                continue;
            }
            $seenIds[$videoId] = true;
            
            $videos[] = [
                'video_id' => $videoId,
                'path' => $path,
                'url' => "/{$path}/{$videoId}"
            ];
        }
        
        $this->log("Found " . count($videos) . " videos in folder");
        return $videos;
    }
    
    /**
     * Extract video title from folder HTML
     */
    private function extractVideoTitle($content, $videoId) {
        // Try to find title near the video link
        $pattern = '/href="\/[dev]\/' . preg_quote($videoId, '/') . '"[^>]*>.*?<strong>(.*?)<\/strong>/is';
        if (preg_match($pattern, $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return "Video {$videoId}";
    }
    
    /**
     * Process folder and get all videos
     */
    public function processFolderBatch($folderUrl) {
        $folderId = $this->extractFolderId($folderUrl);
        if (!$folderId) {
            return [
                'success' => false,
                'error' => 'Invalid folder URL format'
            ];
        }
        
        // Normalize to vid7.online
        $normalizedUrl = $this->normalizeToMainDomain($folderUrl);
        $originalDomain = $this->extractBaseDomain($folderUrl);
        
        $this->log("Processing folder: $folderId");
        
        // Fetch folder content
        $folderContent = $this->fetchContent($normalizedUrl);
        if (!$folderContent) {
            return [
                'success' => false,
                'error' => 'Failed to fetch folder page'
            ];
        }
        
        // Extract video URLs
        $videoList = $this->extractVideoUrlsFromFolder($normalizedUrl);
        if ($videoList === null) {
            return [
                'success' => false,
                'error' => 'Failed to extract videos from folder'
            ];
        }
        
        if (empty($videoList)) {
            return [
                'success' => true,
                'folder_id' => $folderId,
                'total_videos' => 0,
                'videos' => [],
                'message' => 'No videos found in this folder'
            ];
        }
        
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        foreach ($videoList as $video) {
            $videoUrl = "https://vid7.online" . $video['url'];
            $title = $this->extractVideoTitle($folderContent, $video['video_id']);
            
            $this->log("Processing: " . $video['video_id']);
            
            $result = $this->getDownloadInfo($videoUrl);
            
            if ($result['success']) {
                $successCount++;
                $results[] = [
                    'success' => true,
                    'video_id' => $video['video_id'],
                    'title' => $title,
                    'download_url' => $result['data']['download_url'],
                    'thumbnail' => $result['data']['thumbnail'],
                    'embed_url' => $result['data']['embed_url']
                ];
            } else {
                $failCount++;
                $results[] = [
                    'success' => false,
                    'video_id' => $video['video_id'],
                    'title' => $title,
                    'error' => $result['error']
                ];
            }
        }
        
        return [
            'success' => true,
            'type' => 'folder',
            'folder_id' => $folderId,
            'original_domain' => $originalDomain,
            'total_videos' => count($videoList),
            'processed' => count($results),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'videos' => $results
        ];
    }
    
    /**
     * Extract base domain dari URL (for display only)
     */
    private function extractBaseDomain($url) {
        if (preg_match('/https?:\/\/([^\/]+)/', $url, $matches)) {
            return $matches[1];
        }
        return 'vid7.online';
    }
    
    /**
     * Konversi video ID ke iframe ID (hex encoding)
     */
    private function videoIdToIframeId($videoId) {
        return bin2hex($videoId);
    }
    
    /**
     * Fetch content dengan cURL
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
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => $returnHeaders
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->log("Fetching: $url");
        $this->log("HTTP Code: $httpCode");
        
        if ($httpCode !== 200) {
            $this->log("Error: " . $error);
            return null;
        }
        
        return $response;
    }
    
    /**
     * Extract embed URL dari berbagai pattern
     */
    private function extractEmbedUrl($html, $videoId) {
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
        
        // Fallback: construct manually if we have videoId
        $fallbackUrl = "https://vidstrm.mom/embed.php?bucket=temporary&id={$videoId}";
        $this->log("Using fallback URL: $fallbackUrl");
        return $fallbackUrl;
    }
    
    /**
     * Extract MP4 URL dari berbagai pattern
     */
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
    
    /**
     * Extract poster/thumbnail URL
     */
    private function extractPosterUrl($html) {
        if (preg_match('/poster=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * List of known working domains (fallback domains)
     */
    private $fallbackDomains = [
        'vid7.online',
        'vid8.online',
        'vid9.online',
        'vidstream.online'
    ];
    
    /**
     * Try direct embed access (bypass IP endpoint)
     */
    private function tryDirectEmbed($videoId, $processDomain) {
        $embedUrl = "https://{$processDomain}/embed.php?bucket=temporary&id={$videoId}";
        $this->log("Trying direct embed: $embedUrl");
        
        $content = $this->fetchContent($embedUrl);
        if (!$content) {
            return null;
        }
        
        $mp4Url = $this->extractMp4Url($content);
        if ($mp4Url) {
            return [
                'embed_url' => $embedUrl,
                'mp4_url' => $mp4Url,
                'content' => $content
            ];
        }
        
        return null;
    }
    
    /**
     * Main download function
     */
    public function getDownloadInfo($url) {
        // Auto-detect folder and process all videos
        if ($this->isFolder($url)) {
            $this->log("Detected folder URL, processing all videos...");
            return $this->processFolderBatch($url);
        }
        
        // Step 1: Extract video ID
        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            return [
                'success' => false,
                'error' => 'Invalid URL format. Expected: https://DOMAIN/{e|d|v|watch}/VIDEO_ID or /f/FOLDER_ID'
            ];
        }
        
        // Step 2: Extract original domain (for display only)
        $originalDomain = $this->extractBaseDomain($url);
        
        // Step 3: Normalize URL to vid7.online for processing
        $normalizedUrl = $this->normalizeToMainDomain($url);
        $processDomain = 'vid7.online';
        
        $this->log("Video ID: $videoId");
        $this->log("Original Domain: $originalDomain");
        $this->log("Process Domain: $processDomain");
        
        // Try Method 1: Direct embed access (fastest)
        $directResult = $this->tryDirectEmbed($videoId, $processDomain);
        if ($directResult && $directResult['mp4_url']) {
            $posterUrl = $this->extractPosterUrl($directResult['content']);
            
            return [
                'success' => true,
                'method' => 'direct_embed',
                'data' => [
                    'video_id' => $videoId,
                    'original_domain' => $originalDomain,
                    'processed_via' => $processDomain,
                    'download_url' => $directResult['mp4_url'],
                    'thumbnail' => $posterUrl,
                    'embed_url' => $directResult['embed_url'],
                    'title' => "Video {$videoId}"
                ]
            ];
        }
        
        // Method 2: Via IP endpoint (original method)
        $iframeId = $this->videoIdToIframeId($videoId);
        $this->log("Iframe ID: $iframeId");
        
        $ipUrl = "https://{$processDomain}/ip129jk?id={$iframeId}";
        $ipContent = $this->fetchContent($ipUrl, $normalizedUrl);
        
        if (!$ipContent) {
            return [
                'success' => false,
                'error' => 'Failed to fetch IP endpoint',
                'debug' => $this->debug ? [
                    'video_id' => $videoId,
                    'original_domain' => $originalDomain,
                    'process_domain' => $processDomain,
                    'iframe_id' => $iframeId,
                    'ip_url' => $ipUrl
                ] : null
            ];
        }
        
        // Extract embed URL
        $embedUrl = $this->extractEmbedUrl($ipContent, $videoId);
        if (!$embedUrl) {
            // Fallback dengan process domain
            $embedUrl = "https://{$processDomain}/embed.php?bucket=temporary&id={$videoId}";
        }
        
        // Fetch embed page
        $embedContent = $this->fetchContent($embedUrl, $ipUrl);
        if (!$embedContent) {
            return [
                'success' => false,
                'error' => 'Failed to fetch embed page',
                'tried_url' => $embedUrl
            ];
        }
        
        // Extract MP4 URL
        $mp4Url = $this->extractMp4Url($embedContent);
        if (!$mp4Url) {
            return [
                'success' => false,
                'error' => 'Failed to extract video URL',
                'debug' => $this->debug ? [
                    'embed_content_preview' => substr($embedContent, 0, 500)
                ] : null
            ];
        }
        
        // Extract poster
        $posterUrl = $this->extractPosterUrl($embedContent);
        
        return [
            'success' => true,
            'method' => 'ip_endpoint',
            'data' => [
                'video_id' => $videoId,
                'original_domain' => $originalDomain,
                'processed_via' => $processDomain,
                'iframe_id' => $iframeId,
                'download_url' => $mp4Url,
                'thumbnail' => $posterUrl,
                'embed_url' => $embedUrl,
                'title' => "Video {$videoId}"
            ]
        ];
    }
    
    /**
     * Proxy download dengan streaming
     */
    public function proxyDownload($videoUrl, $filename = null, $refererDomain = null) {
        if (!$filename) {
            $filename = 'video_' . time() . '.mp4';
        }
        
        // Auto detect referer dari video URL jika tidak disediakan
        if (!$refererDomain) {
            if (preg_match('/https?:\/\/([^\/]+)/', $videoUrl, $matches)) {
                $refererDomain = $matches[1];
            } else {
                $refererDomain = 'vid7.online';
            }
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $videoUrl,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . $this->userAgent,
                'Referer: https://' . $refererDomain . '/'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_WRITEFUNCTION => function($ch, $data) {
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

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Enable debug mode if requested
    $debug = isset($_GET['debug']) && $_GET['debug'] == '1';
    $downloader = new Vid7Downloader($debug);
    
    // Mode: get info
    if (isset($_GET['url']) && !isset($_GET['download'])) {
        $result = $downloader->getDownloadInfo($_GET['url']);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    // Mode: proxy download
    else if (isset($_GET['video_url']) && isset($_GET['download'])) {
        $videoUrl = $_GET['video_url'];
        $filename = $_GET['filename'] ?? null;
        $refererDomain = $_GET['referer'] ?? null;
        $downloader->proxyDownload($videoUrl, $filename, $refererDomain);
    }
    else {
        echo json_encode([
            'success' => false,
            'error' => 'Missing parameters',
            'usage' => [
                'get_info' => '?url=https://vid7.online/e/VIDEO_ID or /d/VIDEO_ID (single video)',
                'get_folder' => '?url=https://vid7.online/f/FOLDER_ID (batch all videos)',
                'get_info_debug' => '?url=https://vid7.online/e/VIDEO_ID&debug=1',
                'download' => '?video_url=VIDEO_URL&download=1&filename=video.mp4'
            ],
            'notes' => [
                '/d/ and /e/' => 'Single video URLs',
                '/f/' => 'Folder URL - automatically processes all videos in batch'
            ]
        ], JSON_PRETTY_PRINT);
    }
}
?>
