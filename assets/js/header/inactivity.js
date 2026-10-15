// Inactivity Countdown Timer (5 Minutes)
let countdownInterval;
let lastActivityTime = Date.now();
let lastHeartbeatTime = Date.now();
const INACTIVITY_LIMIT = 5 * 60 * 1000;
const HEARTBEAT_INTERVAL = 60 * 1000; // Refresh session at most once every 60s when active

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
    const basePath = (typeof window.civentralBasePath !== 'undefined') ? window.civentralBasePath : '../';
    window.location.href = basePath + 'pages/logout.php';
  }
}

function sendHeartbeat() {
  const basePath = (typeof window.civentralBasePath !== 'undefined') ? window.civentralBasePath : '../';
  fetch(basePath + 'api/heartbeat.php', {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  })
    .then(r => {
      if (r.status === 401) {
        return r.json().then(data => {
          const redirectUrl = data.redirect || (basePath + 'pages/logout.php');
          window.location.href = redirectUrl;
        }).catch(() => {
          window.location.href = basePath + 'pages/logout.php';
        });
      }
    })
    .catch(() => {});
}

function resetInactivityTimer() {
  const now = Date.now();
  lastActivityTime = now;
  updateCountdownDisplay();

  // If user is active and 60 seconds have elapsed since last server sync, refresh PHP session
  if (now - lastHeartbeatTime >= HEARTBEAT_INTERVAL) {
    lastHeartbeatTime = now;
    sendHeartbeat();
  }
}

