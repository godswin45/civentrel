<?php
require_once __DIR__ . '/../../src/bootstrap.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit;
}

$pageTitle  = 'Gemini AI Taxpayer Inquiries';
$activePage = 'treasury-ai-inquiries';
$errorMsg   = null;
$successMsg = null;

$aiService = $geminiInquiryService ?? new \App\Services\GeminiInquiryService($db ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_gemini_key') {
    $newKey = trim((string) ($_POST['gemini_api_key'] ?? ''));
    if ($aiService->saveApiKey($newKey)) {
        $successMsg = $newKey !== ''
            ? 'Google Gemini API Key saved and activated for live AI responses.'
            : 'Gemini API Key cleared. System is running on Live Database RAG Engine.';
    } else {
        $errorMsg = 'Could not save Gemini API Key.';
    }
}

$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$inquiries = $aiService->getRecentInquiries(150, $categoryFilter ?: null);
$hasApiKey = $aiService->hasApiKey();
$modelName = $aiService->getModelName();

$totalInquiries = count($inquiries);
$citizenCount   = count(array_filter($inquiries, fn($i) => ($i['sender_role'] ?? 'citizen') === 'citizen'));
$avgMs          = $totalInquiries > 0
    ? round(array_sum(array_column($inquiries, 'response_ms')) / $totalInquiries)
    : 0;

$categories = [
    'Real Property Tax (RPT)',
    'Business Tax & Permits',
    'Market Stall Rental',
    'Online Payment & Receipts',
    'Cedula & Miscellaneous Fees',
    'General Tax Inquiry',
];

