</main> <!-- Closes .main-content -->

    <!-- ── THEME SELECTION MODAL ────────────────────────────────────────── -->
    <div id="themeModal" class="custom-modal-overlay">
        <div class="custom-modal-card">
            <div class="custom-modal-header">
                <h3><i class="fa-solid fa-palette" style="color:var(--primary);"></i> Select Theme</h3>
                <button type="button" class="custom-modal-close" onclick="closeThemeModal()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <div class="theme-grid">
                    <div class="theme-opt" onclick="applyTheme('light'); closeThemeModal();">
                        <span style="background:#10b981;"></span> Emerald (Default)
                    </div>
                    <div class="theme-opt" onclick="applyTheme('dark'); closeThemeModal();">
                        <span style="background:#6366f1;"></span> Dark Indigo
                    </div>
                    <div class="theme-opt" onclick="applyTheme('sunset'); closeThemeModal();">
                        <span style="background:#ea580c;"></span> Sunset Orange
                    </div>
                    <div class="theme-opt" onclick="applyTheme('ocean'); closeThemeModal();">
                        <span style="background:#0891b2;"></span> Ocean Cyan
                    </div>
                    <div class="theme-opt" onclick="applyTheme('midnight'); closeThemeModal();">
                        <span style="background:#818cf8;"></span> Midnight Dark
                    </div>
                    <div class="theme-opt" onclick="applyTheme('forest'); closeThemeModal();">
                        <span style="background:#15803d;"></span> Forest Green
                    </div>
                    <div class="theme-opt" onclick="applyTheme('pink'); closeThemeModal();">
                        <span style="background:#ec4899;"></span> Soft Pink
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn-modal-close" onclick="closeThemeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- ── HELP & SUPPORT MODAL ─────────────────────────────────────────── -->
    <div id="helpModal" class="custom-modal-overlay">
        <div class="custom-modal-card">
            <div class="custom-modal-header">
                <h3><i class="fa-solid fa-headset" style="color:var(--primary);"></i> Help & Support</h3>
                <button type="button" class="custom-modal-close" onclick="closeHelpModal()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">
                    For portal assistance, course inquiries, or technical support, contact us directly:
                </p>
                
                <div class="support-info-item">
                    <i class="fa-solid fa-envelope"></i>
                    <a href="mailto:kanthale@gmail.com" style="color:inherit; text-decoration:none;">kanthale@gmail.com</a>
                </div>
                
                <div class="support-info-item">
                    <i class="fa-solid fa-phone"></i>
                    <a href="tel:+918551082199" style="color:inherit; text-decoration:none;">+91 8551082199</a>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn-modal-close" onclick="closeHelpModal()">Close</button>
            </div>
        </div>
    </div>

    <style>
        /* ── POPUP OVERLAY STYLES (PREVENTS PAGE OVERLAP BUG) ─────────────── */
        .custom-modal-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(15, 23, 42, 0.6) !important;
            backdrop-filter: blur(4px);
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 99999 !important;
            padding: 20px;
        }

        .custom-modal-overlay.active {
            display: flex !important;
        }

        .custom-modal-card {
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 20px;
            width: min(100%, 460px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalPopIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPopIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .custom-modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border, #e2e8f0);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .custom-modal-header h3 {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .custom-modal-close {
            background: none;
            border: none;
            font-size: 22px;
            color: var(--text-muted, #64748b);
            cursor: pointer;
            line-height: 1;
        }

        .custom-modal-close:hover {
            color: var(--text-main, #1e293b);
        }

        .custom-modal-body {
            padding: 22px;
        }

        /* ── THEME GRID STYLES ────────────────────────────────────────────── */
        .theme-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .theme-opt {
            padding: 12px;
            border-radius: 12px;
            border: 1.5px solid var(--border, #e2e8f0);
            background: var(--bg, #f3f4f9);
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main, #1e293b);
            transition: all 0.2s ease;
        }

        .theme-opt:hover {
            border-color: var(--primary, #10b981);
            transform: translateY(-2px);
        }

        .theme-opt span {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .support-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg, #f3f4f9);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main, #1e293b);
            border: 1px solid var(--border, #e2e8f0);
        }

        .support-info-item i {
            color: var(--primary, #10b981);
            font-size: 16px;
        }

        .custom-modal-footer {
            padding: 14px 22px;
            background: var(--bg, #f3f4f9);
            border-top: 1px solid var(--border, #e2e8f0);
            text-align: right;
        }

        .btn-modal-close {
            background: var(--primary, #10b981);
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-modal-close:hover {
            opacity: 0.9;
        }
    </style>

    <script>
        // ── THEME ENGINE ───────────────────────────────────────────────────
        const themes = {
            light: { primary: '#10b981', bg: '#f3f4f9', header: '#ffffff', sidebar: '#15803d', card: '#ffffff', text: '#1e293b', muted: '#64748b', border: '#e2e8f0', glow: 'rgba(16, 185, 129, 0.2)' },
            dark: { primary: '#6366f1', bg: '#0f172a', header: '#0f172a', sidebar: '#1e293b', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99, 102, 241, 0.3)' },
            sunset: { primary: '#ea580c', bg: '#fff7ed', header: '#ffffff', sidebar: '#7c2d12', card: '#ffffff', text: '#431407', muted: '#9a6a52', border: '#fed7aa', glow: 'rgba(234, 88, 12, 0.2)' },
            ocean: { primary: '#0891b2', bg: '#ecfeff', header: '#ffffff', sidebar: '#164e63', card: '#ffffff', text: '#083344', muted: '#5b8a99', border: '#a5f3fc', glow: 'rgba(8, 145, 178, 0.2)' },
            midnight: { primary: '#818cf8', bg: '#030712', header: '#0b0f19', sidebar: '#111827', card: '#111827', text: '#f9fafb', muted: '#9ca3af', border: '#1f2937', glow: 'rgba(129, 140, 248, 0.3)' },
            forest: { primary: '#15803d', bg: '#f0fdf4', header: '#ffffff', sidebar: '#14532d', card: '#ffffff', text: '#052e16', muted: '#4d7c62', border: '#bbf7d0', glow: 'rgba(21, 128, 61, 0.2)' },
            pink: { primary: '#ec4899', bg: '#fff5f7', header: '#ffffff', sidebar: '#be185d', card: '#ffffff', text: '#4a1034', muted: '#9f4b70', border: '#fbcfe8', glow: 'rgba(236, 72, 153, 0.2)' }
        };

        function applyTheme(themeKey) {
            const t = themes[themeKey] || themes.light;
            const r = document.documentElement;
            r.style.setProperty('--primary', t.primary);
            r.style.setProperty('--bg', t.bg);
            r.style.setProperty('--header-bg', t.header);
            r.style.setProperty('--sidebar-bg', t.sidebar);
            r.style.setProperty('--card-bg', t.card);
            r.style.setProperty('--text-main', t.text);
            r.style.setProperty('--text-muted', t.muted);
            r.style.setProperty('--border', t.border);
            r.style.setProperty('--glow', t.glow);
            document.body.setAttribute('data-theme', themeKey);
            localStorage.setItem('user-theme', themeKey);
        }

        // Initialize Theme from localStorage immediately
        applyTheme(localStorage.getItem('user-theme') || 'light');

        // ── MODAL CONTROLLERS ──────────────────────────────────────────────
        function openThemeModal() {
            const m = document.getElementById('themeModal');
            if (m) m.classList.add('active');
        }

        function closeThemeModal() {
            const m = document.getElementById('themeModal');
            if (m) m.classList.remove('active');
        }

        function openHelpModal() {
            const m = document.getElementById('helpModal');
            if (m) m.classList.add('active');
        }

        function closeHelpModal() {
            const m = document.getElementById('helpModal');
            if (m) m.classList.remove('active');
        }

        // Close on backdrop click
        document.addEventListener('click', function(e) {
            const tm = document.getElementById('themeModal');
            const hm = document.getElementById('helpModal');
            if (tm && e.target === tm) closeThemeModal();
            if (hm && e.target === hm) closeHelpModal();
        });
    </script>
</body>
</html>