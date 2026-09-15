<?php
// Load .env variables (no database connection needed on login page)
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
$recaptchaSiteKey = getenv('RECAPTCHA_SITE_KEY') ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Login – Civentral Portal</title>
  <meta name="description" content="Secure employee login portal for the Civentral Digital Government Platform of Caloocan City.">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/png" href="assets/images/logo.png">

  <!-- Preconnect for performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com">

  <!-- Google Fonts: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style type="text/tailwindcss">
  @theme {
    --color-brand-light: #EEF5FF;
    --color-brand-border: #B4D4FF;
    --color-brand-medium: #86B6F6;
    --color-brand-dark: #176B87;
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  }
  html { scroll-behavior: smooth; }
</style>
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body class="bg-white min-h-screen font-sans antialiased selection:bg-brand-medium selection:text-white">

  <div class="min-h-screen flex flex-col md:flex-row relative">
    
    <div class="hidden md:block md:w-1/2 lg:w-3/5 bg-[url(assets/images/building-bg.jpg)] bg-cover bg-left bg-no-repeat mix-blend-multiply relative">
      <div class="absolute inset-0 bg-gradient-to-r from-brand-dark/10 via-transparent to-white"></div>
      
      <!-- Decorative Enterprise Tagline Overlay -->
      <div class="absolute bottom-8 left-8 right-8 text-slate-700/80 pointer-events-none hidden md:block">
        <span class="text-xs font-bold tracking-widest uppercase text-brand-dark block mb-1">OFFICIAL MUNICIPAL PLATFORM</span>
        <p class="text-sm font-semibold text-slate-600 max-w-lg leading-relaxed">
          Integrated Local Government Operations & Service Management System
        </p>
      </div>
    </div>

    <div class="flex-1 flex flex-col justify-between p-4 sm:p-6 md:p-8 lg:p-10 bg-white z-10 md:border-l md:border-gray-200 h-screen max-h-screen overflow-y-auto md:overflow-y-hidden">
      
      <div></div>

      <div class="w-full max-w-md mx-auto space-y-3 my-auto relative">
  
        <div class="w-full space-y-3">
          <div class="flex flex-col items-center justify-center text-center pb-1 w-full">
            <img src="assets/images/logo.png" alt="Civentral Graphic" class="h-20 sm:h-24 w-auto object-contain -mb-1">
            <span class="text-3xl sm:text-4xl font-black text-[#176B87] tracking-[0.12em] uppercase font-sans">
              Civentral
            </span>
            <span class="text-[9px] sm:text-[10px] font-semibold tracking-[0.2em] uppercase text-gray-400/80 mt-0.5">
              Digital Government Platform
            </span>
          </div>
          
          <div class="flex flex-col items-center md:items-start text-center md:text-left space-y-1 pt-5 sm:pt-6">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-700 tracking-tight">Employee Access</h1>
            <p class="text-xs sm:text-sm text-gray-500 font-medium">Sign in using your Employee ID and password.</p>
          </div>
        </div>

        <div id="statusModal" class="hidden transform transition-all duration-300 ease-out border rounded-xl p-3 flex items-start space-x-3 shadow-lg relative my-1">
          <div id="modalIcon" class="shrink-0 mt-0.5"></div>
          <div class="flex-1">
            <p id="modalTitle" class="text-xs font-bold uppercase tracking-wider mb-0.5"></p>
            <p id="modalMessage" class="text-xs font-medium leading-relaxed"></p>
          </div>
        </div>

        <form id="loginForm" class="space-y-3" onsubmit="handleLogin(event)"
          data-bypass-otp="<?php 
            $bypass = getenv('BYPASS_OTP');
            if ($bypass === false) $bypass = $_SERVER['BYPASS_OTP'] ?? $_ENV['BYPASS_OTP'] ?? 'false';
            echo ($bypass === 'true' || $bypass === '1') ? 'true' : 'false'; 
          ?>">
    
        <div class="space-y-1">
          <label for="employeeId" class="text-xs font-semibold text-gray-500">Employee ID</label>
          <div class="relative flex items-center">
            <span class="absolute left-3.5 text-gray-400">
              <i class="fa-solid fa-user-tie text-sm"></i>
            </span>
            <input 
              type="text" 
              id="employeeId" 
              placeholder="Enter your Employee ID or email" 
              required
              class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition"
            />
          </div>
        </div>

        <div class="space-y-1">
          <label for="password" class="text-xs font-semibold text-gray-500">Password</label>
          <div class="relative flex items-center">
            <span class="absolute left-3.5 text-gray-400">
              <i class="fa-solid fa-key text-sm"></i>
            </span>
            <input 
              type="password" 
              id="password" 
              placeholder="••••••••••••••••" 
              required
              class="w-full pl-10 pr-10 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-medium focus:ring-1 focus:ring-brand-medium transition"
            />
            <button 
              type="button" 
              onclick="togglePasswordVisibility()" 
              class="absolute right-3.5 text-gray-400 hover:text-gray-600 focus:outline-none"
            >
              <i id="passwordIcon" class="fa-solid fa-eye-slash text-sm"></i>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between pt-0.5">
          <label class="flex items-center space-x-2 cursor-pointer select-none">
            <input 
              type="checkbox" 
              class="w-4 h-4 text-brand-medium border-gray-300 rounded focus:ring-brand-medium accent-brand-medium"
            />
            <span class="text-xs text-gray-500">Keep me signed in</span>
          </label>
          <a href="#" class="text-xs font-semibold text-brand-medium hover:underline">Forgot password?</a>
        </div>

        <div class="flex justify-center my-2">
          <div class="g-recaptcha scale-95 sm:scale-100 origin-center" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></div>
        </div>

        <button 
          type="submit" 
          class="w-full py-3.5 px-4 bg-brand-medium hover:bg-opacity-90 text-white font-semibold rounded-lg text-sm transition shadow-sm focus:outline-none cursor-pointer"
        >
          Sign in
        </button>
      </form>

      <div class="relative flex py-1.5 items-center">
        <div class="flex-grow border-t border-gray-200"></div>
        <span class="flex-shrink mx-4 text-xs font-semibold text-gray-400 tracking-wider">OR</span>
        <div class="flex-grow border-t border-gray-200"></div>
      </div>

      <a 
        href="index.php"
        class="inline-block text-center w-full py-2.5 px-4 bg-white hover:bg-brand-medium hover:text-white text-brand-medium font-medium border border-brand-medium rounded-lg text-sm transition focus:outline-none"
      >
        Back to Home
      </a>

  </div>

      <div class="text-center pt-3 sm:pt-4">
        <p class="text-[10px] md:text-xs font-medium text-gray-400 tracking-wide leading-relaxed max-w-sm mx-auto">
          Authorized personnel only. Unauthorized access attempts are logged and may be prosecuted under RA 8792.
        </p>
      </div>

    </div>
  </div>

  <!-- 2FA OTP Verification Modal -->
  <div id="otpModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs transition-all duration-300 opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 sm:p-8 transform scale-95 transition-all duration-300 border border-slate-100 relative">
      
      <!-- Close / Back Button -->
      <button type="button" onclick="closeOtpModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition cursor-pointer">
        <i class="fa-solid fa-xmark text-lg"></i>
      </button>

      <div class="text-center space-y-3 mb-6">
        <div class="h-14 w-14 rounded-2xl bg-brand-light border border-brand-border flex items-center justify-center text-brand-dark mx-auto shadow-xs">
          <i class="fa-solid fa-shield-halved text-2xl"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 tracking-tight">Two-Factor Verification</h3>
        <p class="text-xs text-slate-500 max-w-xs mx-auto leading-relaxed">
          We sent a 6-digit security code to <strong id="otpMaskedEmail" class="text-brand-dark font-mono">your email</strong>.
        </p>
      </div>

      <!-- OTP Form -->
      <form id="otpForm" onsubmit="handleVerifyOTP(event)" class="space-y-6">
        
        <!-- 6 Input boxes -->
        <div class="flex justify-between items-center gap-2 max-w-xs mx-auto">
          <input type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" class="otp-input w-11 h-12 text-center text-xl font-bold font-mono text-brand-dark bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/20 focus:outline-none transition" required autofocus />
          <input type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" class="otp-input w-11 h-12 text-center text-xl font-bold font-mono text-brand-dark bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/20 focus:outline-none transition" required />
          <input type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" class="otp-input w-11 h-12 text-center text-xl font-bold font-mono text-brand-dark bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/20 focus:outline-none transition" required />
          <input type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" class="otp-input w-11 h-12 text-center text-xl font-bold font-mono text-brand-dark bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/20 focus:outline-none transition" required />
          <input type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" class="otp-input w-11 h-12 text-center text-xl font-bold font-mono text-brand-dark bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/20 focus:outline-none transition" required />
          <input type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" class="otp-input w-11 h-12 text-center text-xl font-bold font-mono text-brand-dark bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:border-brand-medium focus:ring-2 focus:ring-brand-medium/20 focus:outline-none transition" required />
        </div>

        <div id="otpAlert" class="hidden text-xs text-center p-3 rounded-lg border"></div>

        <button 
          type="submit" 
          id="btnVerifyOtp"
          class="w-full py-3 px-4 bg-brand-medium hover:bg-opacity-90 text-white font-bold rounded-xl text-sm transition shadow-sm focus:outline-none cursor-pointer flex items-center justify-center gap-2"
        >
          <span>Verify & Complete Sign In</span>
        </button>

        <div class="text-center pt-2">
          <p class="text-xs text-slate-500">
            Didn't receive the code? 
            <button type="button" id="btnResendOtp" onclick="handleResendOTP()" class="font-bold text-brand-dark hover:underline cursor-pointer focus:outline-none">
              Resend Code
            </button>
            <span id="resendTimerText" class="text-slate-400 font-semibold hidden ml-1"></span>
          </p>
        </div>

      </form>

    </div>
  </div>

  <!-- Fullscreen Dashboard Loading Overlay -->
  <div id="dashboardLoadingOverlay" class="hidden fixed inset-0 z-50 flex flex-col items-center justify-center bg-white/95 backdrop-blur-md transition-all duration-500 opacity-0 pointer-events-auto">
    <div class="flex flex-col items-center space-y-6 text-center p-8 max-w-sm mx-auto">
      <div class="relative flex items-center justify-center">
        <!-- Outer pulsing ring -->
        <div class="absolute w-40 h-40 rounded-full bg-brand-medium/20 animate-ping"></div>
        <img src="assets/images/logo.png" alt="Civentral Logo" class="h-32 w-auto object-contain relative z-10 animate-bounce">
      </div>
      
      <div>
        <span class="text-3xl font-black text-brand-medium tracking-[0.25em] uppercase font-sans block">
          Civentral
        </span>
      </div>

      <div class="flex items-center space-x-3 pt-2 text-brand-dark font-semibold text-sm">
        <svg class="animate-spin h-5 w-5 text-brand-medium" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span id="loadingOverlayMessage" class="text-slate-700 font-medium">Entering Dashboard...</span>
      </div>
      
      <!-- Progress indicator bar -->
      <div class="w-48 h-1.5 bg-slate-100 rounded-full overflow-hidden relative">
        <div class="h-full bg-brand-medium rounded-full animate-pulse w-full"></div>
      </div>
    </div>
  </div>

  <script src="assets/js/login.js?v=<?php echo filemtime(__DIR__ . '/assets/js/login.js'); ?>"></script>
</body>
</html>