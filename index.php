<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vidoy Downloader - Auto Detect Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(168, 85, 247, 0.4);
        }
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #a855f7;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .progress-bar {
            transition: width 0.3s ease;
        }
        .detected-url {
            background: rgba(168, 85, 247, 0.1);
            border-left: 3px solid #a855f7;
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
        }
        .detected-url.type-video {
            border-left-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        .url-list-box {
            background: rgba(16, 185, 129, 0.07);
            border: 1.5px solid rgba(16, 185, 129, 0.35);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            margin-top: 1rem;
        }
        .url-list-box textarea {
            width: 100%;
            background: rgba(0,0,0,0.25);
            color: #a7f3d0;
            font-family: monospace;
            font-size: 0.78rem;
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 0.5rem;
            padding: 0.75rem;
            resize: vertical;
            outline: none;
            min-height: 90px;
        }
        .url-list-box textarea:focus {
            border-color: rgba(16,185,129,0.6);
        }
        .copy-btn {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
            font-weight: 700;
            padding: 0.45rem 1.1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            font-size: 0.82rem;
            transition: opacity 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .copy-btn:hover { opacity: 0.85; }
        .copy-btn.copied { background: linear-gradient(to right, #6366f1, #4f46e5); }
    </style>
</head>
<body>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-6">
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="flex items-center justify-center gap-4 mb-4">
                <svg class="w-16 h-16 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h1 class="text-5xl font-bold text-white">Vidoy Downloader</h1>
            </div>
            <p class="text-gray-300 text-lg">Download semua video dari Vidoy dengan mudah</p>
            <p class="text-purple-400 text-sm font-semibold mt-2">🤖 Auto Detect Edition - Smart URL Extraction</p>
        </div>

        <!-- Search Form -->
        <div class="mb-10 max-w-5xl mx-auto">
            <form id="searchForm" class="space-y-4">
                <div class="relative">
                    <textarea
                        id="urlInput"
                        placeholder="Paste teks atau daftar URL di sini... Auto detect akan menemukan semua URL!&#10;&#10;Support SEMUA domain & subdomain dengan pattern /f/ atau /d/&#10;&#10;Contoh:&#10;📁 Folder biasa&#10;https://upl.ad/f/1orrot80hs0&#10;&#10;🎬 Video langsung (subdomain)&#10;https://cdn2.videy.coach/d/9pgjmw4ivhhj&#10;&#10;📁 Folder subdomain&#10;https://cdn2.videy.coach/f/abc123&#10;&#10;Paste teks apapun, sistem akan otomatis menemukan semua URL!"
                        class="w-full px-6 py-5 pr-6 rounded-2xl bg-white/10 backdrop-blur-lg border-2 border-purple-500/30 text-white text-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-none"
                        rows="8"
                        required
                    ></textarea>
                </div>
                
                <!-- Detected URLs Preview -->
                <div id="detectedUrlsBox" class="hidden bg-white/5 backdrop-blur-lg rounded-2xl border-2 border-green-500/30 p-6">
                    <div class="flex items-center gap-4 mb-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                            </svg>
                            <h3 class="text-green-400 font-bold">
                                <span id="detectedCount">0</span> URL Terdeteksi
                            </h3>
                        </div>
                        <div class="flex gap-3 text-xs">
                            <span class="bg-purple-500/30 text-purple-300 px-2 py-1 rounded-full">
                                📁 Folder: <span id="detectedFolderCount">0</span>
                            </span>
                            <span class="bg-blue-500/30 text-blue-300 px-2 py-1 rounded-full">
                                🎬 Video: <span id="detectedVideoCount">0</span>
                            </span>
                        </div>
                    </div>
                    <div id="detectedUrlsList" class="space-y-1 max-h-48 overflow-y-auto text-sm">
                    </div>
                </div>
                
                <button
                    type="submit"
                    id="searchBtn"
                    class="w-full px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold transition-all flex items-center justify-center gap-2 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <svg id="searchIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div id="loadingSpinner" class="spinner hidden"></div>
                    <span id="searchText">Auto Detect & Process</span>
                </button>
            </form>
            <p class="text-gray-400 text-sm mt-3 text-center">
                🤖 <strong class="text-purple-400">Smart Auto Detect</strong> - Support semua domain, subdomain, /f/ (folder) & /d/ (video)
            </p>
            <div class="text-center mt-2">
                <button id="testApiBtn" class="text-gray-500 hover:text-purple-400 text-xs underline">
                    Test API Connection
                </button>
            </div>
        </div>

        <!-- Progress Info -->
        <div id="progressInfo" class="max-w-5xl mx-auto mb-8 hidden">
            <div class="bg-white/5 backdrop-blur-lg rounded-2xl border-2 border-purple-500/30 p-6">
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-300 mb-2">
                        <span>Processing URLs...</span>
                        <span id="progressText">0 / 0</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden">
                        <div id="progressBar" class="progress-bar bg-gradient-to-r from-purple-500 to-pink-500 h-3 rounded-full" style="width: 0%"></div>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-gray-400 text-sm mb-1">Total URLs</div>
                        <div class="text-purple-400 text-2xl font-bold" id="totalFolders">0</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-sm mb-1">Total Videos</div>
                        <div class="text-blue-400 text-2xl font-bold" id="totalVideos">0</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-sm mb-1">Success</div>
                        <div class="text-green-400 text-2xl font-bold" id="successCount">0</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-sm mb-1">Failed</div>
                        <div class="text-red-400 text-2xl font-bold" id="failedCount">0</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="max-w-5xl mx-auto mb-8 hidden">
            <div class="bg-red-500/20 border-2 border-red-500/50 rounded-2xl px-6 py-4 text-red-200 backdrop-blur-lg">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <line x1="12" y1="8" x2="12" y2="12" stroke-width="2"/>
                        <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"/>
                    </svg>
                    <span id="errorText"></span>
                </div>
            </div>
        </div>

        <!-- Videos Container -->
        <div id="videosContainer" class="hidden space-y-8">
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-20">
            <svg class="w-32 h-32 text-gray-600 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h3 class="text-gray-300 text-2xl font-bold mb-3">Siap untuk download?</h3>
            <p class="text-gray-400 text-lg mb-2">Paste teks atau URL di atas untuk memulai</p>
            <p class="text-gray-500 text-sm">Support semua domain, subdomain, /f/ (folder) & /d/ (video langsung)</p>
            
            <div class="mt-12 max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">🤖</div>
                    <h4 class="text-white font-bold mb-2">Auto Detect</h4>
                    <p class="text-gray-400 text-sm">Otomatis ekstrak semua URL dari teks apapun</p>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">📦</div>
                    <h4 class="text-white font-bold mb-2">Subdomain Support</h4>
                    <p class="text-gray-400 text-sm">cdn2.videy.coach, upl.ad, vid7.online & semua domain lainnya</p>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">⚡</div>
                    <h4 class="text-white font-bold mb-2">/f/ & /d/ Support</h4>
                    <p class="text-gray-400 text-sm">/f/ untuk folder, /d/ untuk video langsung</p>
                </div>
            </div>

            <div class="mt-8 max-w-2xl mx-auto bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20 text-left">
                <h4 class="text-white font-bold mb-4 text-center">📋 Format URL yang Didukung</h4>
                <div class="space-y-2 text-sm font-mono">
                    <div class="flex items-center gap-3">
                        <span class="bg-purple-500/30 text-purple-300 px-2 py-0.5 rounded text-xs flex-shrink-0">📁 Folder</span>
                        <span class="text-gray-300">https://upl.ad/f/abc123</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-purple-500/30 text-purple-300 px-2 py-0.5 rounded text-xs flex-shrink-0">📁 Folder</span>
                        <span class="text-gray-300">https://vid7.online/f/xyz789</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-blue-500/30 text-blue-300 px-2 py-0.5 rounded text-xs flex-shrink-0">🎬 Video</span>
                        <span class="text-gray-300">https://cdn2.videy.coach/d/9pgjmw4ivhhj</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-blue-500/30 text-blue-300 px-2 py-0.5 rounded text-xs flex-shrink-0">🎬 Video</span>
                        <span class="text-gray-300">https://sub.domain.com/d/videoid</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-purple-500/30 text-purple-300 px-2 py-0.5 rounded text-xs flex-shrink-0">📁 Folder</span>
                        <span class="text-gray-300">https://sub.domain.com/f/folderid</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-16 pb-8">
            <p class="text-gray-500 text-sm">Made with ❤️ using Vidoy API</p>
        </div>

    </div>
</div>

<!-- Scroll to Top Button -->
<button id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})"
    class="fixed bottom-8 right-8 z-50 hidden w-12 h-12 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-full shadow-lg shadow-purple-500/40 flex items-center justify-center transition-all duration-300 hover:scale-110"
    title="Kembali ke atas">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path d="M5 15l7-7 7 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>

<script>
	// Scroll to Top Button
const scrollTopBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
    if (window.scrollY > 300) {
        scrollTopBtn.classList.remove('hidden');
        scrollTopBtn.classList.add('flex');
    } else {
        scrollTopBtn.classList.add('hidden');
        scrollTopBtn.classList.remove('flex');
    }
});
</script>


