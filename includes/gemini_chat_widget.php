<?php
/**
 * Floating Gemini AI Taxpayer & Treasury Inquiry Assistant Widget
 * Included globally via `includes/footer.php` and on `taxpayer-inquiry.php`.
 */
$widgetBasePath = $basePath ?? '';
$widgetEndpoint = rtrim($widgetBasePath, '/') . ($widgetBasePath !== '' ? '/' : '') . 'api/citizen/treasury/inquiry.php';
$widgetSenderRole = (!empty($_SESSION['user_id']) || !empty($_SESSION['employee_id'])) ? 'employee' : 'citizen';
$widgetSenderName = $headerUser['full_name'] ?? ($_SESSION['citizen_name'] ?? ($widgetSenderRole === 'employee' ? 'Treasury Staff' : 'Citizen Taxpayer'));
?>
<!-- ═══════ GEMINI AI TAXPAYER INQUIRY ASSISTANT WIDGET ═══════ -->
<div id="civ-gemini-widget" class="fixed bottom-5 right-5 z-50 print:hidden font-sans">
  <!-- Chat Window -->
  <div id="civ-gemini-panel" class="hidden mb-3 w-[360px] sm:w-[410px] bg-white rounded-2xl shadow-2xl border border-slate-200/90 overflow-hidden flex flex-col" style="max-height: min(610px, calc(100vh - 110px));">
    <!-- Header -->
    <div class="bg-gradient-to-r from-[#0f172a] via-[#176B87] to-[#0f172a] text-white px-4 py-3.5 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="h-9 w-9 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-wand-magic-sparkles text-amber-300 text-sm"></i>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-1.5">
            <h3 class="text-xs font-black tracking-wide truncate">Gemini AI Tax Assistant</h3>
            <span class="px-1.5 py-0.5 rounded-full bg-emerald-400/20 text-emerald-300 border border-emerald-400/30 text-[9px] font-black uppercase">Live RAG</span>
          </div>
          <p class="text-[10px] text-slate-300 truncate">Caloocan City Revenue &amp; Treasury Inquiry</p>
        </div>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <select id="civ-gemini-lang" class="bg-white/10 text-white border border-white/20 rounded-lg px-2 py-1 text-[10px] font-bold focus:outline-none cursor-pointer" title="Response Language">
          <option value="auto" class="text-slate-900">Auto (EN/TL)</option>
          <option value="en" class="text-slate-900">English</option>
          <option value="tl" class="text-slate-900">Tagalog</option>
        </select>
        <button type="button" onclick="toggleCivGeminiPanel(false)" class="h-7 w-7 rounded-lg hover:bg-white/15 flex items-center justify-center text-white/80 hover:text-white transition" title="Minimize">
          <i class="fa-solid fa-chevron-down text-xs"></i>
        </button>
      </div>
    </div>

    <!-- Quick Prompt Chips -->
    <div class="px-3.5 py-2 bg-slate-50 border-b border-slate-200/70 flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0">
      <button type="button" onclick="sendCivGeminiQuick('Check assessment RPT-2026-001')" class="shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-brand-light text-slate-700 border border-slate-200 text-[10px] font-bold transition cursor-pointer">
        <i class="fa-solid fa-house-chimney text-brand-dark mr-1"></i>RPT-2026-001
      </button>
      <button type="button" onclick="sendCivGeminiQuick('Magkano po ang babayaran sa BIZ-2026-001?')" class="shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-brand-light text-slate-700 border border-slate-200 text-[10px] font-bold transition cursor-pointer">
        <i class="fa-solid fa-briefcase text-amber-600 mr-1"></i>BIZ-2026-001 (TL)
      </button>
      <button type="button" onclick="sendCivGeminiQuick('Check market stall A-03 occupancy and cutoff')" class="shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-brand-light text-slate-700 border border-slate-200 text-[10px] font-bold transition cursor-pointer">
        <i class="fa-solid fa-store text-emerald-600 mr-1"></i>Stall A-03
      </button>
      <button type="button" onclick="sendCivGeminiQuick('Compute 25% surcharge and 2% interest for 15000')" class="shrink-0 px-2.5 py-1 rounded-full bg-white hover:bg-brand-light text-slate-700 border border-slate-200 text-[10px] font-bold transition cursor-pointer">
        <i class="fa-solid fa-calculator text-indigo-600 mr-1"></i>Compute Penalty
      </button>
    </div>

    <!-- Messages Container -->
    <div id="civ-gemini-messages" class="flex-1 p-4 space-y-3 overflow-y-auto bg-slate-50/40" style="min-height: 280px; max-height: 370px;">
      <div class="flex items-start gap-2.5">
        <div class="h-7 w-7 rounded-lg bg-brand-dark text-white flex items-center justify-center shrink-0 mt-0.5 text-xs">
          <i class="fa-solid fa-robot"></i>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs px-3.5 py-2.5 text-xs text-slate-700 shadow-2xs space-y-1.5 leading-relaxed">
          <p class="font-bold text-slate-900">Mabuhay! I am the Caloocan Treasury Gemini AI Assistant.</p>
          <p class="text-[11px] text-slate-600">Ask me in <strong>English</strong> or <strong>Tagalog</strong> about Real Property Tax (RPT), Business Permit renewal, Market Stall rentals, or type an Assessment No. (e.g., <code class="font-mono bg-slate-100 px-1 rounded">RPT-2026-001</code>) to check live balances.</p>
        </div>
      </div>
    </div>

    <!-- Input Form -->
    <form id="civ-gemini-form" onsubmit="submitCivGeminiChat(event)" class="p-3 bg-white border-t border-slate-200/80 flex items-center gap-2 shrink-0">
      <input type="text" id="civ-gemini-input" placeholder="Ask in English or Tagalog (e.g. Check RPT-2026-001)..." autocomplete="off"
        class="flex-1 px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-brand-medium">
      <button type="submit" id="civ-gemini-send" class="h-9 w-9 rounded-xl bg-brand-dark hover:opacity-90 text-white flex items-center justify-center shrink-0 transition cursor-pointer">
        <i class="fa-solid fa-paper-plane text-xs"></i>
      </button>
    </form>
  </div>

  <!-- Floating Trigger Button -->
  <div class="flex justify-end">
    <button type="button" onclick="toggleCivGeminiPanel()" id="civ-gemini-fab"
      class="group flex items-center gap-2.5 bg-gradient-to-r from-[#0f172a] to-[#176B87] hover:from-[#176B87] hover:to-[#0f172a] text-white px-4 py-3 rounded-full shadow-xl border border-white/15 transition-all duration-200 cursor-pointer">
      <span class="relative flex h-6 w-6 items-center justify-center rounded-full bg-amber-400/20 text-amber-300">
        <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-30"></span>
      </span>
      <span class="text-xs font-extrabold tracking-wide">Ask Gemini Tax AI</span>
    </button>
  </div>
