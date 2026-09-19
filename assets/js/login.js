// BYPASS_OTP is read from the form's data-bypass-otp attribute (set by PHP from .env).
// To toggle: set BYPASS_OTP=true or BYPASS_OTP=false in your .env file.
// Defaults to FALSE (OTP required) if the attribute is missing or unreadable.
const BYPASS_OTP = document.getElementById('loginForm')?.dataset?.bypassOtp === 'true';

function togglePasswordVisibility() {
  const passwordInput = document.getElementById('password');
  const passwordIcon = document.getElementById('passwordIcon');

  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    passwordIcon.classList.remove('fa-eye-slash');
    passwordIcon.classList.add('fa-eye');
  } else {
    passwordInput.type = 'password';
    passwordIcon.classList.remove('fa-eye');
    passwordIcon.classList.add('fa-eye-slash');
  }
}

// Status Alert Modal Controller
let statusAlertTimer = null;

function hideStatusAlert() {
  const modal = document.getElementById('statusModal');
  if (!modal) return;
  if (statusAlertTimer) {
    clearTimeout(statusAlertTimer);
    statusAlertTimer = null;
  }
  modal.classList.add('opacity-0', '-translate-y-2');
  setTimeout(() => {
    modal.classList.add('hidden');
  }, 250);
}

function showStatusAlert(type, customMessage = "") {
  const modal = document.getElementById('statusModal');
  const icon = document.getElementById('modalIcon');
  const title = document.getElementById('modalTitle');
  const msgText = document.getElementById('modalMessage');

  if (!modal) return;

  if (statusAlertTimer) {
    clearTimeout(statusAlertTimer);
    statusAlertTimer = null;
  }

  modal.classList.remove('hidden', 'opacity-0', '-translate-y-2', 'border-rose-200', 'bg-rose-50/95', 'text-rose-900', 'border-emerald-200', 'bg-emerald-50/95', 'text-emerald-900', 'border-amber-200', 'bg-amber-50/95', 'text-amber-900');
  modal.className = "transform transition-all duration-300 ease-out border rounded-xl p-4 flex items-start space-x-3 shadow-lg relative my-2";

  if (type === 'success') {
    modal.classList.add('border-emerald-200', 'bg-emerald-50/95', 'text-emerald-900');
    if (icon) icon.innerHTML = '<div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 shadow-xs"><i class="fa-solid fa-circle-check text-base"></i></div>';
    if (title) title.textContent = "Success";
    msgText.textContent = customMessage || "Login successful. Entering dashboard...";
  } else if (type === 'error') {
    modal.classList.add('border-rose-200', 'bg-rose-50/95', 'text-rose-900');
    if (icon) icon.innerHTML = '<div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 shadow-xs animate-pulse"><i class="fa-solid fa-triangle-exclamation text-base"></i></div>';
    if (title) title.textContent = "Notice";
    msgText.textContent = customMessage || "Login failed. Please check your credentials.";
  } else if (type === 'maintenance') {
    modal.classList.add('border-amber-200', 'bg-amber-50/95', 'text-amber-900');
    if (icon) icon.innerHTML = '<div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shadow-xs"><i class="fa-solid fa-screwdriver-wrench text-base"></i></div>';
    if (title) title.textContent = "System Maintenance";
    msgText.textContent = customMessage || "System maintenance is scheduled for Sunday, 11:00 PMâ€“1:00 AM. Save drafts before then.";
  }

  statusAlertTimer = setTimeout(() => {
    hideStatusAlert();
  }, 7000);
}

function showDashboardLoadingOverlay(message = 'Entering Dashboard...') {
  const overlay = document.getElementById('dashboardLoadingOverlay');
  const msgEl = document.getElementById('loadingOverlayMessage');
  if (msgEl) msgEl.textContent = message;
  if (!overlay) return;
  overlay.classList.remove('hidden');
  setTimeout(() => {
    overlay.classList.remove('opacity-0');
  }, 10);
}

