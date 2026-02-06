<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vidoy Downloader - Bulk Edition</title>
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
            <p class="text-purple-400 text-sm font-semibold mt-2">✨ Bulk Edition - Support Multiple Folders</p>
        </div>

        <!-- Search Form -->
        <div class="mb-10 max-w-5xl mx-auto">
            <form id="searchForm" class="relative">
                <textarea
                    id="urlInput"
                    placeholder="Masukkan URL Vidoy (pisahkan dengan koma untuk multiple folders)&#10;&#10;Contoh:&#10;https://vid7.online/f/xxxxxxxxx, https://vid7.online/f/yyyyyyyyy, https://vid7.online/f/zzzzzzzzz"
                    class="w-full px-6 py-5 pr-6 rounded-2xl bg-white/10 backdrop-blur-lg border-2 border-purple-500/30 text-white text-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all resize-none"
                    rows="4"
                    required
                ></textarea>
                <button
                    type="submit"
                    id="searchBtn"
                    class="mt-4 w-full px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-xl font-bold transition-all flex items-center justify-center gap-2 shadow-lg"
                >
                    <svg id="searchIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" stroke-width="2"/>
                        <path d="m21 21-4.35-4.35" stroke-width="2"/>
                    </svg>
                    <div id="loadingSpinner" class="spinner hidden"></div>
                    <span id="searchText">Process URLs</span>
                </button>
            </form>
            <p class="text-gray-400 text-sm mt-3 text-center">
                💡 Pisahkan multiple URLs dengan <strong class="text-purple-400">koma (,)</strong> atau <strong class="text-purple-400">enter/baris baru</strong>
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
            <p class="text-gray-400 text-lg mb-2">Masukkan URL Vidoy di atas untuk memulai</p>
            <p class="text-gray-500 text-sm">Support single atau multiple folders (pisahkan dengan koma)</p>
            
            <div class="mt-12 max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">🚀</div>
                    <h4 class="text-white font-bold mb-2">Bulk Processing</h4>
                    <p class="text-gray-400 text-sm">Process multiple folders sekaligus</p>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">📦</div>
                    <h4 class="text-white font-bold mb-2">Auto Fetch</h4>
                    <p class="text-gray-400 text-sm">Otomatis ambil semua video dari setiap folder</p>
                </div>
                <div class="bg-white/5 backdrop-blur rounded-xl p-6 border border-purple-500/20">
                    <div class="text-4xl mb-3">⚡</div>
                    <h4 class="text-white font-bold mb-2">Super Fast</h4>
                    <p class="text-gray-400 text-sm">Proses download cepat & mudah</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-16 pb-8">
            <p class="text-gray-500 text-sm">Made with ❤️ using Vidoy API</p>
        </div>

    </div>
</div>

<script>
// Test API Connection
document.getElementById('testApiBtn').addEventListener('click', async () => {
    try {
        const response = await fetch('/api/index.php?test=1');
        const data = await response.json();
        
        if (data.success) {
            alert('✅ API Connection Success!\n\n' + 
                  'Timestamp: ' + data.timestamp + '\n' +
                  'PHP Version: ' + data.php_version + '\n' +
                  'cURL Available: ' + (data.curl_available ? 'Yes' : 'No'));
        } else {
            alert('❌ API Test Failed');
        }
    } catch (error) {
        alert('❌ Cannot connect to API\n\n' +
              'Error: ' + error.message + '\n\n' +
              'Pastikan file api/index.php ada di folder yang sama dengan index.html');
    }
});

// Parse URLs from input (support comma and newline separation)
function parseUrls(input) {
    // Split by comma or newline
    const urls = input
        .split(/[,\n]/)
        .map(url => url.trim())
        .filter(url => url.length > 0);
    
    return urls;
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
        showError('Silakan masukkan URL Vidoy');
        return;
    }
    
    // Parse URLs
    const urls = parseUrls(input);
    
    if (urls.length === 0) {
        showError('Tidak ada URL valid yang ditemukan');
        return;
    }
    
    // Reset global stats
    globalStats = {
        totalFolders: urls.length,
        processedFolders: 0,
        totalVideos: 0,
        successVideos: 0,
        failedVideos: 0
    };
    
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
                globalStats.totalVideos += data.videos.length;
                globalStats.successVideos += data.success_count || data.videos.filter(v => v.success).length;
                globalStats.failedVideos += (data.videos.length - (data.success_count || data.videos.filter(v => v.success).length));
            }
            // Handle single video
            else if (data.data && data.data.download_url) {
                const videoData = convertSingleToFolder(data);
                displayFolderVideos(videoData, url, folderNumber);
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
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="folder-${folderNumber}-grid">
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
        searchText.textContent = 'Process URLs';
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
