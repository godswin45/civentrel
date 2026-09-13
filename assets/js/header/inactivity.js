// Inactivity Countdown Timer (5 Minutes)
let countdownInterval;
let lastActivityTime = Date.now();
const INACTIVITY_LIMIT = 30 * 60 * 1000;

function updateCountdownDisplay() {
  const now = Date.now();
  const elapsed = now - lastActivityTime;
  const remaining = Math.max(0, INACTIVITY_LIMIT - elapsed);
  
  const secondsLeft = Math.ceil(remaining / 1000);
  const minutes = Math.floor(secondsLeft / 60);
  const seconds = secondsLeft % 60;
  
  const displayStr = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  const countdownEl = document.getElementById('inactivityCountdown');
  
  if (countdownEl) {
    countdownEl.textContent = displayStr;
    if (secondsLeft <= 60) {
      countdownEl.classList.add('text-rose-500');
    } else {
      countdownEl.classList.remove('text-rose-500');
    }
  }

  if (remaining <= 0) {
    clearInterval(countdownInterval);
    window.location.href = window.civentralBasePath + 'pages/logout.php';
  }
}

function resetInactivityTimer() {
  lastActivityTime = Date.now();
  updateCountdownDisplay();
}