</div>

<script>
(function () {
  const endpoint = <?= json_encode($widgetEndpoint) ?>;
  const senderRole = <?= json_encode($widgetSenderRole) ?>;
  const senderName = <?= json_encode($widgetSenderName) ?>;
  const chatHistory = [];

  function formatAiMarkdown(md) {
    let safe = String(md || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    safe = safe.replace(/^### (.*)$/gm, '<div class="font-black text-slate-900 text-xs mt-1.5 mb-1">$1</div>');
    safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>');
    safe = safe.replace(/`([^`]+)`/g, '<code class="font-mono bg-slate-100 text-brand-dark px-1 py-0.5 rounded text-[10px]">$1</code>');
    safe = safe.replace(/^- (.*)$/gm, '<div class="flex items-start gap-1.5 ml-1"><span class="text-brand-dark font-bold">•</span><span>$1</span></div>');
    safe = safe.replace(/\n/g, '<br>');
    return safe;
  }

  window.toggleCivGeminiPanel = function (forceState) {
    const panel = document.getElementById('civ-gemini-panel');
    if (!panel) return;
    const isHidden = panel.classList.contains('hidden');
    const show = typeof forceState === 'boolean' ? forceState : isHidden;
    panel.classList.toggle('hidden', !show);
    if (show) {
      setTimeout(() => document.getElementById('civ-gemini-input')?.focus(), 80);
    }
  };

  window.sendCivGeminiQuick = function (text) {
    const input = document.getElementById('civ-gemini-input');
    if (!input) return;
    input.value = text;
    toggleCivGeminiPanel(true);
    document.getElementById('civ-gemini-form')?.dispatchEvent(new Event('submit', { cancelable: true }));
  };

  window.submitCivGeminiChat = async function (e) {
    e.preventDefault();
    const input = document.getElementById('civ-gemini-input');
    const msgBox = document.getElementById('civ-gemini-messages');
    const langSel = document.getElementById('civ-gemini-lang');
    const sendBtn = document.getElementById('civ-gemini-send');
    if (!input || !msgBox) return;

    const question = input.value.trim();
    if (!question) return;
    input.value = '';

    // Append user bubble
    const userDiv = document.createElement('div');
    userDiv.className = 'flex justify-end';
    userDiv.innerHTML = `<div class="bg-brand-dark text-white rounded-2xl rounded-tr-xs px-3.5 py-2 text-xs max-w-[85%] shadow-2xs leading-relaxed"></div>`;
    userDiv.firstChild.textContent = question;
    msgBox.appendChild(userDiv);

    // Append typing indicator
    const typingDiv = document.createElement('div');
    typingDiv.className = 'flex items-start gap-2.5';
    typingDiv.innerHTML = `
      <div class="h-7 w-7 rounded-lg bg-brand-dark text-white flex items-center justify-center shrink-0 mt-0.5 text-xs">
        <i class="fa-solid fa-wand-magic-sparkles animate-spin"></i>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs px-3.5 py-2.5 text-[11px] text-slate-400 font-semibold">
        Analyzing live Treasury records...
      </div>`;
    msgBox.appendChild(typingDiv);
    msgBox.scrollTop = msgBox.scrollHeight;

    if (sendBtn) sendBtn.disabled = true;

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          question: question,
          language: langSel ? langSel.value : 'auto',
          sender_role: senderRole,
          sender_name: senderName,
          history: chatHistory.slice(-6)
        })
      });
      const payload = await res.json();
      typingDiv.remove();

      const answer = payload?.data?.answer || payload?.message || 'Unable to retrieve response.';
      const modelUsed = payload?.data?.model_used || 'Gemini AI';
      const ms = payload?.data?.response_ms || 0;

      chatHistory.push({ role: 'user', content: question });
      chatHistory.push({ role: 'assistant', content: answer });

      const aiDiv = document.createElement('div');
      aiDiv.className = 'flex items-start gap-2.5';
      aiDiv.innerHTML = `
        <div class="h-7 w-7 rounded-lg bg-brand-dark text-white flex items-center justify-center shrink-0 mt-0.5 text-xs">
          <i class="fa-solid fa-robot"></i>
        </div>
        <div class="bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs px-3.5 py-2.5 text-xs text-slate-700 shadow-2xs max-w-[88%] space-y-1.5 leading-relaxed">
          <div>${formatAiMarkdown(answer)}</div>
          <div class="pt-1 border-t border-slate-100 flex items-center justify-between text-[9px] text-slate-400 font-mono">
            <span>${modelUsed}</span>
            <span>${ms}ms</span>
          </div>
        </div>`;
      msgBox.appendChild(aiDiv);
      msgBox.scrollTop = msgBox.scrollHeight;
    } catch (err) {
      typingDiv.remove();
      const errDiv = document.createElement('div');
      errDiv.className = 'text-[11px] text-red-600 bg-red-50 border border-red-200 rounded-xl px-3 py-2';
      errDiv.textContent = 'Connection error: ' + err.message;
      msgBox.appendChild(errDiv);
    } finally {
      if (sendBtn) sendBtn.disabled = false;
    }
  };
})();
</script>