<script>
function detectUrls(text) {
    const urlPattern = /https?:\/\/(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}\/[fd]\/[a-zA-Z0-9]+/gi;
    const matches = text.match(urlPattern);
    if (!matches) return [];
    return [...new Set(matches)];
}

function getUrlType(url) {
    if (/\/f\//.test(url)) return 'folder';
    if (/\/d\//.test(url)) return 'video';
    return 'unknown';
}

document.getElementById('urlInput').addEventListener('input', function() {
    const text = this.value;
    const urls = detectUrls(text);
    
    const detectedBox = document.getElementById('detectedUrlsBox');
    const detectedCount = document.getElementById('detectedCount');
    const detectedFolderCount = document.getElementById('detectedFolderCount');
    const detectedVideoCount = document.getElementById('detectedVideoCount');
    const detectedList = document.getElementById('detectedUrlsList');
    
    if (urls.length > 0) {
        detectedBox.classList.remove('hidden');
        detectedCount.textContent = urls.length;
        const folders = urls.filter(u => getUrlType(u) === 'folder').length;
        const videos  = urls.filter(u => getUrlType(u) === 'video').length;
        detectedFolderCount.textContent = folders;
        detectedVideoCount.textContent  = videos;
        detectedList.innerHTML = urls.map((url, index) => {
            const type    = getUrlType(url);
            const typeClass  = type === 'video' ? 'type-video' : '';
            const typeLabel  = type === 'folder' ? '📁 Folder' : '🎬 Video';
            const labelClass = type === 'folder' ? 'text-purple-400' : 'text-blue-400';
            return `
                <div class="detected-url ${typeClass} text-gray-300 font-mono text-xs flex items-center gap-2">
                    <span class="${labelClass} font-bold flex-shrink-0">#${index + 1} ${typeLabel}</span>
                    <span class="truncate">${escapeHtml(url)}</span>
                </div>
            `;
        }).join('');
    } else {
        detectedBox.classList.add('hidden');
    }
});

document.getElementById('testApiBtn').addEventListener('click', async () => {
    try {
        const response = await fetch('/api/index.php?test=1');
        const data = await response.json();
        if (data.success) {
            alert('✅ API Connection Success!\n\nTimestamp: ' + data.timestamp + '\nPHP Version: ' + data.php_version + '\ncURL Available: ' + (data.curl_available ? 'Yes' : 'No'));
        } else {
            alert('❌ API Test Failed');
        }
    } catch (error) {
        alert('❌ Cannot connect to API\n\nError: ' + error.message + '\n\nPastikan file api/index.php ada di folder yang sama dengan index.html');
    }
});

let globalStats = {
    totalFolders: 0,
    processedFolders: 0,
    totalVideos: 0,
    successVideos: 0,
    failedVideos: 0
};

document.getElementById('searchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const urlInput = document.getElementById('urlInput');
    const input = urlInput.value.trim();
    
    if (!input) {
        showError('Silakan masukkan teks atau URL');
        return;
    }
    
    const urls = detectUrls(input);
    
    if (urls.length === 0) {
        showError('❌ Tidak ada URL yang terdeteksi!\n\nPastikan URL memiliki format:\n- https://domain.com/f/xxxxx        (folder)\n- https://domain.com/d/xxxxx        (video)\n- https://sub.domain.com/f/xxxxx    (folder, subdomain)\n- https://sub.domain.com/d/xxxxx    (video, subdomain)\n\nContoh: https://cdn2.videy.coach/d/9pgjmw4ivhhj');
        return;
    }
    
    globalStats = {
        totalFolders: urls.length,
        processedFolders: 0,
        totalVideos: 0,
        successVideos: 0,
        failedVideos: 0
    };
    
    setLoading(true);
    hideAll();
    showProgress();
    updateProgress();
    
    document.getElementById('videosContainer').innerHTML = '';
    
    for (let i = 0; i < urls.length; i++) {
        await processUrl(urls[i], i + 1);
        globalStats.processedFolders++;
        updateProgress();
    }
    
    setLoading(false);
    
    if (globalStats.totalVideos > 0) {
        document.getElementById('videosContainer').classList.remove('hidden');
    } else {
        showError('Tidak ada video yang berhasil diambil dari semua URL');
    }
});

async function processUrl(url, folderNumber) {
    try {
        const apiUrl = `/api/index.php?url=${encodeURIComponent(url)}`;
        const response = await fetch(apiUrl);
        
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Server tidak mengembalikan JSON');
        }
        
        const data = await response.json();
        
        if (data.success) {
            if (data.type === 'folder' && data.videos && data.videos.length > 0) {
                displayFolderVideos(data, url, folderNumber);
                globalStats.totalVideos += data.videos.length;
                globalStats.successVideos += data.success_count || data.videos.filter(v => v.success).length;
                globalStats.failedVideos += (data.videos.length - (data.success_count || data.videos.filter(v => v.success).length));
            } else if (data.data && data.data.download_url) {
                const videoData = convertSingleToFolder(data);
                displayFolderVideos(videoData, url, folderNumber);
                globalStats.totalVideos += 1;
                globalStats.successVideos += 1;
            } else {
                displayFolderError(url, folderNumber, 'Tidak ada video ditemukan');
            }
        } else {
            displayFolderError(url, folderNumber, data.message || data.error || 'Gagal memproses URL');
        }
    } catch (error) {
        displayFolderError(url, folderNumber, error.message);
    }
    
    updateProgress();
}

