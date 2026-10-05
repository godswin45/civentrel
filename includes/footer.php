  </div>

  <script src="<?php echo $basePath ?? '../'; ?>assets/js/dashboard.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/dashboard.js') ?: time(); ?>"></script>
  <style>
    dialog.civ-confirm { margin: auto; }
    dialog.civ-confirm::backdrop { background: rgba(15, 23, 42, .55); backdrop-filter: blur(3px); }
    dialog.civ-confirm[open] { animation: civPop .18s ease-out; }
    @keyframes civPop { from { opacity: 0; transform: translateY(8px) scale(.97); } to { opacity: 1; transform: none; } }
  </style>
  <script src="<?php echo $basePath ?? '../'; ?>assets/js/form-ux.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/form-ux.js') ?: time(); ?>"></script>
</body>
</html>