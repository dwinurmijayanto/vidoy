<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vidoy Downloader Pro - Universal Edition</title>
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
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
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
                <h1 class="text-5xl font-bold text-white">Vidoy Downloader Pro</h1>
            </div>
            <p class="text-gray-300 text-lg">Download semua video dari Vidoy dengan mudah</p>
            <p class="text-purple-400 text-sm font-semibold mt-2">🌐 Universal Edition - Support ALL Domains</p>
        </div>

        <!-- Search Form -->
        <div class="mb-10 max-w-5xl mx-auto">
            <form id="searchForm" class="space-y-4">
                <div class="relative">
                    <textarea
                        id="urlInput"
                        placeholder="Paste teks atau daftar URL di sini... Auto detect akan menemukan semua URL!&#10;&#10;✨ Support SEMUA domain dengan pattern /f/ atau /d/&#10;&#10;Contoh:&#10;📁 Koleksi A&#10;https://upl.ad/f/1orrot80hs0&#10;&#10;📁 Koleksi B&#10;https://vid7.online/f/lt62gxsxkin&#10;&#10;📁 Koleksi C&#10;https://domain-apapun.com/f/abc123"
                        class="w-full px-6 py-5 pr-6 rounded-2xl bg-white/10 backdrop-blur-lg border-2 border-purple-500/30 text-white text-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-none"
                        rows="8"
                        required
                    ></textarea>
                </div>
                
                <!-- Detected URLs Preview -->
                <div id="detectedUrlsBox" class="hidden bg-white/5 backdrop-blur-lg rounded-2xl border-2 border-green-500/30 p-6">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                            </svg>
                            <h3 class="text-green-400 font-bold">
                                <span id="detectedCount">0</span> URL Terdeteksi
                            </h3>
                        </div>
                        <button type="button" id="clearUrlsBtn" class="text-red-400 hover:text-red-300 text-sm underline">
                            Clear All
                        </button>
                    </div>
                    <div id="detectedUrlsList" class="space-y-1 max-h-48 overflow-y-auto text-sm">
                        <!-- Detected URLs will be listed here -->
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
                🌐 <strong class="text-purple-400">Universal Auto Detect</strong> - Support semua domain dengan pattern /f/ atau /d/
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
                        <span>Processing folders...</span>
                        <span id="progressText">0 / 0</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden">
                        <div id="progressBar" class="progress-bar bg-gradient-to-r from-purple-500 to-pink-500 h-3 rounded-full" style="width: 0%"></div>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-gray-400 text-sm mb-1">Total Folders</div>
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

        <!-- Action Buttons -->
        <div id="actionButtons" class="max-w-5xl mx-auto mb-8 hidden">
            <div class="bg-white/5 backdrop-blur-lg rounded-2xl border-2 border-purple-500/30 p-6">
                <div class="flex flex-wrap gap-3 justify-center">
                    <button id="copyAllLinksBtn" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" stroke-width="2"/>
                        </svg>
                        Copy All Links
                    </button>
                    <button id="exportTxtBtn" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="2"/>
                        </svg>
                        Export TXT
                    </button>
                    <button id="exportM3uBtn" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" stroke-width="2"/>
                        </svg>
                        Export M3U
                    </button>
                    <div class="flex gap-2 items-center ml-auto">
                        <label class="text-gray-300 text-sm font-bold">Grid:</label>
                        <button data-grid="2" class="grid-btn px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg font-bold transition-all">2</button>
                        <button data-grid="3" class="grid-btn px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg font-bold transition-all">3</button>
                        <button data-grid="4" class="grid-btn px-4 py-2 bg-white/20 text-white rounded-lg font-bold transition-all">4</button>
                        <button data-grid="6" class="grid-btn px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg font-bold transition-all">6</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Videos Container -->
        <div id="videosContainer" class="hidden space-y-8">
            <!-- Folders will be inserted here dynamically -->
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-20">
            <svg class="w-32 h-32 text-gray-600 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h3 class="text-gray-300 text-2xl font-bold mb-3">Siap untuk download?</h3>
            <p class="text-gray-400 text-lg mb-2">Paste teks atau URL di atas untuk memulai</p>
            <p class="text-gray-500 text-sm">Auto detect akan menemukan semua URL secara otomatis!</p>
            
            <div class="mt-12 max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">🌐</div>
                    <h4 class="text-white font-bold mb-2">Universal Support</h4>
                    <p class="text-gray-400 text-sm">Support semua domain dengan pattern /f/ atau /d/</p>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">🤖</div>
                    <h4 class="text-white font-bold mb-2">Smart Auto Detect</h4>
                    <p class="text-gray-400 text-sm">Otomatis ekstrak semua URL dari teks</p>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">⚡</div>
                    <h4 class="text-white font-bold mb-2">Bulk Process</h4>
                    <p class="text-gray-400 text-sm">Process multiple folders sekaligus</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-16 pb-8">
            <p class="text-gray-500 text-sm">Made with ❤️ using Vidoy API</p>
        </div>

    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer"></div>