function convertSingleToFolder(data) {
    return {
        success: true,
        type: 'single',
        total_videos: 1,
        processed: 1,
        success_count: 1,
        videos: [{
            success: true,
            video_id: data.data.video_id,
            title: data.data.title,
            download_url: data.data.download_url,
            thumbnail: data.data.thumbnail,
            embed_url: data.data.embed_url
        }]
    };
}

function displayFolderVideos(data, sourceUrl, folderNumber) {
    const container = document.getElementById('videosContainer');
    
    const folderSection = document.createElement('div');
    folderSection.className = 'space-y-4';
    
    const videos = data.videos || [];
    const successVideos = videos.filter(v => v.success);
    const successCount = successVideos.length;
    const urlType = getUrlType(sourceUrl);
    const typeIcon  = urlType === 'video' ? '🎬' : '📁';
    const typeLabel = urlType === 'video' ? 'Video' : 'Folder';

    // Collect all tonton URLs for successful videos
    const tontonUrls = successVideos
        .map(v => v.download_url)
        .filter(Boolean);

    // Build URL list box HTML
    let urlListBoxHtml = '';
    if (tontonUrls.length > 0) {
        const urlListId = `urllist-${folderNumber}`;
        const copyBtnId = `copybtn-${folderNumber}`;
        urlListBoxHtml = `
            <div class="url-list-box">
                <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="text-emerald-400 font-bold text-sm">
                            Semua URL Tonton ${typeIcon} ${typeLabel} #${folderNumber}
                            <span class="text-emerald-600 font-normal">(${tontonUrls.length} video)</span>
                        </span>
                    </div>
                    <button class="copy-btn" id="${copyBtnId}" onclick="copyUrlList('${urlListId}', '${copyBtnId}')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Copy Semua URL
                    </button>
                </div>
                <textarea id="${urlListId}" readonly>${escapeHtml(tontonUrls.join('\n'))}</textarea>
            </div>
        `;
    }
    
    folderSection.innerHTML = `
        <div class="bg-white/5 backdrop-blur-lg rounded-2xl border-2 border-purple-500/30 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-1">
                        ${typeIcon} ${typeLabel} #${folderNumber}
                    </h2>
                    <p class="text-gray-400 text-sm break-all">${escapeHtml(sourceUrl)}</p>
                </div>
                <div class="flex gap-4 text-sm">
                    <div class="text-center">
                        <div class="text-gray-400 mb-1">Videos</div>
                        <div class="text-purple-400 font-bold text-xl">${videos.length}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-gray-400 mb-1">Success</div>
                        <div class="text-green-400 font-bold text-xl">${successCount}</div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="folder-${folderNumber}-grid">
            </div>

            ${urlListBoxHtml}
        </div>
    `;
    
    container.appendChild(folderSection);
    
    const grid = folderSection.querySelector(`#folder-${folderNumber}-grid`);
    videos.forEach((video, index) => {
        const card = createVideoCard(video, index);
        grid.appendChild(card);
    });
}