async function handleLogin(event) {
  event.preventDefault();
  const id = document.getElementById('employeeId').value.trim();
  const pass = document.getElementById('password').value.trim();
  const submitBtn = event.target.querySelector('button[type="submit"]');
  const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Sign in';

  if (!id || !pass) {
    showStatusAlert('error', 'Please fill in both Employee ID / Email and Password.');
    return;
  }

  const recaptchaResponse = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';
  if (typeof grecaptcha !== 'undefined' && !recaptchaResponse) {
    showStatusAlert('error', 'Please check the "I\'m not a robot" box to verify you are not a robot.');
    return;
  }

  if (id.toLowerCase() === 'maintenance') {
    showStatusAlert('maintenance');
    return;
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="inline-flex items-center justify-center gap-2"><img src="assets/images/spinner.svg" class="h-4 w-4 inline" alt="loading"> Authenticating...</span>';
  }

  try {
    const response = await fetch('api/employee/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        employeeId: id,
        password: pass,
        recaptchaResponse: recaptchaResponse,
        'g-recaptcha-response': recaptchaResponse
      })
    });

    const data = await response.json();

    if (data.status === 'otp_required') {
      if (BYPASS_OTP) {
        // TEMP: Call bypass-otp.php to establish the session using the
        // remote PHPSESSID saved during login, then redirect.
        showStatusAlert('success', 'Login successful! Redirecting to dashboard...');
        showDashboardLoadingOverlay('Entering Dashboard...');
        try {
          await fetch('api/employee/bypass-otp.php', { method: 'POST' });
        } catch (_) { /* best-effort */ }
        setTimeout(() => {
          window.location.href = 'pages/treasury/index.php';
        }, 1400);
      } else {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHtml;
        }
        openOtpModal(data.email || '');
      }
    } else if (data.status === 'success') {
      showStatusAlert('success', data.message || 'Login successful! Redirecting to dashboard...');
      showDashboardLoadingOverlay('Entering Dashboard...');
      setTimeout(() => {
        window.location.href = 'pages/treasury/index.php';
      }, 1400);
    } else if (data.status === 'maintenance') {
      showStatusAlert('maintenance', data.message);
      if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }
    } else {
      showStatusAlert('error', data.message || 'Login failed. Please check your credentials.');
      if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }
    }
  } catch (err) {
    // Fallback for direct offline / static testing
    const validIds = ['SADM-2026-001', 'EMP-1111-ADMIN-2026', 'ADMIN', 'SUPERADMIN@CIVENTRAL.GOV.PH'];
    if (validIds.includes(id.toUpperCase()) && pass === '1234') {
      showStatusAlert('success', 'Login successful! Redirecting to dashboard...');
      showDashboardLoadingOverlay('Entering Dashboard...');
      setTimeout(() => {
        window.location.href = 'pages/treasury/index.php';
      }, 1400);
    } else {
      showStatusAlert('error', 'Login failed. Invalid credentials or network error.');
      if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }
    }
  }
}

// OTP MODAL CONTROLLERS & EVENT HANDLERS
let resendCooldownTimer = null;

function openOtpModal(email = '') {
  const modal = document.getElementById('otpModal');
  const emailEl = document.getElementById('otpMaskedEmail');
  const otpInputs = document.querySelectorAll('.otp-input');

  if (emailEl && email) {
    emailEl.textContent = email;
  }

  // Reset inputs & alerts
  otpInputs.forEach(input => input.value = '');
  showOtpAlert('');

  modal.classList.remove('hidden');
  setTimeout(() => {
    modal.classList.remove('opacity-0');
    modal.querySelector('.transform').classList.remove('scale-95');
    modal.querySelector('.transform').classList.add('scale-100');
    if (otpInputs[0]) otpInputs[0].focus();
  }, 10);
}

function closeOtpModal() {
  const modal = document.getElementById('otpModal');
  modal.classList.add('opacity-0');
  modal.querySelector('.transform').classList.remove('scale-100');
  modal.querySelector('.transform').classList.add('scale-95');
  setTimeout(() => {
    modal.classList.add('hidden');
  }, 200);
}

function showOtpAlert(message, isError = true) {
  const alertEl = document.getElementById('otpAlert');
  if (!alertEl) return;
  if (!message) {
    alertEl.classList.add('hidden');
    alertEl.textContent = '';
    return;
  }
  alertEl.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'border-red-200', 'bg-green-50', 'text-green-700', 'border-green-200');
  if (isError) {
    alertEl.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
  } else {
    alertEl.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
  }
  alertEl.textContent = message;
}