<script>
// Global variables
let currentGridSize = 4;
let allVideos = [];

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast px-6 py-4 rounded-xl shadow-2xl ${
        type === 'success' ? 'bg-green-600' : 
        type === 'error' ? 'bg-red-600' : 
        'bg-blue-600'
    } text-white font-bold`;
    toast.textContent = message;
    
    document.getElementById('toastContainer').appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Auto detect URLs from input text - UNIVERSAL PATTERN
function detectUrls(text) {
    // Regex pattern universal - deteksi SEMUA domain dengan pattern /f/ atau /d/
    // Matches: https://apapun.domain/f/xxx atau https://apapun.domain/d/xxx
    // Pattern: http(s)://[domain apapun]/[f atau d]/[alphanumeric]
    const urlPattern = /https?:\/\/[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\/[fd]\/[a-zA-Z0-9]+/gi;
    
    const matches = text.match(urlPattern);
    
    if (!matches) {
        return [];
    }
    
    // Remove duplicates
    return [...new Set(matches)];
}

// Real-time URL detection while typing
document.getElementById('urlInput').addEventListener('input', function() {
    const text = this.value;
    const urls = detectUrls(text);
    
    const detectedBox = document.getElementById('detectedUrlsBox');
    const detectedCount = document.getElementById('detectedCount');
    const detectedList = document.getElementById('detectedUrlsList');
    
    if (urls.length > 0) {
        detectedBox.classList.remove('hidden');
        detectedCount.textContent = urls.length;
        
        detectedList.innerHTML = urls.map((url, index) => `
            <div class="detected-url text-gray-300 font-mono text-xs">
                <span class="text-purple-400 font-bold">#${index + 1}</span> ${escapeHtml(url)}
            </div>
        `).join('');
    } else {
        detectedBox.classList.add('hidden');
    }
});

// Clear URLs button
document.getElementById('clearUrlsBtn').addEventListener('click', () => {
    document.getElementById('urlInput').value = '';
    document.getElementById('detectedUrlsBox').classList.add('hidden');
});

// Test API Connection
document.getElementById('testApiBtn').addEventListener('click', async () => {
    try {
        const response = await fetch('/api/index.php?test=1');
        const data = await response.json();
        
        if (data.success) {
            showToast('✅ API Connected Successfully!', 'success');
        } else {
            showToast('❌ API Test Failed', 'error');
        }
    } catch (error) {
        showToast('❌ Cannot connect to API', 'error');
    }
});

// Copy All Links
document.getElementById('copyAllLinksBtn').addEventListener('click', () => {
    const links = allVideos
        .filter(v => v.success)
        .map(v => v.download_url)
        .join('\n');
    
    navigator.clipboard.writeText(links).then(() => {
        showToast(`✅ Copied ${allVideos.filter(v => v.success).length} links to clipboard!`, 'success');
    });
});

// Export TXT
document.getElementById('exportTxtBtn').addEventListener('click', () => {
    const content = allVideos
        .filter(v => v.success)
        .map((v, i) => `${i + 1}. ${v.title}\n${v.download_url}`)
        .join('\n\n');
    
    downloadFile('vidoy-links.txt', content);
    showToast('✅ Exported to TXT file!', 'success');
});

// Export M3U
document.getElementById('exportM3uBtn').addEventListener('click', () => {
    const content = '#EXTM3U\n' + allVideos
        .filter(v => v.success)
        .map(v => `#EXTINF:-1,${v.title}\n${v.download_url}`)
        .join('\n');
    
    downloadFile('vidoy-playlist.m3u', content);
    showToast('✅ Exported to M3U playlist!', 'success');
});

// Download file helper
function downloadFile(filename, content) {
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// Grid size buttons
document.querySelectorAll('.grid-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        currentGridSize = parseInt(this.dataset.grid);
        
        // Update button states
        document.querySelectorAll('.grid-btn').forEach(b => {
            b.classList.remove('bg-white/20');
            b.classList.add('bg-white/10');
        });
        this.classList.remove('bg-white/10');
        this.classList.add('bg-white/20');
        
        // Update all grids
        updateGridSize();
    });
});

function updateGridSize() {
    const gridClass = {
        2: 'lg:grid-cols-2',
        3: 'lg:grid-cols-3',
        4: 'lg:grid-cols-4',
        6: 'lg:grid-cols-6'
    };
    
    document.querySelectorAll('[id^="folder-"][id$="-grid"]').forEach(grid => {
        // Remove all grid classes
        grid.classList.remove('lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4', 'lg:grid-cols-6');
        // Add new grid class
        grid.classList.add(gridClass[currentGridSize]);
    });
}

