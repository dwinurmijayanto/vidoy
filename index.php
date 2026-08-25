<?php

if (isset($_GET['action']) && $_GET['action'] === 'folder') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    $folderUrl = trim($_GET['url'] ?? '');

    if ($folderUrl === '' || !preg_match('#^https?://#i', $folderUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter "url" folder tidak valid.']);
        exit;
    }

    $ch = curl_init($folderUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $html = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $curlErr) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil halaman folder.', 'error' => $curlErr]);
        exit;
    }
    if ($httpCode >= 400) {
        echo json_encode(['success' => false, 'message' => 'Halaman folder mengembalikan status error.', 'http_code' => $httpCode]);
        exit;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // Cari div dengan class "file-grid" (toleran walau ada class tambahan)
    $gridNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' file-grid ')]");

    if ($gridNodes->length === 0) {
        echo json_encode(['success' => false, 'message' => 'Elemen "file-grid" tidak ditemukan pada halaman folder.']);
        exit;
    }

    $parts = parse_url($folderUrl);
    $baseOrigin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

    $files = [];
    foreach ($gridNodes as $grid) {
        $links = $xpath->query('.//a[@href]', $grid);
        foreach ($links as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || strpos($href, '/d/') === false) {
                continue; // hanya link single video (/d/...)
            }

            if (preg_match('#^https?://#i', $href)) {
                $absolute = $href;
            } else {
                $absolute = $baseOrigin . '/' . ltrim($href, '/');
            }

            if (!in_array($absolute, $files, true)) {
                $files[] = $absolute;
            }
        }
    }

    if (empty($files)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada link video (/d/) ditemukan di dalam file-grid.']);
        exit;
    }

    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VDY Scan — Bulk Video Link Extractor</title>