// Auto-advance & paste handler for 6 OTP boxes (NUMBERS ONLY STRICTLY ENFORCED)
document.addEventListener('DOMContentLoaded', () => {
  const otpInputs = document.querySelectorAll('.otp-input');
  otpInputs.forEach((input, index) => {
    // Strictly prevent non-numeric keys on keydown
    input.addEventListener('keydown', (e) => {
      const allowedControlKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
      if (allowedControlKeys.includes(e.key) || e.ctrlKey || e.metaKey) {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
          otpInputs[index - 1].focus();
        }
        return;
      }
      if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
      }
    });

    // Strip non-digit characters on input
    input.addEventListener('input', (e) => {
      const cleanVal = e.target.value.replace(/[^0-9]/g, '');
      e.target.value = cleanVal;
      if (cleanVal && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
    });

    // Handle numeric paste
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').trim();
      if (pastedData) {
        pastedData.split('').slice(0, 6).forEach((char, i) => {
          if (otpInputs[i]) otpInputs[i].value = char;
        });
        const focusIndex = Math.min(pastedData.length, 5);
        if (otpInputs[focusIndex]) otpInputs[focusIndex].focus();
      }
    });
  });
});

async function handleVerifyOTP(event) {
  event.preventDefault();
  const otpInputs = document.querySelectorAll('.otp-input');
  const otpCode = Array.from(otpInputs).map(i => i.value.trim()).join('');

  if (otpCode.length !== 6 || !/^\d{6}$/.test(otpCode)) {
    showOtpAlert('Please enter a complete 6-digit OTP code.');
    return;
  }

  const verifyBtn = document.getElementById('btnVerifyOtp');
  const originalBtnText = verifyBtn ? verifyBtn.innerHTML : 'Verify';

  if (verifyBtn) {
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<span class="inline-flex items-center gap-2"><img src="assets/images/spinner.svg" class="h-4 w-4 inline" alt="loading"> Verifying...</span>';
  }

  try {
    const response = await fetch('api/employee/verify-otp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ otp: otpCode })
    });
    const data = await response.json();

    if (data.status === 'success') {
      showOtpAlert('OTP verified! Entering Dashboard...', false);
      closeOtpModal();
      showDashboardLoadingOverlay('Entering Dashboard...');
      setTimeout(() => {
        window.location.href = 'pages/treasury/index.php';
      }, 1400);
    } else {
      showOtpAlert(data.message || 'Verification failed.');
      if (verifyBtn) {
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = originalBtnText;
      }
    }
  } catch (err) {
    showOtpAlert('Network error verifying OTP code.');
    if (verifyBtn) {
      verifyBtn.disabled = false;
      verifyBtn.innerHTML = originalBtnText;
    }
  }
}

async function handleResendOTP() {
  const resendBtn = document.getElementById('btnResendOtp');
  const timerText = document.getElementById('resendTimerText');
  if (resendBtn.disabled) return;

  resendBtn.disabled = true;
  showOtpAlert('Resending verification code...', false);

  try {
    const response = await fetch('api/employee/resend-otp.php', { method: 'POST' });
    const data = await response.json();

    if (data.status === 'success') {
      showOtpAlert(data.message || 'A new OTP code has been sent to your email.', false);
      startResendCooldown(60);
    } else {
      showOtpAlert(data.message || 'Failed to resend code.');
      resendBtn.disabled = false;
    }
  } catch (err) {
    showOtpAlert('Network error resending OTP code.');
    resendBtn.disabled = false;
  }
}

function startResendCooldown(seconds) {
  const resendBtn = document.getElementById('btnResendOtp');
  const timerText = document.getElementById('resendTimerText');
  let countdown = seconds;

  resendBtn.disabled = true;
  resendBtn.classList.add('opacity-50', 'cursor-not-allowed');
  timerText.classList.remove('hidden');
  timerText.textContent = `(${countdown}s)`;

  if (resendCooldownTimer) clearInterval(resendCooldownTimer);

  resendCooldownTimer = setInterval(() => {
    countdown--;
    if (countdown <= 0) {
      clearInterval(resendCooldownTimer);
      resendBtn.disabled = false;
      resendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
      timerText.classList.add('hidden');
      timerText.textContent = '';
    } else {
      timerText.textContent = `(${countdown}s)`;
    }
  }, 1000);
}