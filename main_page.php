<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZEALHUB | Academic Study & Governance Portal</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-gradient: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
            --student-color: #10b981;
            --student-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --staff-color: #3b82f6;
            --staff-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --admin-color: #ef4444;
            --admin-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            /* Bright, vibrant academic background image with light glassmorphism overlay */
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.45) 0%, rgba(238, 242, 255, 0.5) 40%, rgba(67, 97, 238, 0.25) 100%), 
                        url('https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1920&q=80') center/cover no-repeat fixed;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }

        /* Top Glass Navigation Header */
        .top-navbar {
            width: 100%;
            height: 72px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--primary);
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
        }

        .nav-logo-icon {
            width: 42px;
            height: 42px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .nav-btn {
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .nav-btn-student {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .nav-btn-student:hover {
            background: var(--student-color);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .nav-btn-staff {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.25);
        }
        .nav-btn-staff:hover {
            background: var(--staff-color);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .nav-btn-admin {
            background: var(--admin-gradient);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .nav-btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Ambient Glowing Orbs */
        body::before {
            content: "";
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(67, 97, 238, 0.25);
            filter: blur(140px);
            border-radius: 50%;
            top: -100px;
            left: -150px;
            pointer-events: none;
        }

        body::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(16, 185, 129, 0.2);
            filter: blur(140px);
            border-radius: 50%;
            bottom: -100px;
            right: -150px;
            pointer-events: none;
        }

        /* Main Container */
        .wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            flex: 1;
        }

        .container {
            width: 1180px;
            max-width: 100%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 32px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.18);
            border: 1.5px solid rgba(255, 255, 255, 0.8);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 10;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Left Hero Panel */
        .left {
            width: 46%;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #3a0ca3 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 55px 45px;
            position: relative;
            overflow: hidden;
        }

        .left::after {
            content: "";
            position: absolute;
            bottom: -80px;
            right: -80px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 7px 16px;
            border-radius: 30px;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            width: fit-content;
            margin-bottom: 25px;
        }

        .left h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 38px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 18px;
            letter-spacing: -0.5px;
        }

        .left p {
            font-size: 15px;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 35px;
            color: rgba(255, 255, 255, 0.85);
        }

        .features {
            list-style: none;
        }

        .features li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 16px;
            font-size: 14.5px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 14px 18px;
            border-radius: 18px;
            transition: all 0.3s ease;
        }

        .features li:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateX(8px);
        }

        .features i {
            font-size: 18px;
            color: #34d399;
        }

        .left-footer {
            margin-top: 30px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.65);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        /* Right Panel Cards */
        .right {
            width: 54%;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .header-box {
            margin-bottom: 30px;
            text-align: center;
        }

        .header-box h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 30px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header-box p {
            color: var(--text-muted);
            font-size: 14.5px;
            font-weight: 500;
        }

        .login-cards {
            display: grid;
            gap: 18px;
        }

        .login-option {
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 22px 24px;
            border-radius: 22px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .login-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.08);
            background: #ffffff;
        }

        .login-option.student:hover {
            border-color: var(--student-color);
            box-shadow: 0 18px 35px rgba(16, 185, 129, 0.18);
        }

        .login-option.staff:hover {
            border-color: var(--staff-color);
            box-shadow: 0 18px 35px rgba(59, 130, 246, 0.18);
        }

        .login-option.admin {
            border-color: rgba(239, 68, 68, 0.3);
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
        }

        .login-option.admin:hover {
            border-color: var(--admin-color);
            box-shadow: 0 18px 35px rgba(239, 68, 68, 0.22);
        }

        .icon-box {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 20px;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
        }

        .student-icon { background: rgba(16, 185, 129, 0.15); color: var(--student-color); }
        .staff-icon { background: rgba(59, 130, 246, 0.15); color: var(--staff-color); }
        .admin-icon { background: rgba(239, 68, 68, 0.15); color: var(--admin-color); }

        .option-text h3 {
            color: var(--text-dark);
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 3px;
        }

        .option-text p {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
        }

        .arrow {
            margin-left: auto;
            color: #94a3b8;
            font-size: 16px;
            transition: 0.3s ease;
        }

        .login-option:hover .arrow {
            transform: translateX(6px);
        }

        .student:hover .arrow { color: var(--student-color); }
        .staff:hover .arrow { color: var(--staff-color); }
        .admin:hover .arrow { color: var(--admin-color); }

        .footer {
            margin-top: 35px;
            text-align: center;
            font-size: 12.5px;
            color: #94a3b8;
            font-weight: 600;
        }

        /* Mobile Optimization */
        @media (max-width: 992px) {
            .top-navbar { padding: 0 20px; }
            .container { flex-direction: column; width: 100%; }
            .left, .right { width: 100%; padding: 40px 25px; }
            .left h1 { font-size: 32px; }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Header -->
    <header class="top-navbar">
        <a href="main_page.php" class="nav-logo">
            <div class="nav-logo-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <span>ZEALHUB</span>
        </a>
        <div class="nav-actions">
            <a href="student_login.php" class="nav-btn nav-btn-student"><i class="fa-solid fa-user-graduate"></i> Student</a>
            <a href="staff_login.php" class="nav-btn nav-btn-staff"><i class="fa-solid fa-chalkboard-user"></i> Faculty</a>
            <a href="admin_login.php" class="nav-btn nav-btn-admin"><i class="fa-solid fa-shield-halved"></i> Admin Login</a>
        </div>
    </header>

    <div class="wrapper">
        <div class="container">
            <!-- Informative Left Panel -->
            <div class="left">
                <div>
                    <div class="brand-badge">
                        <i class="fa-solid fa-graduation-cap"></i> ZEALHUB PORTAL
                    </div>

                    <h1>Academic Resource & Governance Hub</h1>
                    <p>A unified platform for study materials, practice question banks, real-time query support, and administrative control.</p>
                    
                    <ul class="features">
                        <li>
                            <i class="fa-solid fa-circle-check"></i>
                            <span><strong>Study Materials & PDFs:</strong> Download verified subject notes instantly.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check"></i>
                            <span><strong>Question Banks:</strong> Access previous year papers & model solutions.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-circle-check"></i>
                            <span><strong>Interactive Compiler & Lab:</strong> Practice programming exercises live.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-shield-halved"></i>
                            <span><strong>Admin Governance:</strong> Full student & staff data management & logs audit.</span>
                        </li>
                    </ul>
                </div>

                <div class="left-footer">
                    <i class="fa-solid fa-shield-cat"></i> Secured by ZEALHUB Academic Portal &copy; <?= date('Y') ?>
                </div>
            </div>

            <!-- Actionable Right Panel -->
            <div class="right">
                <div class="header-box">
                    <h2>Welcome Back</h2>
                    <p>Select your portal access level to continue</p>
                </div>

                <div class="login-cards">
                    <!-- Student Portal Card -->
                    <a href="student_login.php" class="login-option student">
                        <div class="icon-box student-icon">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div class="option-text">
                            <h3>Student Portal</h3>
                            <p>Access notes, question banks, labs & submit queries.</p>
                        </div>
                        <i class="fa-solid fa-arrow-right arrow"></i>
                    </a>

                    <!-- Staff Portal Card -->
                    <a href="staff_login.php" class="login-option staff">
                        <div class="icon-box staff-icon">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div class="option-text">
                            <h3>Faculty & Staff Portal</h3>
                            <p>Manage study materials, question papers & student queries.</p>
                        </div>
                        <i class="fa-solid fa-arrow-right arrow"></i>
                    </a>

                    <!-- Admin Portal Card (Connected directly to admin_login.php) -->
                    <a href="admin_login.php" class="login-option admin">
                        <div class="icon-box admin-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div class="option-text">
                            <h3>Admin Governance Portal</h3>
                            <p>Manage student/staff accounts, upload bulk data & audit logs.</p>
                        </div>
                        <i class="fa-solid fa-arrow-right arrow"></i>
                    </a>
                </div>

                <div class="footer">
                    &copy; <?= date('Y') ?> ZEALHUB Academic Portal • Authorized Access Only
                </div>
            </div>
        </div>
    </div>

</body>
</html>