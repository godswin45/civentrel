<?php
/**
 * Caloocan City Hall — Public & Citizen Taxpayer Inquiry & Assessment Portal
 * Powered by Gemini AI + Live Treasury Database RAG
 */
$basePath = '';
require_once __DIR__ . '/src/bootstrap.php';

$assessments = [];
$stalls      = [];
try {
    if (isset($treasuryService) && $treasuryService) {
        $assessments = $treasuryService->getAllTaxAssessments();
        $stalls      = $treasuryService->getAllMarketStalls();
    }
} catch (\Throwable $e) {
    $assessments = [];
    $stalls      = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Citizen Tax Inquiry &amp; Gemini AI Assistant – Caloocan City Treasury</title>
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style type="text/tailwindcss">
  @theme {
    --color-brand-light: #EEF5FF;
    --color-brand-border: #B4D4FF;
    --color-brand-medium: #86B6F6;
    --color-brand-dark: #176B87;
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800">

  <!-- Top Header -->
  <header class="bg-slate-900 text-white border-b border-slate-800 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <img src="assets/images/logo.png" alt="Civentral" class="h-9 w-auto">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-sm font-black tracking-wider uppercase text-white">Civentral Citizen Tax Portal</span>
            <span class="px-2 py-0.5 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30 text-[10px] font-bold">Gemini AI Powered</span>
          </div>
          <p class="text-[11px] text-slate-400">Office of the City Treasurer · Caloocan City Hall</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <a href="login.php" class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20 text-white border border-white/15 px-3.5 py-2 rounded-xl text-xs font-bold transition">
          <i class="fa-solid fa-user-lock text-brand-medium"></i>
          <span>Employee Portal Login</span>
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">

    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-[#0f172a] via-[#176B87] to-[#0f172a] rounded-2xl p-6 sm:p-8 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-6">
      <div class="space-y-2 max-w-2xl">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 text-amber-300 text-[10px] font-black uppercase tracking-wider">
          <i class="fa-solid fa-wand-magic-sparkles"></i> 24/7 Intelligent Citizen Tax Assistance
        </span>
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Gemini AI Taxpayer Inquiry &amp; Assessment Lookup</h1>
        <p class="text-xs sm:text-sm text-slate-200 leading-relaxed">
          Ask questions in <strong>English</strong> or <strong>Tagalog</strong> about Real Property Tax (Amilyar), Business Permit Renewal, Public Market Stall Rentals, or look up your live Tax Assessment breakdown below.
        </p>
      </div>
      <div class="bg-white/10 border border-white/15 rounded-2xl p-4 shrink-0 space-y-2 min-w-[230px]">
        <div class="text-[10px] font-black uppercase tracking-wider text-slate-300">Sample Reference Codes</div>
        <div class="space-y-1 text-xs font-mono">
          <div class="flex justify-between"><span>RPT Assessment:</span><strong class="text-amber-300">RPT-2026-001</strong></div>
          <div class="flex justify-between"><span>Business Tax:</span><strong class="text-amber-300">BIZ-2026-001</strong></div>
          <div class="flex justify-between"><span>Market Stall:</span><strong class="text-emerald-300">A-03 / B-01</strong></div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- Left: Gemini AI Chat Assistant -->
      <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl shadow-xs flex flex-col overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
          <div class="flex items-center gap-2.5">
            <div class="h-9 w-9 rounded-xl bg-brand-dark text-white flex items-center justify-center">
              <i class="fa-solid fa-robot"></i>
            </div>
            <div>
              <h2 class="text-sm font-black text-slate-900">Ask Gemini Tax AI (English / Tagalog)</h2>
              <p class="text-[11px] text-slate-500">Connected to live Caloocan City Treasury Assessment &amp; Stall Ledgers</p>
            </div>
          </div>
          <select id="portal-lang" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700">
            <option value="auto">Auto (EN / Tagalog)</option>
            <option value="en">English</option>
            <option value="tl">Tagalog</option>
          </select>
        </div>

        <!-- Prompt Chips -->
        <div class="px-4 py-2.5 bg-slate-50/70 border-b border-slate-100 flex flex-wrap gap-1.5">
          <button type="button" onclick="askPortalAi('Magkano po ang babayaran ko sa RPT-2026-001 at bakit may surcharge?')" class="px-2.5 py-1 rounded-full bg-white hover:bg-brand-light border border-slate-200 text-[11px] font-bold text-slate-700 transition cursor-pointer">
            🇵🇭 Magkano ang RPT-2026-001?
          </button>
          <button type="button" onclick="askPortalAi('What is the itemized breakdown and status of BIZ-2026-001?')" class="px-2.5 py-1 rounded-full bg-white hover:bg-brand-light border border-slate-200 text-[11px] font-bold text-slate-700 transition cursor-pointer">
            🏢 Check BIZ-2026-001
          </button>
          <button type="button" onclick="askPortalAi('Anong market stall ang bakante at magkano ang monthly fee sa A-03?')" class="px-2.5 py-1 rounded-full bg-white hover:bg-brand-light border border-slate-200 text-[11px] font-bold text-slate-700 transition cursor-pointer">
            🏪 Market Stall A-03
          </button>
          <button type="button" onclick="askPortalAi('Compute 25% surcharge and 2% monthly interest for 15000')" class="px-2.5 py-1 rounded-full bg-white hover:bg-brand-light border border-slate-200 text-[11px] font-bold text-slate-700 transition cursor-pointer">
            🧮 Compute ₱15,000 Penalty
          </button>
        </div>

        <div id="portal-chat-messages" class="flex-1 p-5 space-y-4 overflow-y-auto bg-slate-50/30" style="min-height: 360px; max-height: 460px;">
          <div class="flex items-start gap-3">
            <div class="h-8 w-8 rounded-xl bg-brand-dark text-white flex items-center justify-center shrink-0 text-xs">
              <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl rounded-tl-xs p-4 text-xs text-slate-700 shadow-2xs space-y-1.5 leading-relaxed">
              <p class="font-bold text-slate-900">Mabuhay! Welcome to the Caloocan City Treasury AI Inquiry Desk.</p>
              <p>You can ask me any question about your <strong>Real Property Tax (RPT)</strong>, <strong>Business Tax &amp; Mayor's Permit</strong>, <strong>Public Market Stall Rental</strong>, or <strong>Online Payment (GCash/Maya)</strong>—or click any assessment on the right to inspect your exact breakdown.</p>
            </div>
          </div>
        </div>

        <form id="portal-chat-form" onsubmit="submitPortalAi(event)" class="p-3.5 bg-white border-t border-slate-200 flex flex-col sm:flex-row gap-2">
          <input type="text" id="portal-taxpayer-name" placeholder="Your Name / Citizen ID (optional)"
            class="sm:w-44 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:bg-white focus:border-brand-medium">
          <div class="flex-1 flex gap-2">
            <input type="text" id="portal-chat-input" placeholder="Type your question in English or Tagalog..." required
              class="flex-1 px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:bg-white focus:border-brand-medium">
            <button type="submit" id="portal-chat-send" class="px-4 py-2 bg-brand-dark hover:opacity-90 text-white font-bold rounded-xl text-xs transition cursor-pointer shrink-0">
              <i class="fa-solid fa-paper-plane mr-1"></i> Ask AI
            </button>
          </div>
        </form>
      </div>

      <!-- Right: Live Assessment & Market Stall Lookup -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
          <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <div>
              <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Quick Assessment Lookup</h3>
              <p class="text-[11px] text-slate-500">Click any assessment to ask Gemini AI for its full breakdown</p>
            </div>
            <span class="text-[10px] font-bold text-brand-dark bg-brand-light px-2 py-0.5 rounded-full"><?= count($assessments) ?> records</span>
          </div>
          <div class="divide-y divide-slate-100 max-h-[290px] overflow-y-auto">
            <?php foreach ($assessments as $a):
              $tot = (float) ($a['basic_amount'] ?? 0) + (float) ($a['surcharge_amount'] ?? 0) + (float) ($a['penalty_amount'] ?? 0);
              $st  = strtolower((string) ($a['status'] ?? 'unpaid'));
            ?>
            <button type="button" onclick="askPortalAi('Please explain the full breakdown and payment status for assessment <?= htmlspecialchars($a['assessment_no']) ?>')"
              class="w-full text-left p-3.5 hover:bg-brand-light/40 transition flex items-center justify-between gap-3 cursor-pointer">
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-black text-brand-dark"><?= htmlspecialchars($a['assessment_no']) ?></span>
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase <?= $st === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($st === 'delinquent' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                    <?= htmlspecialchars($st) ?>
                  </span>
                </div>
                <p class="text-xs font-semibold text-slate-700 mt-0.5"><?= htmlspecialchars($a['taxpayer_name']) ?></p>
                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($a['tax_type']) ?> · Due: <?= htmlspecialchars($a['due_date'] ?? '') ?></p>
              </div>
              <div class="text-right">
                <div class="font-mono text-xs font-black text-slate-900">₱<?= number_format($tot, 2) ?></div>
                <span class="text-[10px] text-brand-dark font-bold">Ask AI &rarr;</span>
              </div>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
          <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <div>
              <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Public Market Stall Directory</h3>
              <p class="text-[11px] text-slate-500">Click any stall to check rental rate &amp; cutoff rules</p>
            </div>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full"><?= count($stalls) ?> stalls</span>
          </div>
          <div class="divide-y divide-slate-100 max-h-[220px] overflow-y-auto">
            <?php foreach ($stalls as $s):
              $occ = strtolower((string) ($s['occupancy_status'] ?? 'vacant'));
            ?>
            <button type="button" onclick="askPortalAi('Check market stall <?= htmlspecialchars($s['stall_number']) ?> occupancy, vendor, and monthly rental fee')"
              class="w-full text-left p-3 hover:bg-brand-light/40 transition flex items-center justify-between gap-3 cursor-pointer">
              <div>
                <span class="font-mono text-xs font-black text-brand-dark"><?= htmlspecialchars($s['stall_number']) ?></span>
                <span class="text-xs font-semibold text-slate-700 ml-1.5"><?= htmlspecialchars($s['section']) ?></span>
                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($s['vendor_name'] ?: 'Vacant — Available for Application') ?></p>
              </div>
              <div class="text-right">
                <div class="font-mono text-xs font-bold text-slate-800">₱<?= number_format((float) ($s['monthly_rate'] ?? 0), 2) ?>/mo</div>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase <?= $occ === 'occupied' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                  <?= htmlspecialchars($occ) ?>
                </span>
              </div>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>
  </main>

  <script>
  const portalHistory = [];

  function formatPortalMd(md) {
    let safe = String(md || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    safe = safe.replace(/^### (.*)$/gm, '<div class="font-black text-slate-900 text-xs mt-1.5 mb-1">$1</div>');
    safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
    safe = safe.replace(/`([^`]+)`/g, '<code class="font-mono bg-slate-100 text-brand-dark px-1 py-0.5 rounded text-[10px]">$1</code>');
    safe = safe.replace(/^- (.*)$/gm, '<div class="flex items-start gap-1.5 ml-1"><span class="text-brand-dark font-bold">•</span><span>$1</span></div>');
    safe = safe.replace(/\n/g, '<br>');
    return safe;
  }

  function askPortalAi(text) {
    const inp = document.getElementById('portal-chat-input');
    inp.value = text;
    document.getElementById('portal-chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
  }

  async function submitPortalAi(e) {
    e.preventDefault();
    const inp = document.getElementById('portal-chat-input');
    const nameInp = document.getElementById('portal-taxpayer-name');
    const langSel = document.getElementById('portal-lang');
    const box = document.getElementById('portal-chat-messages');
    const btn = document.getElementById('portal-chat-send');

    const q = inp.value.trim();
    if (!q) return;
    inp.value = '';
    btn.disabled = true;

    const u = document.createElement('div');
    u.className = 'flex justify-end';
    u.innerHTML = `<div class="bg-brand-dark text-white rounded-2xl rounded-tr-xs px-4 py-2.5 text-xs max-w-[85%] shadow-2xs"></div>`;
    u.firstChild.textContent = q;
    box.appendChild(u);
    box.scrollTop = box.scrollHeight;

    try {
      const res = await fetch('api/citizen/treasury/inquiry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          question: q,
          sender_name: nameInp.value.trim() || 'Citizen Taxpayer',
          sender_role: 'citizen',
          language: langSel.value,
          history: portalHistory.slice(-6)
        })
      });
      const payload = await res.json();
      const ans = payload?.data?.answer || payload?.message || 'Unable to process inquiry.';
      const model = payload?.data?.model_used || 'Gemini AI';
      const ms = payload?.data?.response_ms || 0;

      portalHistory.push({ role: 'user', content: q });
      portalHistory.push({ role: 'assistant', content: ans });

      const a = document.createElement('div');
      a.className = 'flex items-start gap-3';
      a.innerHTML = `
        <div class="h-8 w-8 rounded-xl bg-brand-dark text-white flex items-center justify-center shrink-0 text-xs">
          <i class="fa-solid fa-robot"></i>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl rounded-tl-xs p-4 text-xs text-slate-700 shadow-2xs max-w-[88%] space-y-2 leading-relaxed">
          <div>${formatPortalMd(ans)}</div>
          <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-mono">
            <span>${model}</span>
            <span>${ms}ms</span>
          </div>
        </div>`;
      box.appendChild(a);
      box.scrollTop = box.scrollHeight;
    } catch (err) {
      alert('Error: ' + err.message);
    } finally {
      btn.disabled = false;
    }
  }
  </script>
</body>
</html>