// Global stats tracking
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
    
    // Auto detect URLs from input text
    const urls = detectUrls(input);
    
    if (urls.length === 0) {
        showError('❌ Tidak ada URL yang terdeteksi!\n\nPastikan URL memiliki format:\n- https://domain.com/f/xxxxx (folder)\n- https://domain.com/d/xxxxx (single video)\n\nContoh: https://upl.ad/f/abc123 atau https://vid7.online/f/xyz789');
        return;
    }
    
    console.log('🤖 Auto Detected URLs:', urls);
    showToast(`🔍 Found ${urls.length} URLs, processing...`, 'info');
    
    // Reset global stats
    globalStats = {
        totalFolders: urls.length,
        processedFolders: 0,
        totalVideos: 0,
        successVideos: 0,
        failedVideos: 0
    };
    
    allVideos = [];
    
    // Show loading
    setLoading(true);
    hideAll();
    showProgress();
    updateProgress();
    
    // Clear previous results
    document.getElementById('videosContainer').innerHTML = '';
    
    // Process each URL
    for (let i = 0; i < urls.length; i++) {
        const url = urls[i];
        await processUrl(url, i + 1);
        
        // Update progress
        globalStats.processedFolders++;
        updateProgress();
    }
    
    setLoading(false);
    
    // Show results
    if (globalStats.totalVideos > 0) {
        document.getElementById('videosContainer').classList.remove('hidden');
        document.getElementById('actionButtons').classList.remove('hidden');
        showToast(`✅ Done! Found ${globalStats.successVideos} videos`, 'success');
    } else {
        showError('Tidak ada video yang berhasil diambil dari semua folder');
    }
});

async function processUrl(url, folderNumber) {
    try {
        const apiUrl = `/api/index.php?url=${encodeURIComponent(url)}`;
        console.log(`Processing folder ${folderNumber}:`, apiUrl);
        
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Server tidak mengembalikan JSON');
        }
        
        const data = await response.json();
        console.log(`Folder ${folderNumber} response:`, data);
        
        if (data.success) {
            // Handle folder batch
            if (data.type === 'folder' && data.videos && data.videos.length > 0) {
                displayFolderVideos(data, url, folderNumber);
                allVideos.push(...data.videos);
                globalStats.totalVideos += data.videos.length;
                globalStats.successVideos += data.success_count || data.videos.filter(v => v.success).length;
                globalStats.failedVideos += (data.videos.length - (data.success_count || data.videos.filter(v => v.success).length));
            }
            // Handle single video
            else if (data.data && data.data.download_url) {
                const videoData = convertSingleToFolder(data);
                displayFolderVideos(videoData, url, folderNumber);
                allVideos.push(...videoData.videos);
                globalStats.totalVideos += 1;
                globalStats.successVideos += 1;
            }
            else {
                displayFolderError(url, folderNumber, 'Tidak ada video ditemukan');
            }
        } else {
            displayFolderError(url, folderNumber, data.message || data.error || 'Gagal memproses folder');
        }
    } catch (error) {
        console.error(`Error processing folder ${folderNumber}:`, error);
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
    const successCount = videos.filter(v => v.success).length;
    
    const gridClass = {
        2: 'lg:grid-cols-2',
        3: 'lg:grid-cols-3',
        4: 'lg:grid-cols-4',
        6: 'lg:grid-cols-6'
    };
    
    folderSection.innerHTML = `
        <div class="bg-white/5 backdrop-blur-lg rounded-2xl border-2 border-purple-500/30 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-2">
                        📁 Folder #${folderNumber}
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
            
            <div class="grid grid-cols-1 md:grid-cols-2 ${gridClass[currentGridSize]} gap-6" id="folder-${folderNumber}-grid">
                <!-- Videos will be inserted here -->
            </div>
        </div>
    `;
    
    container.appendChild(folderSection);
    
    const grid = folderSection.querySelector(`#folder-${folderNumber}-grid`);
    videos.forEach((video, index) => {
        const card = createVideoCard(video, index);
        grid.appendChild(card);
    });
}

function displayFolderError(sourceUrl, folderNumber, errorMessage) {
    const container = document.getElementById('videosContainer');
    
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
                        ❌ Folder #${folderNumber} - Error
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
    document.getElementById('totalVideos').textContent = globalStats.totalVideos;
    document.getElementById('successCount').textContent = globalStats.successVideos;
    document.getElementById('failedCount').textContent = globalStats.failedVideos;
    
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
    const searchBtn = document.getElementById('searchBtn');
    const searchIcon = document.getElementById('searchIcon');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const searchText = document.getElementById('searchText');
    
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
    document.getElementById('actionButtons').classList.add('hidden');
}

function showError(message) {
    hideAll();
    document.getElementById('errorText').textContent = message;
    document.getElementById('errorMessage').classList.remove('hidden');
}

function createVideoCard(video, index) {
    const isError = !video.success;
    const name = video.title || 'Unknown';
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
