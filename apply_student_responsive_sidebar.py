import os

student_files = [
    "student_dashboard.php",
    "student_study_material.php",
    "student_questionbank.php",
    "student_syllabus.php",
    "student_alert.php",
    "student_lab.php",
    "student_library.php",
    "student_raise_queries.php",
    "student_profile.php",
    "includes/student_header.php"
]

base_dir = r"c:\xampp\htdocs\study_material_portal"

btn_code = """<button type="button" class="menu-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar Navigation" style="background: var(--primary); color: #ffffff; border: none; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px;"><i class="fa-solid fa-bars"></i></button>"""

js_code = """
        function toggleSidebar() {
            const sb = document.querySelector('.sidebar');
            const main = document.querySelector('.main-content');
            if (window.innerWidth <= 768) {
                if (sb) sb.classList.toggle('collapsed');
                if (sb) sb.classList.toggle('mobile-open');
            } else {
                if (sb) sb.classList.toggle('collapsed');
                if (main) main.classList.toggle('collapsed');
            }
        }
"""

css_code = """
        /* RESPONSIVE & COLLAPSIBLE SIDEBAR */
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .main-content {
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        .main-content.collapsed {
            margin-left: 0 !important;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                top: 72px;
                left: 0;
                height: calc(100vh - 72px);
                z-index: 1000;
                width: 260px !important;
            }
            .sidebar.collapsed, .sidebar.mobile-open {
                transform: translateX(0) !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 20px 15px;
            }
        }
"""

for fname in student_files:
    fpath = os.path.join(base_dir, fname)
    if not os.path.exists(fpath):
        continue

    with open(fpath, "r", encoding="utf-8") as f:
        content = f.read()

    # 1. Add toggle button to header-left
    if 'id="sidebarToggleBtn"' not in content and 'onclick="toggleSidebar()"' not in content:
        if '<div class="header-left">' in content:
            content = content.replace('<div class="header-left">', '<div class="header-left">\n            ' + btn_code)

    # 2. Add toggleSidebar JS function
    if 'function toggleSidebar()' not in content:
        if '</script>' in content:
            last_script = content.rfind('</script>')
            content = content[:last_script] + js_code + content[last_script:]

    # 3. Add responsive CSS rules before </style>
    if '/* RESPONSIVE & COLLAPSIBLE SIDEBAR */' not in content:
        if '</style>' in content:
            last_style = content.find('</style>')
            content = content[:last_style] + css_code + content[last_style:]

    with open(fpath, "w", encoding="utf-8") as f:
        f.write(content)

    print(f"Updated student file: {fname}")
