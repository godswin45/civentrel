<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Civentral – Caloocan City Government Portal</title>
  <meta name="description" content="Civentral is the official digital government platform of Caloocan City. Access public services, announcements, permits, and civic records online.">
  <meta property="og:title" content="Civentral – Caloocan City Government Portal">
  <meta property="og:description" content="Official municipal portal for Caloocan City residents. Permits, civil registry, social welfare, and more — all online.">
  <meta property="og:type" content="website">
  <meta name="theme-color" content="#176B87">
  <link rel="icon" type="image/png" href="assets/images/logo.png">

  <!-- Preconnect for performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com">

  <!-- Google Fonts: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <!-- Design system palette & base -->
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

  <noscript>
    <style>.js-only { display: none !important; }</style>
  </noscript>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

  <!-- Live clock & top bar -->
  <div class="bg-slate-900 text-white text-xs px-4 sm:px-6 py-2.5 flex flex-col sm:flex-row items-center justify-between border-b border-slate-800 gap-2">
    <div class="flex items-center space-x-2 font-mono text-slate-300 text-center sm:text-left">
      <i class="fa-solid fa-calendar-day text-brand-medium"></i>
      <span id="liveClock">Loading Date & Time...</span>
    </div>
    <div class="flex items-center space-x-1 text-[11px] font-bold tracking-wider">
      <button onclick="changeLanguage('en')" id="lang-en" class="text-white hover:text-brand-medium transition cursor-pointer">ENGLISH</button>
      <span class="text-slate-600">|</span>
      <button onclick="changeLanguage('tl')" id="lang-tl" class="text-slate-400 hover:text-brand-medium transition cursor-pointer">TAGALOG</button>
    </div>
  </div>

  <!-- Header -->
  <nav class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-brand-border px-4 sm:px-6 py-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <img src="assets/images/logo.png" alt="Civentral Logo" class="h-10 w-auto object-contain">
        <div class="flex flex-col">
          <span class="text-xl font-black text-brand-dark tracking-wider uppercase leading-none">CIVENTRAL</span>
          <span class="text-[10px] font-bold text-brand-medium tracking-widest uppercase mt-0.5" id="navTagline">Caloocan City Portal</span>
        </div>
      </div>
      
      <!-- For desktop menu -->
      <div class="hidden lg:flex items-center space-x-6">
        <a href="#city-showcase" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">Home</a>
        <a href="#leadership" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">Officials</a>
        <a href="#features" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">Services</a>
        <a href="#announcements" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">News & Events</a>
        <a href="#download-app" class="text-sm font-semibold text-slate-600 hover:text-brand-dark transition">Mobile App</a>
      </div>

      <div class="flex items-center space-x-2">
        <a href="login.php" class="hidden lg:inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-brand-dark border border-brand-medium bg-brand-light hover:bg-brand-medium hover:text-white rounded-lg shadow-xs transition">
          Employee Portal <i class="fa-solid fa-arrow-right-to-bracket ml-2 text-xs"></i>
        </a>
        <!-- Mobile -->
        <button onclick="toggleMobileMenu()" aria-label="Toggle mobile menu" aria-expanded="false" id="mobileMenuBtn" class="lg:hidden text-slate-600 hover:text-brand-dark p-2 focus:outline-none cursor-pointer">
          <i id="menuIcon" class="fa-solid fa-bars text-xl"></i>
        </button>
      </div>
    </div>

    <!-- Dropdown Mobile Menu -->
    <div id="mobileMenu" class="hidden lg:hidden border-t border-slate-100 mt-4 pt-4 flex flex-col space-y-3 px-2">
      <a href="#city-showcase" onclick="toggleMobileMenu()" class="text-sm font-semibold text-slate-600 hover:text-brand-dark py-1">Home</a>
      <a href="#leadership" onclick="toggleMobileMenu()" class="text-sm font-semibold text-slate-600 hover:text-brand-dark py-1">Officials</a>
      <a href="#features" onclick="toggleMobileMenu()" class="text-sm font-semibold text-slate-600 hover:text-brand-dark py-1">Services</a>
      <a href="#announcements" onclick="toggleMobileMenu()" class="text-sm font-semibold text-slate-600 hover:text-brand-dark py-1">News & Events</a>
      <a href="#download-app" onclick="toggleMobileMenu()" class="text-sm font-semibold text-slate-600 hover:text-brand-dark py-1 mb-2">Mobile App</a>
      <a href="login.php" class="w-full text-center py-2.5 text-sm font-semibold text-brand-dark border border-brand-medium bg-brand-light rounded-lg">
        Employee Portal <i class="fa-solid fa-arrow-right-to-bracket ml-1 text-xs"></i>
      </a>
    </div>
  </nav>

  <!-- Hero Carousel -->
  <header id="city-showcase" class="relative bg-slate-900 h-[460px] sm:h-[520px] md:h-[620px] overflow-hidden">
    <div class="absolute inset-0 flex transition-transform duration-1000 ease-in-out" id="carouselTrack">
      <div class="w-full h-full shrink-0 bg-cover bg-center relative bg-[url('assets/images/main-building.jpg')] bg-slate-800">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-transparent"></div>
      </div>
      <div class="w-full h-full shrink-0 bg-cover bg-center relative bg-[url('assets/images/park.jpg')] bg-slate-700">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-transparent"></div>
      </div>
    </div>

    <!-- Carousel Arrow Controls -->
    <button onclick="setSlide((currentSlide - 1 + totalSlides) % totalSlides)" aria-label="Previous slide" class="js-only absolute left-4 top-1/2 -translate-y-1/2 z-20 h-10 w-10 bg-white/10 hover:bg-white/25 border border-white/20 rounded-full flex items-center justify-center text-white transition cursor-pointer backdrop-blur-xs">
      <i class="fa-solid fa-chevron-left text-sm"></i>
    </button>
    <button onclick="setSlide((currentSlide + 1) % totalSlides)" aria-label="Next slide" class="js-only absolute right-4 top-1/2 -translate-y-1/2 z-20 h-10 w-10 bg-white/10 hover:bg-white/25 border border-white/20 rounded-full flex items-center justify-center text-white transition cursor-pointer backdrop-blur-xs">
      <i class="fa-solid fa-chevron-right text-sm"></i>
    </button>

    <div class="absolute inset-0 z-10 flex items-center justify-center px-6 text-center">
      <div class="max-w-4xl space-y-4 sm:space-y-6">
        <span class="text-[10px] sm:text-xs font-bold tracking-[0.2em] sm:tracking-[0.3em] uppercase text-brand-medium bg-white/10 backdrop-blur-xs px-3 sm:px-4 py-1.5 rounded-full border border-white/20" id="heroBadge">
          Welcome to the Historic City of Caloocan
        </span>
        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-tight sm:leading-none" id="heroTitle">
          Serving the Citizens with Excellence and Integrity
        </h1>
        <p class="text-xs sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed" id="heroSubtitle">
          Discover convenient digital public pathways, community updates, and localized transaction structures managed completely online.
        </p>
        <div class="pt-2 sm:pt-4 flex flex-wrap justify-center gap-3">
          <a href="#features" class="px-4 sm:px-5 py-2.5 sm:py-3 bg-brand-medium text-white font-semibold rounded-lg text-xs sm:text-sm transition shadow-md hover:bg-opacity-90" id="heroBtnPrimary">Explore Services</a>
          <a href="#download-app" class="px-4 sm:px-5 py-2.5 sm:py-3 bg-white/10 hover:bg-white/20 text-white font-semibold border border-white/30 rounded-lg text-xs sm:text-sm transition" id="heroBtnSecondary">Get Mobile App</a>
        </div>
      </div>
    </div>

    <!-- Dot navigation -->
    <div class="absolute bottom-6 left-0 right-0 z-20 flex justify-center space-x-2">
      <button onclick="setSlide(0)" aria-label="Go to slide 1" class="h-2 w-8 bg-white rounded-full transition-all duration-300 id-dot cursor-pointer"></button>
      <button onclick="setSlide(1)" aria-label="Go to slide 2" class="h-2 w-2 bg-white/50 rounded-full transition-all duration-300 id-dot cursor-pointer"></button>
    </div>
  </header>

  <!-- Leader -->
  <section id="leadership" class="py-16 sm:py-24 px-4 sm:px-6 max-w-7xl mx-auto space-y-12">
    <div class="text-center space-y-3 max-w-2xl mx-auto">
      <span class="text-xs font-bold uppercase tracking-wider text-brand-medium" id="leaderBadge">City Leadership</span>
      <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight" id="leaderTitle">The Local Government Executive Council</h2>
      <p class="text-xs sm:text-sm text-slate-500" id="leaderSubtitle">
        Guiding the sustainable development and growth of Caloocan through dedicated public service.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
      <!-- Mayor -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6 text-center space-y-4 shadow-xs hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="h-28 w-28 rounded-full border-2 border-brand-medium mx-auto overflow-hidden bg-slate-100">
          <img src="assets/images/mayor.jpg" alt="Mayor Dale Gonzalo Along Malapitan" loading="lazy" class="w-full h-full object-cover object-top">
        </div>
        <div>
          <h4 class="font-extrabold text-slate-900 text-base">Hon. Dale Gonzalo "Along" Malapitan</h4>
          <p class="text-xs font-bold text-brand-dark uppercase tracking-widest mt-0.5" id="titleMayor">City Mayor</p>
        </div>
      </div>
      <!-- Vice Mayor -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6 text-center space-y-4 shadow-xs hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="h-28 w-28 rounded-full border-2 border-brand-medium mx-auto overflow-hidden bg-slate-100">
          <img src="assets/images/Vice.jpg" alt="Vice Mayor Karina Teh-Limsico" loading="lazy" class="w-full h-full object-cover object-top">
        </div>
        <div>
          <h4 class="font-extrabold text-slate-900 text-base">Hon. Karina Teh-Limsico</h4>
          <p class="text-xs font-bold text-brand-dark uppercase tracking-widest mt-0.5" id="titleViceMayor">City Vice Mayor</p>
        </div>
      </div>
      <!-- Sangguniang Panlungsod wala ako malagay -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6 text-center space-y-4 shadow-xs">
        <div class="h-24 w-24 rounded-full bg-brand-light border-2 border-brand-medium mx-auto overflow-hidden flex items-center justify-center text-brand-dark">
          <i class="fa-solid fa-users text-3xl"></i>
        </div>
        <div>
          <h4 class="font-extrabold text-slate-900 text-sm sm:text-base" id="titleCouncil">Sangguniang Panlungsod</h4>
          <p class="text-[11px] font-bold text-brand-dark uppercase tracking-widest mt-0.5" id="subtitleCouncil">City Council Members</p>
        </div>
      </div>
    </div>

    <!-- All Officials -->
    <div class="text-center pt-2">
      <a href="pages/officials.php" class="inline-flex items-center space-x-2 text-xs font-bold text-brand-dark bg-white border border-brand-border px-5 py-2.5 rounded-lg shadow-xs hover:bg-brand-light transition">
        <span>View Full Organizational Directory</span>
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
      </a>
    </div>
  </section>

  <!-- Announcements Section (Simple Picture Top, Caption Bottom) -->
  <section id="announcements" class="py-16 sm:py-24 bg-slate-100/80 border-y border-slate-200/80 px-4 sm:px-6">
    <div class="max-w-7xl mx-auto space-y-10 sm:space-y-12">
      <!-- Section Header -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200/60 pb-6">
        <div class="space-y-2">
          <span class="text-xs font-bold uppercase tracking-widest text-brand-dark bg-white border border-brand-border/60 px-3.5 py-1 rounded-full shadow-2xs" id="newsBadge">Bulletin Board</span>
          <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight" id="newsTitle">Active Circulars & Public Memos</h2>
          <p class="text-xs sm:text-sm text-slate-500 max-w-2xl leading-relaxed">
            Official municipal announcements, weather advisories, emergency alerts, tax payment deadlines, and public notices.
          </p>
        </div>
        <div class="shrink-0">
          <span class="inline-flex items-center gap-2 text-xs font-extrabold text-slate-700 bg-white border border-slate-200 px-4 py-2 rounded-xl shadow-2xs">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
            Live LGU Announcements
          </span>
        </div>
      </div>

      <!-- Announcements Cards Grid (Picture Top, Caption Bottom) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        
        <!-- Announcement 1: Weather Advisory & Class Suspension -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col group">
          <!-- Picture (Top) -->
          <div class="relative overflow-hidden bg-slate-900 aspect-video">
            <img src="assets/images/weather_advisory.png" alt="Weather Advisory: Class Suspension due to Heavy Rains" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            <span class="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md shadow-md animate-pulse">
              <i class="fa-solid fa-triangle-exclamation mr-1"></i> Weather Advisory
            </span>
          </div>

          <!-- Caption (Bottom) -->
          <div class="p-6 space-y-3 flex-1 flex flex-col justify-between">
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[11px] text-slate-400 font-semibold">
                <span class="text-red-600 font-bold flex items-center gap-1"><i class="fa-solid fa-cloud-showers-heavy"></i> Heavy Rainfall Warning</span>
                <span>Jul 26, 2026</span>
              </div>
              <h3 class="font-extrabold text-slate-900 text-base leading-snug group-hover:text-red-600 transition-colors">
                Suspension of Classes Due to Heavy Rains & Monsoon Weather
              </h3>
              <p class="text-xs text-slate-500 leading-relaxed">
                Classes in ALL LEVELS (Public & Private) in Caloocan City are suspended today due to heavy rainfall and thunderstorm advisories. Stay safe indoors and monitor official LGU channels.
              </p>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-red-600">
              <a href="tel:911" class="flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-phone"></i> Emergency Hotline: 911</a>
              <span class="text-[10px] text-slate-400 font-semibold">Read more <i class="fa-solid fa-arrow-right"></i></span>
            </div>
          </div>
        </div>

        <!-- Announcement 2: Business Permit Renewal -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col group">
          <!-- Picture (Top) -->
          <div class="relative overflow-hidden bg-slate-900 aspect-video">
            <img src="assets/images/bplo_advisory.png" alt="Business Permit Renewal and Tax Clearance Schedule 2026" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            <span class="absolute top-3 left-3 bg-blue-600 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md shadow-md">
              <i class="fa-solid fa-file-invoice-dollar mr-1"></i> BPLO Tax Notice
            </span>
          </div>

          <!-- Caption (Bottom) -->
          <div class="p-6 space-y-3 flex-1 flex flex-col justify-between">
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[11px] text-slate-400 font-semibold">
                <span class="text-blue-600 font-bold flex items-center gap-1"><i class="fa-solid fa-briefcase"></i> Business Permits</span>
                <span>Jul 25, 2026</span>
              </div>
              <h3 class="font-extrabold text-slate-900 text-base leading-snug group-hover:text-blue-600 transition-colors">
                2026 Business Permit Renewal & Tax Clearance Schedule
              </h3>
              <p class="text-xs text-slate-500 leading-relaxed">
                Online business permit renewal and tax assessment filing is open via the Civentral portal. File early to avail of early-bird municipal tax discounts.
              </p>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-blue-600">
              <a href="login.php" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-external-link-alt text-[10px]"></i> View Online Clearance</a>
              <span class="text-[10px] text-slate-400 font-semibold">Read more <i class="fa-solid fa-arrow-right"></i></span>
            </div>
          </div>
        </div>

        <!-- Announcement 3: AICS Social Assistance Payout -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col group">
          <!-- Picture (Top) -->
          <div class="relative overflow-hidden bg-slate-900 aspect-video">
            <img src="assets/images/aics_advisory.png" alt="AICS Educational and Medical Financial Aid Distribution" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            <span class="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md shadow-md">
              <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Social Welfare
            </span>
          </div>

          <!-- Caption (Bottom) -->
          <div class="p-6 space-y-3 flex-1 flex flex-col justify-between">
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[11px] text-slate-400 font-semibold">
                <span class="text-emerald-600 font-bold flex items-center gap-1"><i class="fa-solid fa-heart"></i> CSWDO Aid</span>
                <span>Jul 24, 2026</span>
              </div>
              <h3 class="font-extrabold text-slate-900 text-base leading-snug group-hover:text-emerald-600 transition-colors">
                AICS Educational & Medical Financial Aid Distribution
              </h3>
              <p class="text-xs text-slate-500 leading-relaxed">
                Verified beneficiary payout schedules for District 1, 2, and 3 residents are active. Check your portal profile for schedule slots and claim details.
              </p>
            </div>
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-emerald-600">
              <a href="login.php" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-map-location-dot text-[10px]"></i> View Payout Venue</a>
              <span class="text-[10px] text-slate-400 font-semibold">Read more <i class="fa-solid fa-arrow-right"></i></span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Services -->
  <section id="features" class="py-16 sm:py-24 px-4 sm:px-6 max-w-7xl mx-auto space-y-12">
    <div class="text-center space-y-3 max-w-2xl mx-auto">
      <span class="text-xs font-bold uppercase tracking-widest text-brand-dark bg-brand-light px-3.5 py-1 rounded-full border border-brand-border/60">Digital Municipal Services</span>
      <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight" id="servicesTitle">Unified Department Public Services</h2>
      <p class="text-xs sm:text-sm text-slate-500 max-w-xl mx-auto leading-relaxed">
        Integrated municipal digital pathways providing transparent service accessibility, online tracking, and streamlined departmental processing for all Caloocan residents.
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <!-- 1. Citizen Identity Registry -->
      <div class="bg-white border border-slate-200/80 hover:border-brand-medium/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shadow-2xs">
              <i class="fa-solid fa-id-card text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200/60">Civil Registry</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Citizen Identity Registry</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Population profiling setups, official civic information master databases, and digital residency verification.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Digital ID</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Instant Verification</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-brand-dark hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 2. Permit Clearance Controls -->
      <div class="bg-white border border-slate-200/80 hover:border-emerald-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shadow-2xs">
              <i class="fa-solid fa-file-signature text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/60">BPLO & Permits</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Permit Clearance Controls</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Online assessment processing tracks for structural development and commercial business licensing.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Business Permit</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Clearance Filing</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-emerald-700 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 3. Social Welfare System -->
      <div class="bg-white border border-slate-200/80 hover:border-amber-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shadow-2xs">
              <i class="fa-solid fa-hand-holding-heart text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200/60">CSWDO Aid</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Social Welfare System</h4>
          <p class="text-xs text-slate-500 leading-relaxed">AICS financial assistance coordination paths alongside senior citizen & PWD support services management.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">AICS Assistance</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Senior & PWD Aid</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-amber-700 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 4. Health & Sanitation Management -->
      <div class="bg-white border border-slate-200/80 hover:border-rose-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-2xs">
              <i class="fa-solid fa-heart-pulse text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200/60">City Health</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Health & Sanitation</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Municipal health center documentation networks and sanitary inspection clearance registry systems.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Health Permits</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Sanitary Clearance</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-rose-700 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 5. Education & Scholarships -->
      <div class="bg-white border border-slate-200/80 hover:border-indigo-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-2xs">
              <i class="fa-solid fa-graduation-cap text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/60">Scholarships</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Education & Scholarships</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Application verification portals for students and institutional support asset allocation metrics.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Academic Aid</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Student Grants</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-indigo-700 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 6. Disaster Risk Reduction (DRRM) -->
      <div class="bg-white border border-slate-200/80 hover:border-red-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-red-50 border border-red-100 flex items-center justify-center text-red-600 shadow-2xs">
              <i class="fa-solid fa-shield-halved text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-red-50 text-red-700 border border-red-200/60">DRRMO Command</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Disaster Risk Reduction</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Real-time emergency broadcast notification paths, weather updates, and hazard layout registers.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">911 Emergency</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Hazard Alerts</span>
          </div>
          <a href="tel:911" class="text-[10px] font-bold text-red-700 hover:underline whitespace-nowrap ml-2">Call 911 <i class="fa-solid fa-phone text-[9px]"></i></a>
        </div>
      </div>

      <!-- 7. Urban Planning & Zoning -->
      <div class="bg-white border border-slate-200/80 hover:border-cyan-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-cyan-50 border border-cyan-100 flex items-center justify-center text-cyan-700 shadow-2xs">
              <i class="fa-solid fa-map-location-dot text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-cyan-50 text-cyan-800 border border-cyan-200/60">Zoning & CPDO</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Urban Planning & Zoning</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Zoning assessment structural matrices and infrastructure project development coordination.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Locational Clearance</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Site Inspection</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-cyan-800 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 8. Treasury & Revenue Processing -->
      <div class="bg-white border border-slate-200/80 hover:border-teal-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-700 shadow-2xs">
              <i class="fa-solid fa-cash-register text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-teal-50 text-teal-800 border border-teal-200/60">City Treasury</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Treasury & Revenue</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Digital bookkeeping for tax collection loops, real property tax, and electronic fee processing.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Real Property Tax</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Online Payments</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-teal-800 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 9. Transport & Mobility Control -->
      <div class="bg-white border border-slate-200/80 hover:border-purple-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-700 shadow-2xs">
              <i class="fa-solid fa-bus text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-purple-50 text-purple-800 border border-purple-200/60">Traffic & CPO</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Transport & Mobility</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Tricycle franchise database registries and municipal traffic regulation tracking structures.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Tricycle Franchise</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Traffic Memos</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-purple-800 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>

      <!-- 10. Public Asset Management -->
      <div class="bg-white border border-slate-200/80 hover:border-sky-500/60 rounded-2xl p-6 flex flex-col justify-between space-y-4 shadow-xs hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-10 w-10 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-700 shadow-2xs">
              <i class="fa-solid fa-warehouse text-sm"></i>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-sky-50 text-sky-800 border border-sky-200/60">Public Assets</span>
          </div>
          <h4 class="font-extrabold text-slate-900 text-base">Public Asset Management</h4>
          <p class="text-xs text-slate-500 leading-relaxed">Facility configuration reservation grids and municipal infrastructure inventory tracker setups.</p>
        </div>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div class="flex flex-wrap gap-1.5">
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Venue Reservation</span>
            <span class="text-[10px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Public Grounds</span>
          </div>
          <a href="login.php" class="text-[10px] font-bold text-sky-800 hover:underline whitespace-nowrap ml-2">Access <i class="fa-solid fa-arrow-right text-[9px]"></i></a>
        </div>
      </div>
    </div>
  </section>

  <!-- Mobile app showcase -->
  <section id="download-app" class="py-14 bg-gradient-to-br from-brand-dark to-slate-800 px-4 sm:px-6">
    <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
      <div class="md:col-span-8 space-y-4 text-center md:text-left">
        <span class="text-xs font-bold uppercase tracking-wider text-brand-medium bg-white/10 px-3 py-1 rounded-md border border-white/20" id="appBadge">Mobile Platform</span>
        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight" id="appTitle">Download Citizen Mobile App</h2>
        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-xl" id="appDesc">
          Gain direct mobile access to local permit tracking fields, emergency notices, and service scheduling pipelines. Scan the security barcode vector to safely pull down the native Android installation package.
        </p>
        <div class="pt-1 flex flex-wrap justify-center md:justify-start gap-2">
          <span class="text-[10px] font-mono font-bold uppercase px-2.5 py-1 bg-white/10 text-slate-200 border border-white/20 rounded">APK Build</span>
          <span class="text-[10px] font-mono font-bold uppercase px-2.5 py-1 bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 rounded">Verified Secure</span>
        </div>
      </div>
      <div class="md:col-span-4 flex flex-col items-center justify-center space-y-2 shrink-0">
        <div class="p-3 bg-white rounded-2xl shadow-lg border border-white/20">
          <img src="assets/images/qr.jpg" alt="QR Code to download the Civentral mobile app" loading="lazy" class="h-28 w-28 object-contain">
        </div>
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Scan to Install</span>
      </div>
    </div>
  </section>

  <!-- Contact -->
  <section id="contacts-registry" class="py-16 bg-slate-100 border-t border-slate-200 px-4 sm:px-6">
    <div class="max-w-7xl mx-auto space-y-8">
      <div class="space-y-2">
        <span class="text-xs font-bold uppercase tracking-wider text-brand-medium">LGU Communications Desk</span>
        <h2 class="text-2xl font-black text-slate-900 tracking-tight">Direct Department Communications</h2>
      </div>
      
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <!-- Mayor -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Office of the City Mayor</h3>
          <a href="tel:0283327711" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-7711</a>
          <a href="mailto:mayor@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> mayor@caloocancity.gov.ph</a>
        </div>
        <!-- Vice Mayor -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Office of the Vice Mayor</h3>
          <a href="tel:0283327712" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-7712</a>
          <a href="mailto:vicemayor@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> vicemayor@caloocancity.gov.ph</a>
        </div>
        <!-- Sangguniang Panlungsod -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Sangguniang Panlungsod</h3>
          <a href="tel:0283328841" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-8841</a>
          <a href="mailto:council@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> council@caloocancity.gov.ph</a>
        </div>
        <!-- Registry -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Civil Registry Department</h3>
          <a href="tel:0283321122" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-1122</a>
          <a href="mailto:registry@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> registry@caloocancity.gov.ph</a>
        </div>
        <!-- BPLO -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Business Permits & Licensing</h3>
          <a href="tel:0283324455" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-4455</a>
          <a href="mailto:bplo@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> bplo@caloocancity.gov.ph</a>
        </div>
        <!-- Welfare -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Social Welfare & Devt (CSWDO)</h3>
          <a href="tel:0283326677" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-6677</a>
          <a href="mailto:cswdo@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> cswdo@caloocancity.gov.ph</a>
        </div>
        <!-- Health -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">City Health Department</h3>
          <a href="tel:0283321024" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-1024</a>
          <a href="mailto:health@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> health@caloocancity.gov.ph</a>
        </div>
        <!-- Treasury -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">City Treasury Office</h3>
          <a href="tel:0283325512" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-5512</a>
          <a href="mailto:treasury@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> treasury@caloocancity.gov.ph</a>
        </div>
        <!-- Planning -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Urban Planning & Zoning</h3>
          <a href="tel:0283329900" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-9900</a>
          <a href="mailto:planning@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> planning@caloocancity.gov.ph</a>
        </div>
        <!-- Traffic -->
        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-1.5 shadow-xs hover:shadow-md hover:border-brand-border transition-all duration-200">
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Transport & Traffic Management</h3>
          <a href="tel:0283323344" class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 hover:text-brand-dark transition"><i class="fa-solid fa-phone opacity-60"></i> (02) 8332-3344</a>
          <a href="mailto:traffic@caloocancity.gov.ph" class="text-[11px] text-brand-dark font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> traffic@caloocancity.gov.ph</a>
        </div>
        <!-- DRRMO -->
        <div class="p-4 bg-red-50 border border-red-200/60 rounded-xl space-y-1.5 shadow-xs sm:col-span-2">
          <h3 class="text-xs font-black text-red-700 uppercase tracking-wide flex items-center gap-1.5"><i class="fa-solid fa-circle-exclamation text-[10px] animate-pulse"></i> DRRMO Emergency Command Center</h3>
          <a href="tel:911" class="text-xs font-bold text-red-700 flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-phone text-red-500"></i> Hotline 911 / 8288-2323</a>
          <a href="mailto:drrmo@caloocancity.gov.ph" class="text-[11px] text-slate-600 font-medium flex items-center gap-1.5 hover:underline"><i class="fa-solid fa-envelope opacity-60"></i> drrmo@caloocancity.gov.ph</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-brand-dark text-white/80 border-t-4 border-brand-medium pt-16 pb-12 px-4 sm:px-6">
    <div class="max-w-7xl mx-auto space-y-12">
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 border-b border-white/10 pb-12">
        <div class="space-y-4">
          <div class="flex items-center space-x-2 text-white">
            <img src="assets/images/logo.png" alt="Civentral" loading="lazy" class="h-8 w-auto object-contain brightness-200">
            <span class="text-lg font-black tracking-wide uppercase">Caloocan City</span>
          </div>
          <p class="text-xs leading-relaxed text-brand-light/70">
            Providing accessible, smart, and comprehensive administrative tracking environments for all municipal districts.
          </p>
          <!-- Social media links -->
          <div class="flex items-center gap-3 pt-1">
            <a href="https://www.facebook.com/CaloocancityPH" target="_blank" rel="noopener noreferrer" aria-label="Official Caloocan City Facebook Page" class="h-8 w-8 rounded-lg bg-white/10 hover:bg-brand-medium flex items-center justify-center text-white/70 hover:text-white transition">
              <i class="fa-brands fa-facebook-f text-xs"></i>
            </a>
            <a href="https://twitter.com/CaloocancityPH" target="_blank" rel="noopener noreferrer" aria-label="Official Caloocan City Twitter" class="h-8 w-8 rounded-lg bg-white/10 hover:bg-brand-medium flex items-center justify-center text-white/70 hover:text-white transition">
              <i class="fa-brands fa-x-twitter text-xs"></i>
            </a>
            <a href="https://www.youtube.com/@CaloocancityPH" target="_blank" rel="noopener noreferrer" aria-label="Official Caloocan City YouTube" class="h-8 w-8 rounded-lg bg-white/10 hover:bg-brand-medium flex items-center justify-center text-white/70 hover:text-white transition">
              <i class="fa-brands fa-youtube text-xs"></i>
            </a>
          </div>
        </div>
        <div class="space-y-3">
          <h4 class="text-xs font-bold uppercase text-white tracking-wider">Public Services</h4>
          <ul class="text-xs space-y-2 text-brand-light/70">
            <li><a href="#features" class="hover:text-white transition">Business Registrations</a></li>
            <li><a href="#features" class="hover:text-white transition">Real Property Tax Inquiries</a></li>
            <li><a href="#features" class="hover:text-white transition">Civil Registry Trackers</a></li>
            <li><a href="#features" class="hover:text-white transition">Social Welfare Assistance</a></li>
          </ul>
        </div>
        <div class="space-y-3">
          <h4 class="text-xs font-bold uppercase text-white tracking-wider">Government Links</h4>
          <ul class="text-xs space-y-2 text-brand-light/70">
            <li><a href="#" class="hover:text-white transition">Transparency Board</a></li>
            <li><a href="#" class="hover:text-white transition">Municipal Code Regulations</a></li>
            <li><a href="#announcements" class="hover:text-white transition">News & Announcements</a></li>
            <li><a href="login.php" class="hover:text-white text-brand-medium font-semibold transition">Administrative Login Portal</a></li>
          </ul>
        </div>
        <div class="space-y-3">
          <h4 class="text-xs font-bold uppercase text-white tracking-wider">City Hall Contact</h4>
          <p class="text-xs leading-relaxed text-brand-light/70">
            City Hall Complex, A. Mabini St.,<br>
            Caloocan City, Metro Manila,<br>
            Philippines
          </p>
          <a href="tel:0283327711" class="text-xs text-brand-light/70 hover:text-white transition flex items-center gap-1.5 mt-1">
            <i class="fa-solid fa-phone text-brand-medium"></i> (02) 8332-7711
          </a>
          <a href="mailto:mayor@caloocancity.gov.ph" class="text-xs text-brand-light/70 hover:text-white transition flex items-center gap-1.5">
            <i class="fa-solid fa-envelope text-brand-medium"></i> mayor@caloocancity.gov.ph
          </a>
        </div>
      </div>

      <div class="flex flex-col md:flex-row items-center justify-between space-y-6 md:space-y-0 text-center md:text-left">
        <div class="space-y-1 text-xs">
          <p class="text-brand-light font-medium">&copy; 2026 City Government of Caloocan. All Rights Reserved.</p>
          <p class="text-brand-light/50">Regulated under data infrastructure protection parameters per RA 8792.</p>
        </div>
        <div class="text-[10px] font-bold text-slate-800 tracking-wider max-w-sm border border-brand-border/40 rounded-lg p-3 bg-brand-light">
          DEPT ACCESS ONLY — UNAUTHORIZED USE IS LOGGED & PROSECUTABLE UNDER RA 8792
        </div>
      </div>

    </div>
  </footer>

  <!-- Back to Top Button -->
  <button
    id="backToTop"
    onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
    aria-label="Back to top"
    class="fixed bottom-6 right-6 z-50 h-11 w-11 bg-brand-dark hover:bg-brand-medium text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-300 opacity-0 translate-y-4 pointer-events-none"
  >
    <i class="fa-solid fa-arrow-up text-sm"></i>
  </button>

  <script src="assets/js/index.js"></script>
</body>
</html>