$basePath = '../../';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
    <main class="flex-1 p-6 md:p-8 w-full space-y-6 overflow-y-auto">

      <!-- Breadcrumb -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/60 pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-slate-400">
            <span>Revenue Collection &amp; Treasury</span>
            <i class="fa-solid fa-chevron-right text-[8px] opacity-60"></i>
            <span class="text-brand-dark">Gemini AI Taxpayer Inquiries</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2 mt-4">
            <i class="fa-solid fa-wand-magic-sparkles text-brand-dark"></i>
            Gemini AI-Powered Taxpayer Inquiry Assistant
          </h1>
          <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">
            Bilingual (English &amp; Tagalog) AI assistant grounded on live Caloocan City Treasury assessments, market stalls, and Local Government Code (RA 7160) tax computations.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="../../taxpayer-inquiry.php" target="_blank"
            class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold px-3.5 py-2 rounded-xl text-xs transition shadow-2xs">
            <i class="fa-solid fa-arrow-up-right-from-square text-brand-dark"></i>
            <span>Open Citizen Inquiry Portal</span>
          </a>
          <button type="button" onclick="document.getElementById('geminiKeyModal').classList.remove('hidden')"
            class="inline-flex items-center gap-2 bg-brand-dark hover:opacity-90 text-white font-bold px-3.5 py-2 rounded-xl text-xs transition shadow-2xs cursor-pointer">
            <i class="fa-solid fa-key"></i>
            <span>Configure Gemini API Key</span>
          </button>
        </div>
      </div>

      <?php if ($successMsg): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 text-xs font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($successMsg) ?></span></div>
        <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-xs font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($errorMsg) ?></span>
      </div>
      <?php endif; ?>

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-brand-dark"></div>
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total AI Inquiries Logged</span>
          <h3 class="text-2xl font-black text-slate-900 mt-1"><?= $totalInquiries ?></h3>
          <p class="text-[11px] text-slate-500 font-semibold mt-1"><?= $citizenCount ?> citizen · <?= $totalInquiries - $citizenCount ?> staff queries</p>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">AI Engine &amp; RAG Status</span>
          <h3 class="text-base font-black text-emerald-700 mt-1 flex items-center gap-1.5">
            <i class="fa-solid fa-circle-check text-xs"></i>
            <?= $hasApiKey ? htmlspecialchars($modelName) . ' + Live RAG' : 'Live DB RAG Active' ?>
          </h3>
          <p class="text-[11px] text-slate-500 font-semibold mt-1"><?= $hasApiKey ? 'Connected to Google Gemini API' : 'Grounded on MySQL Treasury Tables' ?></p>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Avg Response Latency</span>
          <h3 class="text-2xl font-black text-slate-900 font-mono mt-1"><?= $avgMs ?> ms</h3>
          <p class="text-[11px] text-amber-600 font-semibold mt-1">Real-time database lookup</p>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs relative overflow-hidden">
          <div class="absolute top-0 left-0 w-1.5 h-full bg-indigo-500"></div>
          <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Supported Languages</span>
          <h3 class="text-lg font-black text-slate-900 mt-1">English &amp; Tagalog</h3>
          <p class="text-[11px] text-indigo-600 font-semibold mt-1">Auto-detects taxpayer language</p>
        </div>
      </div>

      <!-- Interactive Live Gemini Test Console + Inquiry Log -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Interactive Inquiry Simulator -->
        <div class="lg:col-span-5 bg-white border border-slate-200/80 rounded-2xl shadow-xs flex flex-col overflow-hidden">
          <div class="p-4 border-b border-slate-100 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <i class="fa-solid fa-wand-magic-sparkles text-amber-300"></i>
              <div>
                <h2 class="text-xs font-black uppercase tracking-wider">Interactive Taxpayer Inquiry Console</h2>
                <p class="text-[10px] text-slate-400">Test live Citizen &amp; Staff questions grounded on MySQL data</p>
              </div>
            </div>
            <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[9px] font-bold">Live DB</span>
          </div>

          <div class="p-3 bg-slate-50 border-b border-slate-100 flex flex-wrap gap-1.5">
            <button type="button" onclick="runConsolePrompt('Magkano po ang babayaran ko sa RPT-2026-001 at bakit may surcharge?')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-brand-light border border-slate-200 text-[10px] font-bold text-slate-700 transition cursor-pointer">
              RPT-2026-001 (Tagalog)
            </button>
            <button type="button" onclick="runConsolePrompt('What is the breakdown and status of BIZ-2026-001?')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-brand-light border border-slate-200 text-[10px] font-bold text-slate-700 transition cursor-pointer">
              BIZ-2026-001 (English)
            </button>
            <button type="button" onclick="runConsolePrompt('Which market stalls are vacant and what is the monthly rental for A-03?')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-brand-light border border-slate-200 text-[10px] font-bold text-slate-700 transition cursor-pointer">
              Market Stalls
            </button>
            <button type="button" onclick="runConsolePrompt('Compute surcharge and interest for 25000')" class="px-2.5 py-1 rounded-lg bg-white hover:bg-brand-light border border-slate-200 text-[10px] font-bold text-slate-700 transition cursor-pointer">
              Compute ₱25,000 Penalty
            </button>
          </div>

          <div id="console-chat-box" class="flex-1 p-4 space-y-3 overflow-y-auto bg-slate-50/40" style="min-height: 320px; max-height: 420px;">
            <div class="bg-white border border-slate-200/80 rounded-xl p-3.5 text-xs text-slate-700 space-y-1">
              <p class="font-bold text-slate-900"><i class="fa-solid fa-robot text-brand-dark mr-1.5"></i>Gemini AI Assistant Ready</p>
              <p class="text-[11px] text-slate-500">Click any sample prompt above or type any question in English or Tagalog below to test live Retrieval-Augmented Generation (RAG).</p>
            </div>
          </div>

          <form id="console-chat-form" onsubmit="submitConsolePrompt(event)" class="p-3 bg-white border-t border-slate-200 flex items-center gap-2">
            <input type="text" id="console-chat-input" placeholder="Ask about an assessment, market stall, or tax rule..." required
              class="flex-1 px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:bg-white focus:border-brand-medium">
            <button type="submit" id="console-chat-btn" class="px-4 py-2 bg-brand-dark hover:opacity-90 text-white font-bold rounded-xl text-xs transition cursor-pointer">
              Ask AI
            </button>
          </form>
        </div>

        <!-- Right: Logged Taxpayer Inquiries Table -->
        <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-2xl shadow-xs flex flex-col overflow-hidden">
          <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h2 class="text-sm font-extrabold text-slate-800">Citizen &amp; Staff AI Inquiry Audit Log (<code class="font-mono text-xs text-brand-dark">tr_ai_inquiries</code>)</h2>
              <p class="text-[11px] text-slate-400 mt-0.5">Every taxpayer question, matched assessment/stall reference, and AI response</p>
            </div>
            <form method="get" class="flex items-center gap-2">
              <select name="category" onchange="this.form.submit()" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>

          <div class="overflow-x-auto max-h-[500px] overflow-y-auto divide-y divide-slate-100">
            <?php foreach ($inquiries as $inq): ?>
            <div class="p-4 hover:bg-slate-50/70 transition space-y-2">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= ($inq['sender_role'] ?? '') === 'employee' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' ?>">
                    <?= htmlspecialchars($inq['sender_role'] ?? 'citizen') ?>
                  </span>
                  <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($inq['sender_name'] ?? 'Taxpayer') ?></span>
                  <?php if (!empty($inq['citizen_id'])): ?>
                  <span class="text-[10px] font-mono text-slate-400">(<?= htmlspecialchars($inq['citizen_id']) ?>)</span>
                  <?php endif; ?>
                  <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold"><?= htmlspecialchars($inq['category'] ?? '') ?></span>
                  <?php if (!empty($inq['matched_reference'])): ?>
                  <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700 font-mono text-[10px] font-bold">Ref: <?= htmlspecialchars($inq['matched_reference']) ?></span>
                  <?php endif; ?>
                </div>
                <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($inq['created_at'] ?? '') ?> · <?= (int) ($inq['response_ms'] ?? 0) ?>ms</span>
              </div>
              <div class="bg-slate-50 border border-slate-200/60 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-800">
                <span class="text-brand-dark font-black mr-1">Q:</span> <?= htmlspecialchars($inq['question'] ?? '') ?>
              </div>
              <div class="pl-3 border-l-2 border-brand-medium text-xs text-slate-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($inq['answer'] ?? '') ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </main>

    <!-- Configure Gemini API Key Modal -->
    <div id="geminiKeyModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 hidden">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full mx-4 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-black text-slate-900"><i class="fa-solid fa-key text-brand-dark mr-1.5"></i>Configure Google Gemini API Key</h3>
          <button type="button" onclick="document.getElementById('geminiKeyModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed">
          Enter your Google AI Studio <code class="font-mono bg-slate-100 px-1 rounded">GEMINI_API_KEY</code> to enable live cloud generation with <code class="font-mono bg-slate-100 px-1 rounded">gemini-2.0-flash</code>. Even without an external key, the system automatically answers using its built-in <strong>Live MySQL Database RAG Engine</strong>.
        </p>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="save_gemini_key">
          <div>
            <label class="text-[11px] font-bold text-slate-600 block mb-1">Gemini API Key (AIza...)</label>
            <input type="password" name="gemini_api_key" placeholder="AIzaSy..."
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-brand-medium">
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="document.getElementById('geminiKeyModal').classList.add('hidden')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold">Cancel</button>
            <button type="submit" class="px-4 py-2 rounded-xl bg-brand-dark text-white text-xs font-bold">Save Key</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    function runConsolePrompt(text) {
      const inp = document.getElementById('console-chat-input');
      if (!inp) return;
      inp.value = text;
      document.getElementById('console-chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
    }

    async function submitConsolePrompt(e) {
      e.preventDefault();
      const inp = document.getElementById('console-chat-input');
      const box = document.getElementById('console-chat-box');
      const btn = document.getElementById('console-chat-btn');
      const q = inp.value.trim();
      if (!q) return;
      inp.value = '';
      btn.disabled = true;

      const u = document.createElement('div');
      u.className = 'bg-brand-dark text-white rounded-xl p-3 text-xs font-semibold ml-8';
      u.textContent = q;
      box.appendChild(u);

      try {
        const res = await fetch('../../api/citizen/treasury/inquiry.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ question: q })
        });
        const payload = await res.json();
        const ans = payload?.data?.answer || payload?.message || 'Error';
        const ref = payload?.data?.matched_reference;
        const a = document.createElement('div');
        a.className = 'bg-white border border-slate-200 rounded-xl p-3.5 text-xs text-slate-700 mr-4 space-y-1.5 whitespace-pre-line';
        a.innerHTML = (ref ? `<div class="text-[10px] font-mono font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded inline-block">Matched Record: ${ref}</div>` : '')
          + `<div></div>`;
        a.lastChild.textContent = ans;
        box.appendChild(a);
        box.scrollTop = box.scrollHeight;
      } catch (err) {
        alert('Error: ' + err.message);
      } finally {
        btn.disabled = false;
      }
    }
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