<style>
  :root{
    --bg: #0a0f0e;
    --panel: #101716;
    --panel-2: #141d1c;
    --line: #223330;
    --text: #e7f2ee;
    --muted: #7a938c;
    --accent: #48e0c2;
    --accent-dim: #1f4a41;
    --error: #ff6b57;
    --ok: #48e0c2;
    --mono: ui-monospace, 'SF Mono', 'Cascadia Code', 'JetBrains Mono', Consolas, monospace;
    --sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, sans-serif;
  }
  *{ box-sizing: border-box; }
  body{
    margin:0;
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height:100vh;
    display:flex;
    justify-content:center;
    padding: 48px 20px 80px;
    background-image: radial-gradient(ellipse 80% 40% at 50% -10%, rgba(72,224,194,0.08), transparent);
  }
  .wrap{ width:100%; max-width: 860px; }

  .eyebrow{
    font-family: var(--mono); font-size: 12px; letter-spacing: 0.18em;
    color: var(--accent); text-transform: uppercase; margin-bottom: 10px;
    display:flex; align-items:center; gap:8px;
  }
  .eyebrow .dot{
    width:6px;height:6px;border-radius:50%; background: var(--accent);
    box-shadow: 0 0 8px var(--accent); animation: blink 1.6s ease-in-out infinite;
  }
  @keyframes blink{ 0%,100%{opacity:1} 50%{opacity:.25} }

  h1{ font-family: var(--mono); font-size: clamp(22px, 4vw, 30px); font-weight: 600; margin: 0 0 6px; letter-spacing: -0.01em; }
  .sub{ color: var(--muted); font-size: 13.5px; margin: 0 0 28px; line-height:1.55; max-width: 62ch; }
  .sub code{ font-family: var(--mono); background: var(--panel-2); padding: 1px 6px; border-radius: 4px; font-size: 12px; color: var(--text); }

  .panel{
    position:relative;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 14px;
    overflow:hidden;
  }
  .panel.scanning::before{
    content:""; position:absolute; left:0; right:0; top:0; height:2px;
    background: linear-gradient(90deg, transparent, var(--accent), transparent);
    animation: sweep 1.3s linear infinite;
    box-shadow: 0 0 12px var(--accent);
  }
  @keyframes sweep{ 0%{ transform: translateY(-4px); } 100%{ transform: translateY(140px); } }

  textarea{
    width:100%;
    min-height: 140px;
    resize: vertical;
    background: var(--panel-2);
    border: 1px solid var(--line);
    color: var(--text);
    font-family: var(--mono);
    font-size: 13px;
    line-height: 1.6;
    padding: 12px 14px;
    border-radius: 7px;
    outline:none;
    transition: border-color .15s;
  }
  textarea::placeholder{ color: #4a615c; }
  textarea:focus{ border-color: var(--accent); }

  .panel-footer{
    display:flex;
    align-items:center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    flex-wrap: wrap;
  }
  .hint{ font-family: var(--mono); font-size: 11px; color: var(--muted); }
  .count-badge{ font-family: var(--mono); font-size: 11px; color: var(--muted); }
  .count-badge b{ color: var(--accent); }

  button{
    font-family: var(--mono);
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    transition: filter .15s, transform .1s;
  }
  button:active{ transform: scale(0.98); }
  button:disabled{ opacity:.5; cursor: progress; }
  button:focus-visible{ outline: 2px solid var(--accent); outline-offset: 2px; }

  button.go{
    background: var(--accent);
    color: #04211b;
    font-size: 13px;
    padding: 11px 24px;
    white-space: nowrap;
  }
  button.go:hover{ filter: brightness(1.08); }

  button.ghost{
    background: transparent;
    border: 1px solid var(--line);
    color: var(--text);
    font-size: 11.5px;
    padding: 9px 16px;
  }
  button.ghost:hover{ border-color: var(--accent); color: var(--accent); }

  .status{ margin-top: 16px; font-family: var(--mono); font-size: 12.5px; color: var(--muted); display:none; }
  .status.show{ display:block; }
  .status .barwrap{ height:3px; background: var(--panel-2); border-radius: 2px; margin-top:8px; overflow:hidden; }
  .status .bar{ height:100%; background: var(--accent); width:0%; transition: width .2s ease; }

  .results-head{
    margin-top: 28px;
    display:none;
    align-items:center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .results-head.show{ display:flex; }
  .results-head .title{
    font-family: var(--mono);
    font-size: 11.5px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--accent);
  }
  .results-head .stats{ font-family: var(--mono); font-size: 11px; color: var(--muted); }

  .results{ margin-top: 12px; display:flex; flex-direction: column; gap: 8px; }

  .row{
    display:grid;
    grid-template-columns: 56px 1fr auto;
    align-items:center;
    gap: 12px;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 8px 10px;
  }
  .row.err{ border-color: rgba(255,107,87,0.35); background: rgba(255,107,87,0.04); }

  .row .thumb{
    width:56px; height:36px;
    border-radius: 5px;
    overflow:hidden;
    background: var(--panel-2);
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
  }
  .row .thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
  .row .thumb .dash{ color: var(--muted); font-size: 14px; font-family: var(--mono); }

  .row .info{ min-width:0; display:flex; flex-direction: column; gap: 2px; }
  .row .info .code{ font-family: var(--mono); font-size: 11.5px; color: var(--muted); }
  .row .info .url{
    font-family: var(--mono);
    font-size: 12.5px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .row.err .info .url{ color: var(--error); }

  .row .actions{ display:flex; gap:6px; flex-shrink:0; }
  .copy-btn{
    background: var(--panel-2);
    border: 1px solid var(--line);
    color: var(--text);
    font-family: var(--mono);
    font-size: 11px;
    padding: 7px 12px;
    border-radius: 6px;
  }
  .copy-btn:hover{ background: var(--accent-dim); color: var(--accent); }
  .copy-btn.copied{ background: var(--accent); color: #04211b; border-color: var(--accent); }

  footer{ margin-top: 40px; font-family: var(--mono); font-size: 11px; color: #3d534d; text-align:center; }

  @media (max-width: 560px){
    .row{ grid-template-columns: 44px 1fr; }
    .row .actions{ grid-column: 1 / -1; justify-content: flex-end; }
    .row .thumb{ width:44px; height:30px; }
  }
</style>
</head>
<body>

<div class="wrap">
  <div class="eyebrow"><span class="dot"></span>vidshare / scan / bulk</div>
  <h1>Bulk Video Link Extractor</h1>
  <p class="sub">Tempel banyak URL sekaligus, satu per baris — dari domain apa pun. Hanya baris berupa URL (http/https) yang diproses; domain diabaikan dan hanya kode terakhir pada path yang diambil. Link folder <code>/f/</code> otomatis dibuka dan semua file di dalamnya (<code>/d/...</code>) ikut diproses; link single <code>/d/</code> atau <code>/e/</code> diproses langsung.</p>

  <div class="panel" id="panel">
    <textarea id="input-bulk" placeholder="https://vdy.to/d/f5fn2v5mxec8&#10;https://vdy.to/f/mxicxvexlcj&#10;https://cdn.mp4ko.de/e/mqptlh3tw1jv&#10;..."></textarea>
    <div class="panel-footer">
      <span class="count-badge" id="count-badge"><b>0</b> baris terdeteksi</span>
      <div style="display:flex; gap:8px; align-items:center;">
        <button class="ghost" id="btn-clear" type="button">Bersihkan</button>
        <button class="go" id="btn-scan" type="button">Scan Semua</button>
      </div>
    </div>
  </div>

  <div class="status" id="status">
    <div id="status-text">Memindai 0 / 0…</div>
    <div class="barwrap"><div class="bar" id="status-bar"></div></div>
  </div>

  <div class="results-head" id="results-head">
    <span class="title">Hasil</span>
    <div style="display:flex; align-items:center; gap:12px;">
      <span class="stats" id="results-stats"></span>
      <button class="ghost" id="btn-copy-all" type="button">Copy Semua Video URL</button>
    </div>
  </div>

  <div class="results" id="results"></div>

  <footer>vidshare.my.id/scan &middot; powered by get-video-info API</footer>
</div>

<script>
const API_ENDPOINT = 'https://vidshare.my.id/scan/api/index.php';
const FOLDER_ENDPOINT = '?action=folder';

const $textarea   = document.getElementById('input-bulk');
const $countBadge = document.getElementById('count-badge');
const $btnScan    = document.getElementById('btn-scan');
const $btnClear   = document.getElementById('btn-clear');
const $panel      = document.getElementById('panel');
const $status     = document.getElementById('status');
const $statusText = document.getElementById('status-text');
const $statusBar  = document.getElementById('status-bar');
const $resultsHead= document.getElementById('results-head');
const $resultsStats = document.getElementById('results-stats');
const $results    = document.getElementById('results');
const $btnCopyAll = document.getElementById('btn-copy-all');

function getLines(){
  return $textarea.value
    .split('\n')
    .map(l => l.trim())
    .filter(Boolean);
}

function updateCount(){
  const n = getLines().length;
  $countBadge.innerHTML = `<b>${n}</b> baris terdeteksi`;
}
$textarea.addEventListener('input', updateCount);
updateCount();

$btnClear.addEventListener('click', () => {
  $textarea.value = '';
  updateCount();
  $results.innerHTML = '';
  $resultsHead.classList.remove('show');
  $status.classList.remove('show');
});

// Hanya proses baris berupa URL (http/https) — teks/ID polos ditolak di sini,
// karena kalau langsung dikirim sebagai id ke API sering muncul
// "ID video tidak dapat dikenali dari input yang diberikan" (mis. ID mengandung
// karakter seperti "-" yang tidak lolos validasi ID polos di API).
// Dari URL, kode diambil dari segmen terakhir path, domain diabaikan.
function extractCode(raw){
  let value = raw.trim();
  if (!value) return null;
  if (!/^https?:\/\//i.test(value)) return null; // bukan URL -> tolak

  try{
    const u = new URL(value);
    const segments = u.pathname.replace(/\/+$/, '').split('/').filter(Boolean);
    let code = segments.length ? segments[segments.length - 1] : '';
    if (!code){
      const qid = u.searchParams.get('id');
      if (qid) code = qid;
    }
    code = code.split('?')[0].split('#')[0].trim();
    return code || null;
  } catch(e){
    return null;
  }
}

function isFolderUrl(url){
  try{
    const u = new URL(url);
    return /\/f\//.test(u.pathname);
  } catch(e){
    return false;
  }
}

async function fetchFolderFiles(folderUrl){
  try{
    const res = await fetch(`${FOLDER_ENDPOINT}&url=${encodeURIComponent(folderUrl)}`);
    const data = await res.json();
    if (res.ok && data.success && Array.isArray(data.files) && data.files.length){
      return { files: data.files };
    }
    return { error: (data && data.message) || 'Gagal membaca isi folder.' };
  } catch(e){
    return { error: 'Gagal menghubungi endpoint folder.' };
  }
}

function rowTemplate(original, code){
  const row = document.createElement('div');
  row.className = 'row';
  row.innerHTML = `
    <div class="thumb"><span class="dash">···</span></div>
    <div class="info">
      <div class="code">${code || '(tidak terbaca)'}</div>
      <div class="url">Menunggu…</div>
    </div>
    <div class="actions">
      <button class="copy-btn" disabled>Copy</button>
    </div>
  `;
  return row;
}

function setRowSuccess(row, data){
  row.classList.remove('err');
  const thumbEl = row.querySelector('.thumb');
  const urlEl = row.querySelector('.url');
  const copyBtn = row.querySelector('.copy-btn');

  thumbEl.innerHTML = data.thumbnail_url
    ? `<img src="${data.thumbnail_url}" alt="${data.id}">`
    : '<span class="dash">—</span>';

  urlEl.textContent = data.video_url;
  urlEl.title = data.video_url;

  copyBtn.disabled = false;
  copyBtn.addEventListener('click', () => copyText(data.video_url, copyBtn));
}

function setRowError(row, message){
  row.classList.add('err');
  row.querySelector('.thumb').innerHTML = '<span class="dash">!</span>';
  const urlEl = row.querySelector('.url');
  urlEl.textContent = message;
  urlEl.title = message;
}

async function copyText(text, btn){
  if (!text) return;
  try{
    await navigator.clipboard.writeText(text);
  } catch(e){
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  }
  if (btn){
    const original = btn.textContent;
    btn.textContent = 'Copied';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = original; btn.classList.remove('copied'); }, 1100);
  }
}

async function scanAll(){
  // Hanya baris berupa URL (http/https) yang diproses.
  const lines = getLines().filter(l => /^https?:\/\//i.test(l));
  if (!lines.length) return;

  $btnScan.disabled = true;
  $panel.classList.add('scanning');
  $status.classList.add('show');
  $resultsHead.classList.remove('show');
  $results.innerHTML = '';
  $btnCopyAll.onclick = null;

  // --- Tahap 1: expand link folder (/f/) jadi daftar link single (/d/...) ---
  $statusText.textContent = 'Membuka folder…';
  $statusBar.style.width = '0%';

  const expanded = []; // { url } untuk yang siap diproses, atau { sourceLine, error } untuk folder gagal
  for (const line of lines){
    if (isFolderUrl(line)){
      const result = await fetchFolderFiles(line);
      if (result.error){
        expanded.push({ sourceLine: line, error: result.error });
      } else {
        result.files.forEach(fileUrl => expanded.push({ url: fileUrl }));
      }
    } else {
      expanded.push({ url: line });
    }
  }

  // Tampilkan dulu folder yang gagal dibuka sebagai row error.
  expanded.filter(e => e.error).forEach(e => {
    const row = rowTemplate(e.sourceLine, null);
    $results.appendChild(row);
    setRowError(row, e.error);
  });

  // Baris/URL hasil expand yang bukan URL video yang valid tidak ditampilkan sama sekali.
  const entries = expanded
    .filter(e => !e.error)
    .map(e => ({ original: e.url, code: extractCode(e.url) }))
    .filter(entry => entry.code);

  const rows = [];
  entries.forEach(entry => {
    const row = rowTemplate(entry.original, entry.code);
    $results.appendChild(row);
    rows.push(row);
  });

  if (!entries.length){
    $status.classList.remove('show');
    $panel.classList.remove('scanning');
    $btnScan.disabled = false;
    if (expanded.some(e => e.error)){
      $resultsHead.classList.add('show');
      $resultsStats.textContent = `0 berhasil / 0 file video ditemukan`;
    }
    return;
  }

  // --- Tahap 2: proses tiap single video seperti alur sebelumnya ---
  const successUrls = [];
  let done = 0;

  for (let i = 0; i < entries.length; i++){
    const { code } = entries[i];
    const row = rows[i];

    try{
      const res = await fetch(`${API_ENDPOINT}?id=${encodeURIComponent(code)}`);
      const data = await res.json();
      if (res.ok && data.success){
        setRowSuccess(row, data);
        successUrls.push(data.video_url);
      } else {
        setRowError(row, data.message || 'Gagal mengambil data.');
      }
    } catch(e){
      setRowError(row, 'Gagal menghubungi API.');
    }

    done++;
    $statusText.textContent = `Memindai ${done} / ${entries.length}…`;
    $statusBar.style.width = `${Math.round((done / entries.length) * 100)}%`;
  }

  $status.classList.remove('show');
  $panel.classList.remove('scanning');
  $btnScan.disabled = false;

  $resultsHead.classList.add('show');
  $resultsStats.textContent = `${successUrls.length} berhasil / ${entries.length - successUrls.length} gagal dari ${entries.length} video`;
  $btnCopyAll.onclick = () => copyText(successUrls.join('\n'), $btnCopyAll);
}

$btnScan.addEventListener('click', scanAll);
</script>

</body>
</html>
