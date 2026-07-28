<?php
session_start();
require_once "includes/database.php";
require_once "includes/security.php";
require_once "includes/qr_generator.php";

if(!isset($_SESSION['student'])) {
    header("Location: student_login_new.php");
    exit();
}

$student_id = $_SESSION['student'];
$student = getStudentById($student_id);

if (!$student['success'] || !$student['data']) {
    die("Student data not found.");
}

$studentData = $student['data'];

// Generate/get QR code
$qrResult = generateStudentQR($student_id);
$qrPath = $qrResult['success'] ? $qrResult['path'] : 'generate_qr.php?id=' . $student_id;

// Calculate profile completion
function calculateProfileCompletion($student) {
    $fields = ['name', 'email', 'mobile', 'prn', 'roll_no', 'department', 'semester', 'dob', 'gender', 'blood_group', 'address', 'city', 'state', 'pincode', 'father_name', 'father_mobile', 'mother_name', 'mother_mobile'];
    $filled = 0;
    foreach ($fields as $field) {
        if (!empty($student[$field])) {
            $filled++;
        }
    }
    return round(($filled / count($fields)) * 100);
}

$profileCompletion = calculateProfileCompletion($studentData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | Zeal EduHub ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --dark-lighter: #1e293b;
            --gray: #64748b;
            --light: #f8fafc;
            --white: #ffffff;
            --gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            --shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 100px 20px 40px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
        }

        .page-header p {
            color: var(--gray);
            margin-top: 5px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ID Card Section */
        .id-card-section {
            background: var(--gradient);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .id-card-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .id-card-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            position: relative;
            z-index: 1;
        }

        .id-card-left {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .id-photo {
            width: 150px;
            height: 150px;
            border-radius: 20px;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .id-info h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .id-info .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .id-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .id-info-item label {
            display: block;
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 3px;
        }

        .id-info-item span {
            font-size: 16px;
            font-weight: 600;
        }

        .id-card-right {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .qr-code img {
            width: 150px;
            height: 150px;
            display: block;
        }

        .qr-caption {
            margin-top: 15px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Profile Sections Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: var(--primary);
        }

        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .info-table tr:last-child {
            border-bottom: none;
        }

        .info-table th {
            text-align: left;
            padding: 15px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray);
            width: 40%;
        }

        .info-table td {
            padding: 15px;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }

        /* Profile Completion */
        .completion-card {
            text-align: center;
        }

        .progress-circle {
            width: 180px;
            height: 180px;
            margin: 0 auto 20px;
            position: relative;
        }

        .progress-circle svg {
            transform: rotate(-90deg);
        }

        .progress-circle circle {
            fill: none;
            stroke-width: 12;
        }

        .progress-circle .bg {
            stroke: #e2e8f0;
        }

        .progress-circle .progress {
            stroke: var(--gradient);
            stroke-linecap: round;
            stroke-dasharray: 530;
            stroke-dashoffset: calc(530 - (530 * <?php echo $profileCompletion; ?>) / 100);
            transition: stroke-dashoffset 1s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 36px;
            font-weight: 800;
            color: var(--dark);
        }

        .completion-status {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 20px;
        }

        /* Document List */
        .document-list {
            list-style: none;
        }

        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: var(--light);
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .document-name {
            font-weight: 600;
            font-size: 14px;
        }

        .document-actions {
            display: flex;
            gap: 10px;
        }

        .document-actions a {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .view-btn {
            background: #dbeafe;
            color: #2563eb;
        }

        .delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--light);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }

        .stat-card i {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-card strong {
            display: block;
            font-size: 24px;
            font-weight: 800;
            color: var(--dark);
        }

        .stat-card span {
            font-size: 12px;
            color: var(--gray);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .id-card-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .id-card-left {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 100px 15px 20px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .header-actions {
                width: 100%;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header" data-aos="fade-up">
            <div>
                <h1>Student Profile</h1>
                <p>Manage your personal information and academic details</p>
            </div>
            <div class="header-actions">
                <a href="student_dashboard_new.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="edit_profile.php" class="btn btn-primary">
                    <i class="fa-solid fa-user-pen"></i> Edit Profile
                </a>
            </div>
        </div>

        <!-- Digital ID Card -->
        <div class="id-card-section" data-aos="fade-up" data-aos-delay="100">
            <div class="id-card-content">
                <div class="id-card-left">
                    <img src="<?php echo !empty($studentData['photo']) ? 'assets/uploads/photos/' . e($studentData['photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($studentData['name']) . '&background=6366f1&color=fff&size=200'; ?>" 
                         alt="Student Photo" class="id-photo">
                    <div class="id-info">
                        <h2><?php echo e($studentData['name']); ?></h2>
                        <span class="badge"><?php echo e($studentData['department'] ?? 'Information Technology'); ?></span>
                        <div class="id-info-grid">
                            <div class="id-info-item">
                                <label>PRN Number</label>
                                <span><?php echo e($studentData['prn'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="id-info-item">
                                <label>Roll Number</label>
                                <span><?php echo e($studentData['roll_no'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="id-info-item">
                                <label>Semester</label>
                                <span><?php echo e($studentData['semester'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="id-info-item">
                                <label>Blood Group</label>
                                <span><?php echo e($studentData['blood_group'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="id-card-right">
                    <div class="qr-code">
                        <img src="<?php echo $qrPath; ?>" alt="QR Code">
                    </div>
                    <p class="qr-caption"><i class="fa-solid fa-qrcode"></i> Scan to Verify</p>
                    <a href="download_id.php" class="btn" style="background: white; color: var(--primary); margin-top: 15px;">
                        <i class="fa-solid fa-download"></i> Download ID Card
                    </a>
                </div>
            </div>
        </div>

        <div class="profile-grid">
            <div class="left-column">
                <!-- Personal Information -->
                <div class="card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-user"></i> Personal Information</h2>
                    </div>
                    <table class="info-table">
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo e($studentData['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo e($studentData['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Mobile</th>
                            <td><?php echo e($studentData['mobile'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo e($studentData['dob'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?php echo e($studentData['gender'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Blood Group</th>
                            <td><?php echo e($studentData['blood_group'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>ABC ID</th>
                            <td><?php echo e($studentData['abc_id'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Academic Information -->
                <div class="card" data-aos="fade-up" data-aos-delay="250">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-graduation-cap"></i> Academic Information</h2>
                    </div>
                    <table class="info-table">
                        <tr>
                            <th>PRN Number</th>
                            <td><?php echo e($studentData['prn'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Roll Number</th>
                            <td><?php echo e($studentData['roll_no'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td><?php echo e($studentData['department'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Semester</th>
                            <td><?php echo e($studentData['semester'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>CGPA</th>
                            <td><?php echo e($studentData['cgpa'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Attendance</th>
                            <td><?php echo e($studentData['attendance'] ?? 'N/A'); ?>%</td>
                        </tr>
                    </table>
                </div>

                <!-- Parent Details -->
                <div class="card" data-aos="fade-up" data-aos-delay="300">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-users"></i> Parent Details</h2>
                    </div>
                    <table class="info-table">
                        <tr>
                            <th>Father Name</th>
                            <td><?php echo e($studentData['father_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Father Mobile</th>
                            <td><?php echo e($studentData['father_mobile'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Father Occupation</th>
                            <td><?php echo e($studentData['father_occupation'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Mother Name</th>
                            <td><?php echo e($studentData['mother_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Mother Mobile</th>
                            <td><?php echo e($studentData['mother_mobile'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Mother Occupation</th>
                            <td><?php echo e($studentData['mother_occupation'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Address -->
                <div class="card" data-aos="fade-up" data-aos-delay="350">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-location-dot"></i> Address</h2>
                    </div>
                    <table class="info-table">
                        <tr>
                            <th>Address</th>
                            <td><?php echo e($studentData['address'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>City</th>
                            <td><?php echo e($studentData['city'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>State</th>
                            <td><?php echo e($studentData['state'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Pincode</th>
                            <td><?php echo e($studentData['pincode'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="right-column">
                <!-- Profile Completion -->
                <div class="card completion-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-chart-pie"></i> Profile Completion</h2>
                    </div>
                    <div class="progress-circle">
                        <svg width="180" height="180">
                            <circle class="bg" cx="90" cy="90" r="85"></circle>
                            <circle class="progress" cx="90" cy="90" r="85"></circle>
                        </svg>
                        <div class="progress-text"><?php echo $profileCompletion; ?>%</div>
                    </div>
                    <p class="completion-status">Complete your profile to unlock all features</p>
                    <a href="edit_profile.php" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-user-pen"></i> Complete Profile
                    </a>
                </div>

                <!-- Quick Stats -->
                <div class="card" data-aos="fade-up" data-aos-delay="250">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-chart-simple"></i> Quick Stats</h2>
                    </div>
                    <div class="stats-row">
                        <div class="stat-card">
                            <i class="fa-solid fa-book"></i>
                            <strong><?php echo count(getStudentEnrolledSubjects($studentId)['data'] ?? []); ?></strong>
                            <span>Subjects</span>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-file-lines"></i>
                            <strong><?php echo dbCount('study_materials'); ?></strong>
                            <span>Materials</span>
                        </div>
                        <div class="stat-card">
                            <i class="fa-solid fa-trophy"></i>
                            <strong>8.75</strong>
                            <span>CGPA</span>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="card" data-aos="fade-up" data-aos-delay="300">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-folder-open"></i> Documents</h2>
                    </div>
                    <ul class="document-list">
                        <?php if (!empty($studentData['aadhaar_file'])): ?>
                        <li class="document-item">
                            <div class="document-info">
                                <div class="document-icon"><i class="fa-solid fa-id-card"></i></div>
                                <span class="document-name">Aadhaar Card</span>
                            </div>
                            <div class="document-actions">
                                <a href="assets/uploads/aadhaar/<?php echo e($studentData['aadhaar_file']); ?>" target="_blank" class="view-btn">View</a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($studentData['pan_file'])): ?>
                        <li class="document-item">
                            <div class="document-info">
                                <div class="document-icon"><i class="fa-solid fa-file"></i></div>
                                <span class="document-name">PAN Card</span>
                            </div>
                            <div class="document-actions">
                                <a href="assets/uploads/pan/<?php echo e($studentData['pan_file']); ?>" target="_blank" class="view-btn">View</a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($studentData['ssc_file'])): ?>
                        <li class="document-item">
                            <div class="document-info">
                                <div class="document-icon"><i class="fa-solid fa-certificate"></i></div>
                                <span class="document-name">SSC Marksheet</span>
                            </div>
                            <div class="document-actions">
                                <a href="assets/uploads/ssc/<?php echo e($studentData['ssc_file']); ?>" target="_blank" class="view-btn">View</a>
                            </div>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($studentData['hsc_file'])): ?>
                        <li class="document-item">
                            <div class="document-info">
                                <div class="document-icon"><i class="fa-solid fa-certificate"></i></div>
                                <span class="document-name">HSC Marksheet</span>
                            </div>
                            <div class="document-actions">
                                <a href="assets/uploads/hsc/<?php echo e($studentData['hsc_file']); ?>" target="_blank" class="view-btn">View</a>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (empty($studentData['aadhaar_file']) && empty($studentData['pan_file']) && empty($studentData['ssc_file']) && empty($studentData['hsc_file'])): ?>
                        <p style="text-align: center; color: var(--gray);">No documents uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });
    </script>
</body>
</html>