function copyUrlList(textareaId, btnId) {
    const textarea = document.getElementById(textareaId);
    const btn = document.getElementById(btnId);
    if (!textarea) return;
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    try {
        navigator.clipboard.writeText(textarea.value).then(() => {
            btn.classList.add('copied');
            btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Tersalin!`;
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Copy Semua URL`;
            }, 2000);
        });
    } catch {
        document.execCommand('copy');
        btn.textContent = '✅ Tersalin!';
        setTimeout(() => { btn.textContent = 'Copy Semua URL'; }, 2000);
    }
}

function displayFolderError(sourceUrl, folderNumber, errorMessage) {
    const container = document.getElementById('videosContainer');
    const urlType  = getUrlType(sourceUrl);
    const typeIcon = urlType === 'video' ? '🎬' : '📁';
    const typeLabel = urlType === 'video' ? 'Video' : 'Folder';
    
    const folderSection = document.createElement('div');
    folderSection.className = 'space-y-4';
    
    folderSection.innerHTML = `
        <div class="bg-red-500/10 backdrop-blur-lg rounded-2xl border-2 border-red-500/30 p-6">
            <div class="flex items-start gap-4">
                <svg class="w-8 h-8 text-red-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke-width="2"/>
                    <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"/>
                </svg>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-red-300 mb-2">
                        ❌ ${typeIcon} ${typeLabel} #${folderNumber} - Error
                    </h2>
                    <p class="text-gray-400 text-sm break-all mb-2">${escapeHtml(sourceUrl)}</p>
                    <p class="text-red-200">${escapeHtml(errorMessage)}</p>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(folderSection);
    globalStats.failedVideos++;
}

function updateProgress() {
    document.getElementById('totalFolders').textContent = globalStats.totalFolders;
    document.getElementById('totalVideos').textContent  = globalStats.totalVideos;
    document.getElementById('successCount').textContent = globalStats.successVideos;
    document.getElementById('failedCount').textContent  = globalStats.failedVideos;
    
    const progressPercent = globalStats.totalFolders > 0 
        ? (globalStats.processedFolders / globalStats.totalFolders) * 100 
        : 0;
    
    document.getElementById('progressBar').style.width = progressPercent + '%';
    document.getElementById('progressText').textContent = 
        `${globalStats.processedFolders} / ${globalStats.totalFolders}`;
}

function showProgress() {
    document.getElementById('progressInfo').classList.remove('hidden');
}

function setLoading(loading) {
    const searchBtn     = document.getElementById('searchBtn');
    const searchIcon    = document.getElementById('searchIcon');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const searchText    = document.getElementById('searchText');
    
    if (loading) {
        searchBtn.disabled = true;
        searchIcon.classList.add('hidden');
        loadingSpinner.classList.remove('hidden');
        searchText.textContent = 'Processing...';
    } else {
        searchBtn.disabled = false;
        searchIcon.classList.remove('hidden');
        loadingSpinner.classList.add('hidden');
        searchText.textContent = 'Auto Detect & Process';
    }
}

function hideAll() {
    document.getElementById('errorMessage').classList.add('hidden');
    document.getElementById('videosContainer').classList.add('hidden');
    document.getElementById('emptyState').classList.add('hidden');
}

function showError(message) {
    hideAll();
    document.getElementById('errorText').textContent = message;
    document.getElementById('errorMessage').classList.remove('hidden');
}

function createVideoCard(video, index) {
    const isError  = !video.success;
    const name     = video.title || 'Unknown';
    const thumbnail = video.thumbnail || '';
    const videoUrl = video.download_url || '#';
    
    const card = document.createElement('div');
    card.className = `card-hover bg-white/5 backdrop-blur-lg rounded-2xl overflow-hidden border-2 ${isError ? 'border-red-500/50' : 'border-purple-500/20'}`;
    
    const thumbnailHtml = !isError && thumbnail ? 
        `<img src="${escapeHtml(thumbnail)}" alt="${escapeHtml(name)}" class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-500" onerror="this.parentElement.innerHTML='<div class=\\'w-full h-48 bg-gray-700 flex items-center justify-center\\'><svg class=\\'w-16 h-16 text-gray-500\\' fill=\\'none\\' stroke=\\'currentColor\\' viewBox=\\'0 0 24 24\\'><path d=\\'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z\\' stroke-width=\\'2\\'/></svg></div>';">` :
        `<div class="w-full h-48 bg-gray-700 flex items-center justify-center"><svg class="w-16 h-16 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" stroke-width="2"/></svg></div>`;
    
    const errorContent = isError ? 
        `<div class="bg-red-500/20 border border-red-500/50 rounded-lg px-3 py-2 text-red-300 text-sm">❌ ${escapeHtml(video.error || 'Unknown error')}</div>` : '';
    
    const actionButtons = !isError ? `
        <a href="${escapeHtml(videoUrl)}" target="_blank" rel="noopener noreferrer" class="block w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white py-3 rounded-xl font-bold transition-all text-center flex items-center justify-center gap-2 shadow-lg hover:shadow-purple-500/50">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor"/></svg>
            <span>Tonton</span>
        </a>` : '';
    
    card.innerHTML = `
        <a href="${isError ? '#' : escapeHtml(videoUrl)}" target="_blank" rel="noopener noreferrer" class="block relative group overflow-hidden ${isError ? 'pointer-events-none' : ''}">
            ${thumbnailHtml}
            ${!isError ? '<div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center"><svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor"/></svg></div>' : ''}
        </a>
        <div class="p-5 space-y-4">
            <h3 class="text-white font-bold text-base line-clamp-2 min-h-[3rem]" title="${escapeHtml(name)}">${escapeHtml(name)}</h3>
            ${errorContent}
            ${!isError ? `
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="bg-purple-500/30 text-purple-200 px-3 py-1.5 rounded-full border border-purple-400/30">🎬 Video</span>
                    ${video.video_id ? `<span class="bg-blue-500/30 text-blue-200 px-3 py-1.5 rounded-full border border-blue-400/30">🆔 ${escapeHtml(video.video_id)}</span>` : ''}
                </div>
                ${actionButtons}
            ` : ''}
        </div>
    `;
    
    return card;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>
