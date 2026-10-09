/**
 * CIVENTRAL — Global form UX
 * ------------------------------------------------------------------
 * 1. Confirmation dialog: add  data-confirm="Message"  to any <form>
 *    (optional: data-confirm-title, data-confirm-ok, data-confirm-variant="danger|success|primary")
 *    and the user must confirm before it submits.
 * 2. Loading state: every normal POST form disables its submit button(s)
 *    and shows a spinner after submit, preventing double submissions
 *    (e.g. duplicate collections or double approvals).
 *    Opt out per form with  data-no-loading.
 *
 * Forms whose own JS calls event.preventDefault() (AJAX forms) are left alone.
 */
(function () {
  'use strict';

  // ── Confirmation dialog ───────────────────────────────────────────────
  const VARIANTS = {
    success: { btn: 'bg-emerald-600 hover:bg-emerald-700', icon: 'fa-circle-check', iconWrap: 'bg-emerald-100 text-emerald-600' },
    danger:  { btn: 'bg-red-600 hover:bg-red-700',         icon: 'fa-triangle-exclamation', iconWrap: 'bg-red-100 text-red-600' },
    primary: { btn: 'bg-teal-600 hover:bg-teal-700',       icon: 'fa-circle-question', iconWrap: 'bg-teal-100 text-teal-600' },
  };

  let dialog = null;

  function buildDialog() {
    dialog = document.createElement('dialog');
    dialog.id = 'civConfirmDialog';
    dialog.className = 'civ-confirm rounded-2xl p-0 shadow-2xl border border-slate-200 w-[92vw] max-w-md';
    dialog.innerHTML =
      '<form method="dialog" class="p-6">' +
        '<div class="flex items-start gap-4">' +
          '<div id="civConfirmIconWrap" class="h-11 w-11 rounded-full flex items-center justify-center flex-shrink-0">' +
            '<i id="civConfirmIcon" class="fa-solid text-lg"></i>' +
          '</div>' +
          '<div class="flex-1 min-w-0">' +
            '<h3 id="civConfirmTitle" class="text-base font-extrabold text-slate-900"></h3>' +
            '<p id="civConfirmMsg" class="mt-1.5 text-sm text-slate-500 leading-relaxed whitespace-pre-line"></p>' +
          '</div>' +
        '</div>' +
        '<div class="mt-6 flex justify-end gap-2">' +
          '<button value="cancel" id="civConfirmCancel" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancel</button>' +
          '<button value="ok" id="civConfirmOk" class="px-4 py-2 text-sm font-bold text-white rounded-lg shadow-sm transition"></button>' +
        '</div>' +
      '</form>';
    document.body.appendChild(dialog);

    // Close when clicking the backdrop
    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) dialog.close('cancel');
    });
  }

  function civConfirm(opts) {
    if (!dialog) buildDialog();
    const v = VARIANTS[opts.variant] || VARIANTS.primary;

    dialog.querySelector('#civConfirmTitle').textContent = opts.title || 'Please confirm';
    dialog.querySelector('#civConfirmMsg').textContent   = opts.message || 'Are you sure?';
    const ok = dialog.querySelector('#civConfirmOk');
    ok.textContent = opts.okText || 'Confirm';
    ok.className = 'px-4 py-2 text-sm font-bold text-white rounded-lg shadow-sm transition ' + v.btn;
    dialog.querySelector('#civConfirmIconWrap').className = 'h-11 w-11 rounded-full flex items-center justify-center flex-shrink-0 ' + v.iconWrap;
    dialog.querySelector('#civConfirmIcon').className = 'fa-solid text-lg ' + v.icon;

    return new Promise(function (resolve) {
      dialog.returnValue = '';
      dialog.addEventListener('close', function onClose() {
        dialog.removeEventListener('close', onClose);
        resolve(dialog.returnValue === 'ok');
      });
      dialog.showModal();
      dialog.querySelector('#civConfirmCancel').focus();
    });
  }
  window.civConfirm = civConfirm;

  // Capture phase: runs before page handlers, so a cancelled confirm stops everything.
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
    if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }

    e.preventDefault();
    e.stopImmediatePropagation();
    const submitter = e.submitter || null;

    civConfirm({
      title:   form.dataset.confirmTitle,
      message: form.dataset.confirm,
      okText:  form.dataset.confirmOk,
      variant: form.dataset.confirmVariant,
    }).then(function (yes) {
      if (!yes) return;
      form.dataset.confirmed = '1';
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
      } else {
        form.submit();
      }
    });
  }, true);

  // ── Loading state (bubble phase, after page handlers) ─────────────────
  const SPINNER = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>';

  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (e.defaultPrevented) return;                         // AJAX / validation handled by page JS
    if (form.hasAttribute('data-no-loading')) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    if (form.target && form.target !== '_self') return;     // opens elsewhere, page stays

    if (form.dataset.submitting === '1') {                  // second click while in flight
      e.preventDefault();
      return;
    }
    form.dataset.submitting = '1';

    const buttons = form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]');
    const clicked = e.submitter;

    // Defer so the clicked button's name/value is still included in the POST.
    setTimeout(function () {
      buttons.forEach(function (btn) {
        btn.dataset.originalHtml = btn.tagName === 'INPUT' ? btn.value : btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('opacity-70', 'cursor-wait');
        if (btn === clicked || buttons.length === 1) {
          const label = btn.dataset.loadingText || 'Processing...';
          if (btn.tagName === 'INPUT') btn.value = label;
          else btn.innerHTML = SPINNER + label;
        }
      });
    }, 0);

    // Safety net: forms that trigger a file download never navigate away.
    setTimeout(function () { resetForm(form); }, 20000);
  });

  function resetForm(form) {
    delete form.dataset.submitting;
    form.querySelectorAll('[data-original-html]').forEach(function (btn) {
      if (btn.tagName === 'INPUT') btn.value = btn.dataset.originalHtml;
      else btn.innerHTML = btn.dataset.originalHtml;
      delete btn.dataset.originalHtml;
      btn.disabled = false;
      btn.classList.remove('opacity-70', 'cursor-wait');
    });
  }

  // Restore buttons when the user navigates Back (bfcache).
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) document.querySelectorAll('form[data-submitting="1"]').forEach(resetForm);
  });
})();

  // -- Data Integrity (Title Case & Numeric Blocking) --------------
  function toTitleCase(str) {
    return str.replace(
      /\w\S*/g,
      function(txt) {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
      }
    );
  }

  document.addEventListener('input', function(e) {
    if (!e.target || !e.target.matches) return;
    
    // Title Case fields
    if (e.target.matches('input[name="business_name"]')) {
       let start = e.target.selectionStart;
       let end = e.target.selectionEnd;
       let val = e.target.value;
       let newVal = toTitleCase(val);
       if (val !== newVal) {
           e.target.value = newVal;
           e.target.setSelectionRange(start, end);
       }
    }

    // Name fields (Title Case AND block numbers)
    if (e.target.matches('input[name="owner_name"], input[name="stall_holder"]')) {
       let val = e.target.value;
       if (/[0-9]/.test(val)) {
           val = val.replace(/[0-9]/g, '');
           e.target.value = val;
       }
       let start = e.target.selectionStart;
       let end = e.target.selectionEnd;
       let newVal = toTitleCase(val);
       if (val !== newVal) {
           e.target.value = newVal;
           e.target.setSelectionRange(start, end);
       }
    }
  });

