<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CIVENTRAL Revenue API — Documentation</title>
  <meta name="description" content="Interactive API reference documentation for the CIVENTRAL Revenue & Treasury system. Covers Budget Requests, Online Payments, and Webhooks." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    :root {
      --bg:         #0d1117;
      --bg2:        #161b22;
      --bg3:        #21262d;
      --border:     #30363d;
      --border2:    #21262d;
      --text:       #e6edf3;
      --text-muted: #7d8590;
      --accent:     #1e6fcc;
      --accent-glow:#1e6fcc33;
      --green:      #3fb950;
      --yellow:     #d29922;
      --red:        #f85149;
      --orange:     #db6d28;
      --purple:     #8957e5;
      --teal:       #39c5cf;

      --method-get:    #1f6feb;
      --method-post:   #238636;
      --method-put:    #9e6a03;
      --method-patch:  #6e40c9;
      --method-delete: #b91c1c;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      font-size: 14px;
      line-height: 1.6;
      min-height: 100vh;
    }

    code, pre, .mono { font-family: 'JetBrains Mono', monospace; }

    /* ── Layout ─────────────────────────────────────────── */
    #app { display: flex; flex-direction: column; min-height: 100vh; }

    /* ── Header ─────────────────────────────────────────── */
    #doc-header {
      background: linear-gradient(135deg, #0a1628 0%, #0d1f3c 50%, #0f2042 100%);
      border-bottom: 1px solid var(--border);
      padding: 48px 64px 40px;
      position: relative;
      overflow: hidden;
    }
    #doc-header::before {
      content: '';
      position: absolute;
      top: -60px; right: -80px;
      width: 400px; height: 400px;
      background: radial-gradient(circle, #1e6fcc22 0%, transparent 70%);
      pointer-events: none;
    }

    .header-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 28px;
    }
    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--accent-glow);
      border: 1px solid var(--accent);
      border-radius: 8px;
      padding: 6px 14px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: #58a6ff;
    }
    .brand-badge i { font-size: 12px; }

    .meta-pills { display: flex; flex-wrap: wrap; gap: 8px; }
    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 4px 12px;
      font-size: 11px;
      color: var(--text-muted);
      font-weight: 500;
    }
    .meta-pill i { font-size: 10px; }

    .doc-title {
      font-size: 2.4rem;
      font-weight: 900;
      letter-spacing: -1px;
      line-height: 1.1;
      margin-bottom: 10px;
      background: linear-gradient(135deg, #e6edf3, #58a6ff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .doc-subtitle {
      color: var(--text-muted);
      font-size: 0.95rem;
      max-width: 620px;
      margin-bottom: 32px;
    }

    /* ── Overview Grid ──────────────────────────────────── */
    .overview-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }
    .overview-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      transition: border-color 0.2s;
    }
    .overview-card:hover { border-color: var(--accent); }
    .overview-card-header {
      display: flex; align-items: center; gap: 8px;
      font-size: 10px; font-weight: 700; letter-spacing: 1px;
      text-transform: uppercase; color: var(--text-muted);
      margin-bottom: 8px;
    }
    .overview-card-header i { color: var(--accent); width: 14px; text-align: center; }
    .overview-card-value {
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      font-weight: 600;
      color: #58a6ff;
      word-break: break-all;
    }

    .alert-box {
      display: flex; align-items: flex-start; gap: 12px;
      border-radius: 8px; padding: 12px 16px;
      font-size: 12px; margin-bottom: 10px;
    }
    .alert-box.alert-info { background: #1e3a5f22; border: 1px solid #1e6fcc55; color: #8ab8e8; }
    .alert-box.alert-warning { background: #3d260022; border: 1px solid #d2992255; color: #d4a843; }
    .alert-icon { margin-top: 1px; flex-shrink: 0; font-size: 13px; }

    /* ── Toolbar ─────────────────────────────────────────── */
    #toolbar {
      position: sticky;
      top: 0;
      z-index: 50;
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 12px 64px;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .search-wrapper {
      position: relative;
      flex: 1;
      min-width: 200px;
    }
    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: 12px;
      pointer-events: none;
    }
    #search-box {
      width: 100%;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px 8px 34px;
      color: var(--text);
      font-size: 13px;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    #search-box:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    #search-box::placeholder { color: var(--text-muted); }

    .jump-wrapper { position: relative; }
    .jump-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 11px; pointer-events: none; }
    #group-jump {
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px 8px 30px;
      color: var(--text);
      font-size: 12px;
      outline: none;
      cursor: pointer;
    }

    .method-filters { display: flex; gap: 4px; }
    .mf-btn {
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 6px 12px;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      background: var(--bg3);
      color: var(--text-muted);
      transition: all 0.15s;
      letter-spacing: 0.5px;
    }
    .mf-btn:hover { border-color: var(--text-muted); color: var(--text); }
    .mf-btn.active[data-m="ALL"]    { background: var(--accent);      border-color: var(--accent);      color: #fff; }
    .mf-btn.active[data-m="GET"]    { background: var(--method-get);   border-color: var(--method-get);   color: #fff; }
    .mf-btn.active[data-m="POST"]   { background: var(--method-post);  border-color: var(--method-post);  color: #fff; }
    .mf-btn.active[data-m="PUT"]    { background: var(--method-put);   border-color: var(--method-put);   color: #fff; }
    .mf-btn.active[data-m="DELETE"] { background: var(--method-delete);border-color: var(--method-delete);color: #fff; }

    .expand-btns { display: flex; gap: 6px; margin-left: auto; }
    .ctrl-btn {
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 6px 12px;
      font-size: 11px;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.15s;
      display: inline-flex; align-items: center; gap: 5px;
    }
    .ctrl-btn:hover { border-color: var(--accent); color: var(--accent); }

    /* ── Main Content ────────────────────────────────────── */
    #main-content { flex: 1; padding: 32px 64px 64px; max-width: 1100px; margin: 0 auto; width: 100%; }

    /* ── Group Section ────────────────────────────────────── */
    .group-section { margin-bottom: 48px; }
    .group-header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 20px;
    }
    .group-icon {
      width: 38px; height: 38px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px;
    }
    .group-title { font-size: 1.1rem; font-weight: 800; }
    .group-desc { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
    .group-count {
      margin-left: auto;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2px 10px;
      font-size: 11px;
      font-weight: 700;
      color: var(--text-muted);
    }

    /* ── Endpoint Card ─────────────────────────────────────── */
    .endpoint-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 12px;
      margin-bottom: 12px;
      overflow: hidden;
      transition: border-color 0.2s;
    }
    .endpoint-card:hover { border-color: #3d444d; }
    .endpoint-card.hidden { display: none; }

    .endpoint-trigger {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
      cursor: pointer;
      user-select: none;
      background: transparent;
      border: none;
      width: 100%;
      text-align: left;
    }
    .endpoint-trigger:hover { background: var(--bg3); }

    .method-badge {
      font-family: 'JetBrains Mono', monospace;
      font-size: 10px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 5px;
      min-width: 58px;
      text-align: center;
      letter-spacing: 0.5px;
    }
    .badge-GET    { background: #1e6feb22; color: #58a6ff; border: 1px solid #1e6feb55; }
    .badge-POST   { background: #23863622; color: #3fb950; border: 1px solid #23863655; }
    .badge-PUT    { background: #9e6a0322; color: #d29922; border: 1px solid #9e6a0355; }
    .badge-PATCH  { background: #6e40c922; color: #a371f7; border: 1px solid #6e40c955; }
    .badge-DELETE { background: #b91c1c22; color: #f85149; border: 1px solid #b91c1c55; }

    .endpoint-path {
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
      flex: 1;
    }
    .endpoint-path .path-param { color: #d29922; }
    .endpoint-summary { font-size: 12px; color: var(--text-muted); }
    .endpoint-chevron { color: var(--text-muted); font-size: 12px; margin-left: 8px; transition: transform 0.2s; }
    .endpoint-card.open .endpoint-chevron { transform: rotate(180deg); }

    /* ── Endpoint Body ─────────────────────────────────────── */
    .endpoint-body {
      display: none;
      padding: 0 20px 20px;
      border-top: 1px solid var(--border2);
      margin-top: 0;
    }
    .endpoint-card.open .endpoint-body { display: block; }

    .full-url-bar {
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 14px;
      color: var(--text-muted);
      margin: 14px 0 16px;
      word-break: break-all;
    }
    .full-url-bar span { color: #58a6ff; }

    .body-section { margin-bottom: 20px; }
    .section-label {
      font-size: 10px;
      font-weight: 800;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .section-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* ── Params Table ─────────────────────────────────────── */
    .params-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .params-table th {
      text-align: left;
      padding: 6px 10px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      color: var(--text-muted);
      background: var(--bg3);
      border-bottom: 1px solid var(--border);
    }
    .params-table td {
      padding: 8px 10px;
      border-bottom: 1px solid var(--border2);
      vertical-align: top;
    }
    .params-table tr:last-child td { border-bottom: none; }
    .param-name { font-family: 'JetBrains Mono', monospace; color: #58a6ff; font-weight: 600; }
    .param-type { color: var(--teal); font-family: 'JetBrains Mono', monospace; font-size: 11px; }
    .param-desc { color: var(--text-muted); }
    .badge-required {
      display: inline-block;
      background: #b91c1c22;
      border: 1px solid #b91c1c55;
      color: #f85149;
      border-radius: 4px;
      padding: 1px 6px;
      font-size: 9px;
      font-weight: 700;
      letter-spacing: 0.5px;
    }
    .badge-optional {
      display: inline-block;
      background: var(--bg3);
      border: 1px solid var(--border);
      color: var(--text-muted);
      border-radius: 4px;
      padding: 1px 6px;
      font-size: 9px;
      font-weight: 700;
    }

    /* ── Code Blocks ─────────────────────────────────────── */
    .code-block-wrapper { position: relative; }
    .copy-btn {
      position: absolute;
      top: 8px; right: 8px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 4px 10px;
      font-size: 10px;
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.15s;
      display: flex; align-items: center; gap: 4px;
      z-index: 1;
    }
    .copy-btn:hover { border-color: var(--accent); color: var(--accent); }
    .copy-btn.copied { border-color: var(--green); color: var(--green); }

    pre {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
      overflow-x: auto;
      font-size: 12px;
      line-height: 1.7;
      color: var(--text);
    }
    pre .key   { color: #58a6ff; }
    pre .str   { color: #a5d6ff; }
    pre .num   { color: #79c0ff; }
    pre .bool  { color: var(--green); }
    pre .null  { color: var(--text-muted); }
    pre .cmt   { color: var(--text-muted); font-style: italic; }

    /* ── Response Tabs ─────────────────────────────────────── */
    .response-tabs { display: flex; gap: 4px; margin-bottom: 8px; }
    .rtab {
      padding: 5px 12px;
      border-radius: 6px 6px 0 0;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      border: 1px solid var(--border);
      border-bottom: none;
      background: var(--bg3);
      color: var(--text-muted);
      transition: all 0.15s;
    }
    .rtab.active { background: var(--bg); color: var(--text); border-color: var(--border); }
    .rtab.success-tab.active { color: var(--green); }
    .rtab.error-tab.active   { color: var(--red); }
    .rtab-panel { display: none; }
    .rtab-panel.active { display: block; }

    /* ── Auth Note ─────────────────────────────────────── */
    .auth-note {
      display: flex; align-items: center; gap: 8px;
      background: #9e6a0311;
      border: 1px solid #9e6a0344;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 11px;
      color: #d29922;
      margin-bottom: 12px;
    }

    /* ── No results ─────────────────────────────────────── */
    #no-results {
      display: none;
      text-align: center;
      padding: 80px 32px;
      color: var(--text-muted);
    }
    #no-results i { font-size: 48px; margin-bottom: 16px; opacity: 0.3; }
    #no-results p { font-weight: 600; font-size: 1rem; }

    /* ── Footer ─────────────────────────────────────────── */
    #doc-footer {
      background: var(--bg2);
      border-top: 1px solid var(--border);
      padding: 20px 64px;
      font-size: 12px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
    }
    .footer-brand { font-weight: 700; color: #58a6ff; }

    /* ── Responsive ─────────────────────────────────────── */
    @media (max-width: 768px) {
      #doc-header, #toolbar, #main-content, #doc-footer { padding-left: 20px; padding-right: 20px; }
      .doc-title { font-size: 1.6rem; }
    }
  </style>
</head>
<body>
<div id="app">

  <!-- HEADER -->
  <header id="doc-header">
    <div class="header-top">
      <div class="brand-badge"><i class="fa-solid fa-code"></i> CIVENTRAL REVENUE API</div>
      <div class="meta-pills">
        <span class="meta-pill"><i class="fa-solid fa-tag"></i> v2.0.0</span>
        <span class="meta-pill"><i class="fa-solid fa-server"></i> Production</span>
        <span class="meta-pill"><i class="fa-solid fa-cube"></i> <strong id="stat-ops">--</strong>&nbsp;Operations</span>
        <span class="meta-pill"><i class="fa-solid fa-folder-tree"></i> <strong id="stat-groups">--</strong>&nbsp;Groups</span>
      </div>
    </div>

    <h1 class="doc-title">CIVENTRAL Revenue API<br>Documentation</h1>
    <p class="doc-subtitle">Complete REST API reference for the CIVENTRAL Revenue & Treasury system — covering Budget Requests, Online Payments (PayMongo), and Webhooks.</p>

    <div class="overview-grid">
      <div class="overview-card">
        <div class="overview-card-header"><i class="fa-solid fa-globe"></i> Production URL</div>
        <div class="overview-card-value">https://revenue.civentral.tech</div>
      </div>
      <div class="overview-card">
        <div class="overview-card-header"><i class="fa-solid fa-desktop"></i> Local Dev URL</div>
        <div class="overview-card-value">http://localhost/civentrel</div>
      </div>
      <div class="overview-card">
        <div class="overview-card-header"><i class="fa-solid fa-money-bill-transfer"></i> Budget API</div>
        <div class="overview-card-value">/api/treasury/budget.php</div>
      </div>
      <div class="overview-card">
        <div class="overview-card-header"><i class="fa-solid fa-credit-card"></i> Payment API</div>
        <div class="overview-card-value">/api/citizen/treasury/payments.php</div>
      </div>
    </div>

    <div class="alert-box alert-info">
      <i class="fa-solid fa-circle-info alert-icon"></i>
      <div><strong>Response Format:</strong> All endpoints return JSON with a top-level <code style="color:#58a6ff">status</code> field of either <code style="color:#3fb950">"success"</code> or <code style="color:#f85149">"error"</code>.</div>
    </div>
    <div class="alert-box alert-warning">
      <i class="fa-solid fa-key alert-icon"></i>
      <div><strong>Authentication:</strong> The Budget API supports optional API key authentication via the <code style="color:#d29922">X-API-Key</code> header when <code style="color:#d29922">BUDGET_API_KEY</code> is set in the server environment.</div>
    </div>
  </header>

  <!-- TOOLBAR -->
  <div id="toolbar">
    <div class="search-wrapper">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" id="search-box" placeholder="Search endpoints, methods, descriptions…" oninput="doSearch()" />
    </div>
    <div class="jump-wrapper">
      <i class="fa-solid fa-list-ul jump-icon"></i>
      <select id="group-jump" onchange="jumpToGroup(this.value)">
        <option value="">Jump to group…</option>
      </select>
    </div>
    <div class="method-filters" id="method-filters">
      <button class="mf-btn active" data-m="ALL"    onclick="filterMethod('ALL')">All</button>
      <button class="mf-btn"        data-m="GET"    onclick="filterMethod('GET')">GET</button>
      <button class="mf-btn"        data-m="POST"   onclick="filterMethod('POST')">POST</button>
      <button class="mf-btn"        data-m="PUT"    onclick="filterMethod('PUT')">PUT</button>
    </div>
    <div class="expand-btns">
      <button class="ctrl-btn" onclick="expandAll()"><i class="fa-solid fa-angles-down"></i> Expand All</button>
      <button class="ctrl-btn" onclick="collapseAll()"><i class="fa-solid fa-angles-up"></i> Collapse All</button>
    </div>
  </div>

  <!-- MAIN -->
  <main id="main-content">
    <div id="no-results">
      <i class="fa-solid fa-filter-circle-xmark"></i>
      <p>No endpoints match your filter.</p>
    </div>
  </main>

  <!-- FOOTER -->
  <footer id="doc-footer">
    <div><span class="footer-brand">CIVENTRAL</span> Revenue & Treasury API &bull; v2.0.0 &bull; <?= date('F Y') ?></div>
    <div>Caloocan Digital Government Platform</div>
  </footer>
</div>

<script>
// ── Data ──────────────────────────────────────────────────────────────────────
const BASE_PROD  = 'https://revenue.civentral.tech';
const BASE_LOCAL = 'http://localhost/civentrel';
const BUDGET_PATH   = '/api/treasury/budget.php';
const PAYMENT_PATH  = '/api/citizen/treasury/payments.php';
const WEBHOOK_PATH  = '/api/webhooks/paymongo.php';

const API_GROUPS = [
  {
    id:    'budget',
    title: 'Budget Requests',
    desc:  'Allow any department or module to submit, track, approve, and release budget requests.',
    icon:  'fa-money-bill-transfer',
    color: '#d29922',
    bg:    '#3d260033',
    endpoints: [
      {
        method: 'POST',
        path: BUDGET_PATH,
        pathSuffix: '',
        summary: 'Submit a new budget request',
        auth: true,
        body: [
          { name: 'department_name',  type: 'string',  req: true,  desc: 'Full name of the requesting department (e.g. "Department of Health")' },
          { name: 'department_code',  type: 'string',  req: false, desc: 'Short department code (e.g. HEALTH, ENGG, EDUC). Defaults to GEN' },
          { name: 'project_title',    type: 'string',  req: true,  desc: 'Title of the budget item or project' },
          { name: 'requested_amount', type: 'number',  req: true,  desc: 'Amount requested in PHP (e.g. 50000.00)' },
          { name: 'budget_type',      type: 'string',  req: false, desc: 'operational | capital | supplemental | emergency. Defaults to operational' },
          { name: 'description',      type: 'string',  req: false, desc: 'Detailed description of what the budget will be used for' },
          { name: 'justification',    type: 'string',  req: false, desc: 'Why this budget is needed — supports the approval process' },
          { name: 'requested_by',     type: 'string',  req: false, desc: 'Name of the person or system submitting the request' },
          { name: 'fiscal_year',      type: 'integer', req: false, desc: 'Fiscal year for this request (e.g. 2026). Defaults to current year' },
          { name: 'quarter',          type: 'string',  req: false, desc: 'Q1 | Q2 | Q3 | Q4. Defaults to the current quarter' },
          { name: 'fund_id',          type: 'string',  req: false, desc: 'Fund code: GF (General Fund) | SEF | TF. Defaults to GF' },
        ],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"message"</span>: <span class="str">"Budget request submitted successfully and is now pending review."</span>,
  <span class="key">"data"</span>: {
    <span class="key">"id"</span>: <span class="num">42</span>,
    <span class="key">"request_no"</span>: <span class="str">"BR-2026-000042"</span>,
    <span class="key">"department_name"</span>: <span class="str">"Department of Health"</span>,
    <span class="key">"department_code"</span>: <span class="str">"HEALTH"</span>,
    <span class="key">"project_title"</span>: <span class="str">"Medical Supplies Procurement Q3"</span>,
    <span class="key">"requested_amount"</span>: <span class="num">150000</span>,
    <span class="key">"budget_type"</span>: <span class="str">"operational"</span>,
    <span class="key">"status"</span>: <span class="str">"pending"</span>,
    <span class="key">"fiscal_year"</span>: <span class="num">2026</span>,
    <span class="key">"quarter"</span>: <span class="str">"Q3"</span>,
    <span class="key">"fund_code"</span>: <span class="str">"GF"</span>,
    <span class="key">"created_at"</span>: <span class="str">"2026-09-25 18:00:00"</span>
  }
}`,
        errorResponse: `{
  <span class="key">"status"</span>: <span class="str">"error"</span>,
  <span class="key">"message"</span>: <span class="str">"Missing required fields: department_name, requested_amount"</span>,
  <span class="key">"required_fields"</span>: { <span class="cmt">/* field descriptions */</span> }
}`,
        curlExample: `curl -X POST "${BASE_PROD}${BUDGET_PATH}" \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: your-api-key" \\
  -d '{
    "department_name":  "Department of Health",
    "department_code":  "HEALTH",
    "project_title":    "Medical Supplies Procurement Q3",
    "requested_amount": 150000,
    "budget_type":      "operational",
    "description":      "Procurement of essential medical supplies for Q3 2026",
    "justification":    "Supplies are critically low and need immediate replenishment",
    "requested_by":     "Dr. Maria Santos",
    "fiscal_year":      2026,
    "quarter":          "Q3",
    "fund_id":          "GF"
  }'`,
      },
      {
        method: 'GET',
        path: BUDGET_PATH,
        pathSuffix: '/budget/requests',
        summary: 'List all budget requests',
        auth: true,
        params: [],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"count"</span>: <span class="num">3</span>,
  <span class="key">"data"</span>: [
    {
      <span class="key">"id"</span>: <span class="num">42</span>,
      <span class="key">"request_no"</span>: <span class="str">"BR-2026-000042"</span>,
      <span class="key">"department_name"</span>: <span class="str">"Department of Health"</span>,
      <span class="key">"status"</span>: <span class="str">"pending"</span>,
      <span class="key">"requested_amount"</span>: <span class="num">150000</span>
    }
  ]
}`,
        curlExample: `curl -X GET "${BASE_PROD}${BUDGET_PATH}/budget/requests" \\
  -H "X-API-Key: your-api-key"`,
      },
      {
        method: 'GET',
        path: BUDGET_PATH,
        pathSuffix: '/budget/requests/{id}',
        summary: 'Get a specific budget request by ID',
        auth: true,
        params: [{ name: 'id', type: 'integer', req: true, desc: 'The unique ID of the budget request (path parameter)' }],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"data"</span>: {
    <span class="key">"id"</span>: <span class="num">42</span>,
    <span class="key">"request_no"</span>: <span class="str">"BR-2026-000042"</span>,
    <span class="key">"department_name"</span>: <span class="str">"Department of Health"</span>,
    <span class="key">"status"</span>: <span class="str">"approved"</span>,
    <span class="key">"approved_by"</span>: <span class="str">"Treasurer"</span>,
    <span class="key">"approved_at"</span>: <span class="str">"2026-09-25 14:30:00"</span>
  }
}`,
        curlExample: `curl -X GET "${BASE_PROD}${BUDGET_PATH}/budget/requests/42" \\
  -H "X-API-Key: your-api-key"`,
      },
      {
        method: 'GET',
        path: BUDGET_PATH,
        pathSuffix: '/budget/department/{code}',
        summary: 'Get all budget requests by department code',
        auth: true,
        params: [{ name: 'code', type: 'string', req: true, desc: 'Department code (e.g. HEALTH, ENGG, EDUC)' }],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"count"</span>: <span class="num">2</span>,
  <span class="key">"data"</span>: [ <span class="cmt">/* array of budget requests for this department */</span> ]
}`,
        curlExample: `curl -X GET "${BASE_PROD}${BUDGET_PATH}/budget/department/HEALTH" \\
  -H "X-API-Key: your-api-key"`,
      },
      {
        method: 'PUT',
        path: BUDGET_PATH,
        pathSuffix: '/budget/requests/{id}/approve',
        summary: 'Approve a budget request',
        auth: true,
        params: [
          { name: 'id',           type: 'integer', req: true,  desc: 'Budget request ID (path parameter)' },
          { name: 'X-Approved-By',type: 'header',  req: false, desc: 'Name of the approver (HTTP header). Defaults to API Admin' },
        ],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"message"</span>: <span class="str">"Budget request approved."</span>,
  <span class="key">"data"</span>: { <span class="cmt">/* updated budget request object */</span> }
}`,
        curlExample: `curl -X PUT "${BASE_PROD}${BUDGET_PATH}/budget/requests/42/approve" \\
  -H "X-API-Key: your-api-key" \\
  -H "X-Approved-By: Treasurer Juan dela Cruz"`,
      },
      {
        method: 'PUT',
        path: BUDGET_PATH,
        pathSuffix: '/budget/requests/{id}/reject',
        summary: 'Reject a budget request',
        auth: true,
        body: [{ name: 'rejection_reason', type: 'string', req: true, desc: 'Reason for the rejection — required for audit trail' }],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"message"</span>: <span class="str">"Budget request rejected."</span>,
  <span class="key">"data"</span>: { <span class="cmt">/* updated budget request with rejection_reason */</span> }
}`,
        curlExample: `curl -X PUT "${BASE_PROD}${BUDGET_PATH}/budget/requests/42/reject" \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: your-api-key" \\
  -d '{ "rejection_reason": "Budget exceeded allocated ceiling for Q3." }'`,
      },
      {
        method: 'PUT',
        path: BUDGET_PATH,
        pathSuffix: '/budget/requests/{id}/release',
        summary: 'Release an approved budget to the department',
        auth: true,
        params: [{ name: 'id', type: 'integer', req: true, desc: 'Budget request ID (path parameter). Must be in Approved status.' }],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"message"</span>: <span class="str">"Budget released successfully."</span>,
  <span class="key">"data"</span>: { <span class="cmt">/* updated budget request with Released status */</span> }
}`,
        curlExample: `curl -X PUT "${BASE_PROD}${BUDGET_PATH}/budget/requests/42/release" \\
  -H "X-API-Key: your-api-key"`,
      },
    ],
  },

  {
    id:    'payments',
    title: 'Online Payments (PayMongo)',
    desc:  'Create PayMongo checkout sessions for citizens to pay taxes and fees via GCash, PayMaya, or card.',
    icon:  'fa-credit-card',
    color: '#3fb950',
    bg:    '#23863622',
    endpoints: [
      {
        method: 'POST',
        path: PAYMENT_PATH,
        pathSuffix: '',
        summary: 'Initiate a PayMongo checkout session for a citizen payment',
        auth: false,
        body: [
          { name: 'taxpayer_name',    type: 'string',  req: true,  desc: 'Full name of the citizen / taxpayer' },
          { name: 'account_number',   type: 'string',  req: true,  desc: 'Taxpayer account or reference number (e.g. T-12345)' },
          { name: 'email',            type: 'string',  req: true,  desc: 'Valid email address — used for PayMongo receipt' },
          { name: 'payment_type',     type: 'string',  req: true,  desc: 'Type of payment (e.g. "Real Property Tax", "Business Tax & Fees")' },
          { name: 'amount',           type: 'number',  req: true,  desc: 'Amount in PHP without service fee (e.g. 4200)' },
          { name: 'payment_method',   type: 'string',  req: true,  desc: 'GCash | PayMaya | Bank Transfer | Debit/Credit Card' },
          { name: 'notes',            type: 'string',  req: false, desc: 'Additional notes about the payment' },
          { name: 'citizen_user_id',  type: 'integer', req: false, desc: 'Citizen user ID from the CIVENTRAL citizen database' },
          { name: 'idempotency_key',  type: 'string',  req: false, desc: 'Unique client-generated key to prevent duplicate submissions' },
        ],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"message"</span>: <span class="str">"Payment checkout link generated successfully."</span>,
  <span class="key">"transaction_id"</span>: <span class="str">"PAY-1790330837-ca1bee1b"</span>,
  <span class="key">"reference_no"</span>: <span class="str">"PAY-1790330837-ca1bee1b"</span>,
  <span class="key">"checkout_url"</span>: <span class="str">"https://checkout.paymongo.com/cs_xxxx#pk_live_xxxx"</span>,
  <span class="key">"data"</span>: {
    <span class="key">"checkout_url"</span>: <span class="str">"https://checkout.paymongo.com/cs_xxxx#pk_live_xxxx"</span>,
    <span class="key">"payment_reference"</span>: <span class="str">"PAY-1790330837-ca1bee1b"</span>,
    <span class="key">"status"</span>: <span class="str">"processing"</span>
  }
}`,
        errorResponse: `{
  <span class="key">"status"</span>: <span class="str">"error"</span>,
  <span class="key">"message"</span>: <span class="str">"Unauthorized: Citizen authentication required"</span>
}`,
        curlExample: `curl -X POST "${BASE_PROD}${PAYMENT_PATH}" \\
  -H "Content-Type: application/json" \\
  -d '{
    "taxpayer_name":   "Maria Santos",
    "account_number":  "T-12345",
    "email":           "maria.santos@example.com",
    "payment_type":    "Real Property Tax",
    "amount":          4200,
    "payment_method":  "GCash",
    "citizen_user_id": 88
  }'`,
      },
      {
        method: 'GET',
        path: PAYMENT_PATH,
        pathSuffix: '?citizen_id={id}',
        summary: "Retrieve a citizen's payment history",
        auth: false,
        params: [{ name: 'citizen_id', type: 'integer', req: true, desc: 'The citizen user ID to retrieve payment history for' }],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"data"</span>: [
    {
      <span class="key">"id"</span>: <span class="num">5</span>,
      <span class="key">"payment_reference"</span>: <span class="str">"PAY-1790330837-ca1bee1b"</span>,
      <span class="key">"payment_type"</span>: <span class="str">"Real Property Tax"</span>,
      <span class="key">"amount"</span>: <span class="str">"4200.00"</span>,
      <span class="key">"status"</span>: <span class="str">"completed"</span>,
      <span class="key">"receipt_no"</span>: <span class="str">"OR-2026-27B723"</span>,
      <span class="key">"created_at"</span>: <span class="str">"2026-09-25 18:07:17"</span>
    }
  ]
}`,
        curlExample: `curl -X GET "${BASE_PROD}${PAYMENT_PATH}?citizen_id=88"`,
      },
    ],
  },

  {
    id:    'webhooks',
    title: 'PayMongo Webhooks',
    desc:  'PayMongo calls this endpoint automatically when a payment is confirmed. It generates the OR, updates the status to Completed, and records the collection.',
    icon:  'fa-webhook',
    color: '#a371f7',
    bg:    '#6e40c922',
    endpoints: [
      {
        method: 'POST',
        path: WEBHOOK_PATH,
        pathSuffix: '',
        summary: 'PayMongo payment confirmation webhook receiver',
        auth: false,
        params: [
          { name: 'HTTP_PAYMONGO_SIGNATURE', type: 'header', req: true, desc: 'Webhook signature sent by PayMongo for payload verification' },
        ],
        body: [
          { name: 'data.attributes.type', type: 'string', req: true, desc: 'Must be checkout_session.payment.paid or payment.paid' },
          { name: 'data.attributes.data.attributes.reference_number', type: 'string', req: true, desc: 'Your payment reference that was passed during checkout session creation' },
        ],
        successResponse: `{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"message"</span>: <span class="str">"Payment processed and recorded successfully"</span>
}`,
        errorResponse: `{
  <span class="key">"status"</span>: <span class="str">"error"</span>,
  <span class="key">"message"</span>: <span class="str">"Transaction not found in database"</span>
}`,
        curlExample: `# This endpoint is called BY PayMongo — not by your frontend.
# Configure the Webhook URL in:
# PayMongo Dashboard → Developers → Webhooks → Add Webhook
# URL: ${BASE_PROD}${WEBHOOK_PATH}
# Event: checkout_session.payment.paid`,
      },
    ],
  },
];

// ── Render ────────────────────────────────────────────────────────────────────
let activeMethod = 'ALL';
let cardIndex = 0;

function formatPath(p, suffix) {
  return suffix.replace(/\{(\w+)\}/g, '<span class="path-param">{$1}</span>');
}

function renderParamsTable(params, type) {
  if (!params || params.length === 0) return '';
  const rows = params.map(p => `
    <tr>
      <td><span class="param-name">${p.name}</span></td>
      <td><span class="param-type">${p.type}</span></td>
      <td>${p.req ? '<span class="badge-required">required</span>' : '<span class="badge-optional">optional</span>'}</td>
      <td class="param-desc">${p.desc}</td>
    </tr>`).join('');
  return `
    <div class="body-section">
      <div class="section-label"><i class="fa-solid fa-${type === 'body' ? 'code' : 'sliders'}"></i> ${type === 'body' ? 'Request Body (JSON)' : 'Parameters'}</div>
      <table class="params-table">
        <thead><tr><th>Field</th><th>Type</th><th>Req.</th><th>Description</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

function renderCard(ep, gId) {
  const idx = cardIndex++;
  const cardId = `card-${idx}`;
  const tabSuccId = `tab-s-${idx}`;
  const tabErrId  = `tab-e-${idx}`;
  const panSuccId = `pan-s-${idx}`;
  const panErrId  = `pan-e-${idx}`;

  const pathDisplay = ep.pathSuffix
    ? (ep.path.replace('.php','') + ep.pathSuffix)
    : ep.path;

  const hasError = !!ep.errorResponse;

  return `
<div id="${cardId}" class="endpoint-card" data-method="${ep.method}" data-group="${gId}">
  <button class="endpoint-trigger" onclick="toggleCard('${cardId}')">
    <span class="method-badge badge-${ep.method}">${ep.method}</span>
    <span class="endpoint-path">${formatPath(ep.path, pathDisplay)}</span>
    <span class="endpoint-summary">${ep.summary}</span>
    <i class="fa-solid fa-chevron-down endpoint-chevron"></i>
  </button>
  <div class="endpoint-body">
    ${ep.auth ? '<div class="auth-note"><i class="fa-solid fa-key"></i> Supports optional API key authentication via <code>X-API-Key</code> header.</div>' : ''}
    <div class="full-url-bar">
      <span style="color:var(--text-muted)">${ep.method}</span>&nbsp;
      <span>${BASE_PROD}${pathDisplay}</span>
    </div>
    ${renderParamsTable(ep.params, 'param')}
    ${renderParamsTable(ep.body, 'body')}

    ${ep.curlExample ? `
    <div class="body-section">
      <div class="section-label"><i class="fa-solid fa-terminal"></i> cURL Example</div>
      <div class="code-block-wrapper">
        <button class="copy-btn" onclick="copyCode(this)"><i class="fa-regular fa-copy"></i> Copy</button>
        <pre>${ep.curlExample}</pre>
      </div>
    </div>` : ''}

    <div class="body-section">
      <div class="section-label"><i class="fa-solid fa-arrow-right-from-bracket"></i> Responses</div>
      <div class="response-tabs">
        <div class="rtab success-tab active" onclick="switchTab('${tabSuccId}','${tabErrId}','${panSuccId}','${panErrId}',this)">✓ Success</div>
        ${hasError ? `<div class="rtab error-tab" onclick="switchTab('${tabErrId}','${tabSuccId}','${panErrId}','${panSuccId}',this)">✕ Error</div>` : ''}
      </div>
      <div id="${panSuccId}" class="rtab-panel active">
        <div class="code-block-wrapper">
          <button class="copy-btn" onclick="copyCode(this)"><i class="fa-regular fa-copy"></i> Copy</button>
          <pre>${ep.successResponse}</pre>
        </div>
      </div>
      ${hasError ? `<div id="${panErrId}" class="rtab-panel">
        <div class="code-block-wrapper">
          <button class="copy-btn" onclick="copyCode(this)"><i class="fa-regular fa-copy"></i> Copy</button>
          <pre>${ep.errorResponse}</pre>
        </div>
      </div>` : ''}
    </div>
  </div>
</div>`;
}

function renderAll() {
  const container = document.getElementById('main-content');
  const jumpSel   = document.getElementById('group-jump');
  let totalOps = 0;

  API_GROUPS.forEach(g => {
    totalOps += g.endpoints.length;

    const opt = document.createElement('option');
    opt.value = g.id;
    opt.textContent = g.title;
    jumpSel.appendChild(opt);

    const cards = g.endpoints.map(ep => renderCard(ep, g.id)).join('');
    const section = document.createElement('div');
    section.id = `group-${g.id}`;
    section.className = 'group-section';
    section.innerHTML = `
      <div class="group-header">
        <div class="group-icon" style="background:${g.bg}; color:${g.color}">
          <i class="fa-solid ${g.icon}"></i>
        </div>
        <div>
          <div class="group-title">${g.title}</div>
          <div class="group-desc">${g.desc}</div>
        </div>
        <span class="group-count">${g.endpoints.length} operation${g.endpoints.length !== 1 ? 's' : ''}</span>
      </div>
      ${cards}`;
    container.insertBefore(section, document.getElementById('no-results'));
  });

  document.getElementById('stat-ops').textContent    = totalOps;
  document.getElementById('stat-groups').textContent = API_GROUPS.length;
}

// ── Interactions ──────────────────────────────────────────────────────────────
function toggleCard(id) {
  const card = document.getElementById(id);
  card.classList.toggle('open');
}

function switchTab(activTab, inactTab, activPan, inactPan, el) {
  document.getElementById(activPan).classList.add('active');
  document.getElementById(inactPan).classList.remove('active');
  el.classList.add('active');
  el.parentElement.querySelectorAll('.rtab').forEach(t => { if (t !== el) t.classList.remove('active'); });
}

function expandAll() {
  document.querySelectorAll('.endpoint-card:not(.hidden)').forEach(c => c.classList.add('open'));
}
function collapseAll() {
  document.querySelectorAll('.endpoint-card').forEach(c => c.classList.remove('open'));
}

function filterMethod(m) {
  activeMethod = m;
  document.querySelectorAll('.mf-btn').forEach(b => b.classList.toggle('active', b.dataset.m === m));
  applyFilters();
}

function doSearch() { applyFilters(); }

function applyFilters() {
  const q = document.getElementById('search-box').value.toLowerCase().trim();
  let visible = 0;

  document.querySelectorAll('.endpoint-card').forEach(card => {
    const method   = card.dataset.method;
    const text     = card.textContent.toLowerCase();
    const matchM   = activeMethod === 'ALL' || method === activeMethod;
    const matchQ   = !q || text.includes(q);
    const show     = matchM && matchQ;
    card.classList.toggle('hidden', !show);
    if (show) visible++;
  });

  document.querySelectorAll('.group-section').forEach(sec => {
    const anyVisible = sec.querySelectorAll('.endpoint-card:not(.hidden)').length > 0;
    sec.style.display = anyVisible ? '' : 'none';
  });

  document.getElementById('no-results').style.display = visible === 0 ? 'flex' : 'none';
}

function jumpToGroup(id) {
  if (!id) return;
  const el = document.getElementById('group-' + id);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function copyCode(btn) {
  const pre = btn.nextElementSibling;
  try {
    await navigator.clipboard.writeText(pre.textContent);
    btn.classList.add('copied');
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
    setTimeout(() => {
      btn.classList.remove('copied');
      btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy';
    }, 2000);
  } catch { /* fallback */ }
}

// ── Init ──────────────────────────────────────────────────────────────────────
renderAll();
</script>

</body>
</html>
