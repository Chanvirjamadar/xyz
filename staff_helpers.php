<?php

function staffEsc($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function requireStaffAuth() {
    if (!isset($_SESSION['staff'])) {
        header('Location: staff_login.php');
        exit();
    }
}

function getStaffProfile($conn, $staffID) {
    $staffID = mysqli_real_escape_string($conn, $staffID);
    $query = "SELECT * FROM staff_profile WHERE staff_id = '$staffID' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

function getStaffPhotoUrl($staffRow) {
    if (!empty($staffRow['photo'])) {
        return 'uploads/staff/' . $staffRow['photo'];
    }

    return 'assets/uploads/photos/default-avatar.png';
}

function getSubjectsList($conn, $department) {
    $department = mysqli_real_escape_string($conn, $department);
    $query = "SELECT * FROM subjects WHERE branch = '$department' ORDER BY subject_name ASC";
    $result = $conn->query($query);

    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    return [];
}

function getStaffUploadStats($conn, $staffName) {
    $staffName = mysqli_real_escape_string($conn, $staffName);
    $stats = ['materials' => 0, 'question_bank' => 0];

    $materialsQuery = "SELECT COUNT(*) as total FROM study_materials WHERE uploaded_by = '$staffName' OR uploaded_by LIKE '%$staffName%'";
    $qbankQuery = "SELECT COUNT(*) as total FROM question_bank WHERE uploaded_by = '$staffName' OR uploaded_by LIKE '%$staffName%'";

    $materialsResult = $conn->query($materialsQuery);
    if ($materialsResult && $materialsResult->num_rows > 0) {
        $stats['materials'] = (int) $materialsResult->fetch_assoc()['total'];
    }

    $qbankResult = $conn->query($qbankQuery);
    if ($qbankResult && $qbankResult->num_rows > 0) {
        $stats['question_bank'] = (int) $qbankResult->fetch_assoc()['total'];
    }

    return $stats;
}

function renderStaffLayoutStart($title, $activePage, $staffRow) {
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . staffEsc($title) . '</title>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<style>body{font-family:"Plus Jakarta Sans",sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);color:#1f2937;margin:0;padding:0;} .page-shell{max-width:1280px;margin:0 auto;padding:24px 20px 40px;} .staff-card{background:#fff;border-radius:22px;padding:24px;box-shadow:0 20px 45px rgba(15,23,42,.08);margin-bottom:20px;border:1px solid #e5e7eb;} .profile-edit-header{display:flex;justify-content:space-between;align-items:center;margin:10px 0 20px;gap:12px;flex-wrap:wrap;} .page-header h1{margin:0 0 6px;font-size:30px;color:#111827;} .page-header p{margin:0;color:#64748b;} .btn-primary,.btn-success,.btn-secondary{border:none;padding:11px 16px;border-radius:999px;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;gap:8px;} .btn-primary{background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;} .btn-success{background:linear-gradient(135deg,#059669,#10b981);color:#fff;} .btn-secondary{background:#eef2ff;color:#3730a3;} .profile-info-table{width:100%;border-collapse:collapse;} .profile-info-table th,.profile-info-table td{padding:12px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;} .profile-info-table th{color:#64748b;font-weight:700;width:180px;} .editable{transition:all .2s ease;} .editable:disabled{color:#111827;background:transparent;border:none;opacity:1;} .editable:not(:disabled){background:#f8fafc;border:1px solid #dbeafe;border-radius:8px;padding:8px 10px;} .content-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;} .subject-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;} .subject-card{border:1px solid #e5e7eb;border-radius:14px;padding:14px;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);} .subject-card .code{font-size:12px;font-weight:800;letter-spacing:.2em;color:#4f46e5;margin-bottom:8px;text-transform:uppercase;} .subject-card h4{margin:0 0 8px;font-size:17px;color:#111827;} .meta-row{display:flex;gap:8px;flex-wrap:wrap;color:#64748b;font-size:13px;} .empty-state{padding:12px 0;color:#64748b;} .id-card-wrapper{background:linear-gradient(135deg,#111827 0%,#312e81 50%,#4338ca 100%);color:#fff;border-radius:28px;padding:24px;box-shadow:0 25px 60px rgba(15,23,42,.18);margin-bottom:8px;} .id-card-header,.id-card-footer,.id-card-body{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;} .id-card-body{margin:20px 0 12px;align-items:flex-start;} .id-left-details{display:flex;gap:18px;align-items:center;flex-wrap:wrap;} .id-photo-frame{width:130px;height:150px;border-radius:18px;overflow:hidden;border:3px solid rgba(255,255,255,.28);background:#fff;} .id-photo-frame img{width:100%;height:100%;object-fit:cover;} .id-brand{display:flex;align-items:center;gap:12px;} .id-brand-icon{width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:18px;} .id-brand-title{font-size:20px;font-weight:800;} .id-brand-subtitle{font-size:12px;color:#dbeafe;opacity:.9;} .id-security-badge{background:rgba(255,255,255,.16);padding:8px 12px;border-radius:999px;font-size:12px;font-weight:700;} .id-student-name{margin:0 0 6px;font-size:22px;} .id-dept-pill{display:inline-flex;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.15);font-size:12px;font-weight:700;margin-bottom:10px;} .id-meta-row{display:grid;grid-template-columns:repeat(2,minmax(140px,1fr));gap:10px 16px;} .id-meta-label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#c7d2fe;opacity:.9;margin-bottom:4px;} .id-meta-value{font-size:14px;font-weight:700;} .id-qr-box{background:#fff;padding:12px;border-radius:16px;display:inline-block;} .id-qr-caption{font-size:13px;margin-top:8px;text-align:center;color:#e0e7ff;} .id-download-btn{color:#fff;text-decoration:none;background:rgba(255,255,255,.18);padding:8px 12px;border-radius:999px;} .file-upload-btn{display:block;margin:0 0 18px;padding:10px 12px;border:1px dashed #c7d2fe;border-radius:12px;background:#f8fbff;color:#4338ca;cursor:pointer;} .alert{padding:14px 16px;border-radius:14px;margin-bottom:16px;font-weight:600;} .alert-success{background:#ecfdf5;color:#065f46;} .alert-error{background:#fef2f2;color:#991b1b;} .badge-resolved{display:inline-flex;padding:6px 10px;border-radius:999px;background:#ecfdf5;color:#065f46;font-weight:700;} @media (max-width: 720px){.page-shell{padding:16px 12px 30px;} .content-grid{grid-template-columns:1fr;} .id-card-wrapper{padding:18px;} .id-meta-row{grid-template-columns:1fr;}} </style>';
    echo '</head>';
    echo '<body>';
}

function renderStaffLayoutEnd() {
    echo '</body>';
    echo '</html>';
}
