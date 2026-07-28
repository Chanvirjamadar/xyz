<?php
session_start();

if (!isset($_SESSION['student'])) {
    header("Location: student_login.php");
    exit();
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/security.php";

$id = (int)$_SESSION['student'];

$studRes = dbSelectOne(
    "SELECT s.name, s.email, sp.*
     FROM student s
     LEFT JOIN student_profile sp ON CAST(sp.student_id AS UNSIGNED) = s.id
     WHERE s.id = ?",
    [$id], 'i'
);

if (!$studRes['success'] || !$studRes['data']) {
    $studRes = dbSelectOne("SELECT id AS student_id, name, email FROM student WHERE id = ?", [$id], 'i');
    if (!$studRes['success'] || !$studRes['data']) {
        die("Student Record Not Found. Please <a href='student_login.php'>login again</a>.");
    }
}
$student = $studRes['data'];

function uploadFile($inputName,$folder) {
    if(isset($_FILES[$inputName]) && $_FILES[$inputName]['error']==0) {
        $filename = time()."_".basename($_FILES[$inputName]['name']);
        $targetDir = "assets/uploads/".$folder;
        if(!is_dir($targetDir)) { mkdir($targetDir, 0777, true); }
        $target = $targetDir."/".$filename;
        $allowed = ['pdf','jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
        if(!in_array($ext,$allowed)) { die("Invalid File Type"); }
        if($_FILES[$inputName]['size'] > 5242880) { die("Maximum 5MB File Allowed"); }
        if(move_uploaded_file($_FILES[$inputName]['tmp_name'],$target)) { return $filename; }
        else { die("File upload failed: ".$target); }
    }
    return "";
}

if(isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    if(!preg_match("/^[A-Za-z ]+$/",$name)) { die("Invalid Name"); }
    if(!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|in|org)$/",$email)) { die("Invalid Email Format"); }
    if(!preg_match("/^[6-9][0-9]{9}$/",$_POST['mobile'])) { die("Invalid Mobile Number"); }
    if(!preg_match("/^[6-9][0-9]{9}$/",$_POST['father_mobile'])) { die("Invalid Father Mobile Number"); }
    if(!preg_match("/^[6-9][0-9]{9}$/",$_POST['mother_mobile'])) { die("Invalid Mother Mobile Number"); }
    if(!preg_match("/^[6-9][0-9]{9}$/", $_POST['emergency_mobile'])) { die("Invalid Emergency Contact"); }
    if(!preg_match("/^[0-9]{12,20}$/",$_POST['abc'])) { die("Invalid ABC ID"); }
    if(!preg_match("/^[0-9]{6}$/",$_POST['pincode'])) { die("Invalid Pincode"); }
    if(!preg_match("/^[A-Za-z ]+$/",$_POST['father'])) { die("Invalid Father Name"); }
    if(!preg_match("/^[A-Za-z ]+$/",$_POST['mother'])) { die("Invalid Mother Name"); }
    if(!preg_match("/^[A-Za-z .-]+$/",$_POST['father_occupation'])) { die("Invalid Father Occupation"); }
    if(!preg_match("/^[A-Za-z .-]+$/",$_POST['mother_occupation'])) { die("Invalid Mother Occupation"); }

    $dob = $_POST['dob'] ?? $student['dob'];
    if(strtotime($dob)>time()) { die("Future Date Not Allowed"); }
    $gender = $_POST['gender'] ?? $student['gender'];
    $blood = $_POST['blood'] ?? $student['blood_group'];
    $mobile = $_POST['mobile'] ?? $student['mobile'];
    $_POST['abc'] = str_replace(' ','',$_POST['abc']);
    $abc = $_POST['abc'] ?? $student['abc_id'];
    $address = $_POST['address'] ?? $student['address'];
    $city = $_POST['city'] ?? $student['city'];
    $state = $_POST['state'] ?? $student['state'];
    $pincode = $_POST['pincode'] ?? $student['pincode'];
    $father = $_POST['father'] ?? $student['father_name'];
    $father_mobile = $_POST['father_mobile'] ?? $student['father_mobile'];
    $mother = $_POST['mother'] ?? $student['mother_name'];
    $mother_mobile = $_POST['mother_mobile'] ?? $student['mother_mobile'];
    $father_occupation = $_POST['father_occupation'] ?? $student['father_occupation'];
    $mother_occupation = $_POST['mother_occupation'] ?? $student['mother_occupation'];
    $medical = $_POST['medical'] ?? ($student['medical_condition'] ?? 'None');
    $emergency = trim($_POST['emergency_mobile'] ?? ($student['emergency_contact'] ?? ''));

    $photo = uploadFile('photo', 'photos');

    $stmt = $conn->prepare("UPDATE student SET name = ?, email = ? WHERE id = ?");
    if (!$stmt) { die('DB Error: ' . $conn->error); }
    $stmt->bind_param('ssi', $name, $email, $id);
    $stmt->execute();
    $stmt->close();

    $idStr = (string)$id;
    $stmt  = $conn->prepare("SELECT student_id FROM student_profile WHERE student_id = ? OR CAST(student_id AS UNSIGNED) = ?");
    if (!$stmt) { die('DB Error: ' . $conn->error); }
    $stmt->bind_param('si', $idStr, $id);
    $stmt->execute();
    $result    = $stmt->get_result();
    $checkProf = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($checkProf) {
        $sql = "UPDATE student_profile SET
            mobile = ?, dob = ?, gender = ?, blood_group = ?, abc_id = ?, address = ?, city = ?, state = ?, pincode = ?, father_name = ?, father_mobile = ?, father_occupation = ?, mother_name = ?, mother_mobile = ?, mother_occupation = ?, medical_condition = ?, emergency_contact = ?";
        $params = [$mobile, $dob, $gender, $blood, $abc, $address, $city, $state, $pincode, $father, $father_mobile, $father_occupation, $mother, $mother_mobile, $mother_occupation, $medical, $emergency];
        $types  = 'sssssssssssssssss';
        if (!empty($photo)) { $sql .= ", photo = ?"; $params[] = $photo; $types .= 's'; }
        $sql .= " WHERE CAST(student_id AS UNSIGNED) = ? OR student_id = ?";
        $params[] = $id; $params[] = (string)$id; $types .= 'is';
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die('DB Error: ' . $conn->error); }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    } else {
        $sidStr = (string)$id;
        $sql = "INSERT INTO student_profile (student_id, mobile, dob, gender, blood_group, abc_id, address, city, state, pincode, father_name, father_mobile, father_occupation, mother_name, mother_mobile, mother_occupation, medical_condition, emergency_contact, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$sidStr, $mobile, $dob, $gender, $blood, $abc, $address, $city, $state, $pincode, $father, $father_mobile, $father_occupation, $mother, $mother_mobile, $mother_occupation, $medical, $emergency, $photo];
        $types  = 'sssssssssssssssssss';
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die('DB Error: ' . $conn->error); }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
    echo "<script>alert('Profile & Emergency Contact Updated Successfully!');window.location='student_profile.php';</script>";
}

// Fetch Notifications for Header
$notifCountQuery = "SELECT COUNT(*) as total FROM announcements a WHERE a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE student_id = '$id')";
$notifCount = ($res = $conn->query($notifCountQuery)) ? $res->fetch_assoc()['total'] : 0;
$notifications = $conn->query("SELECT a.* FROM announcements a ORDER BY a.created_at DESC LIMIT 5");

$studentName = $student['name'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | ZEALHUB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #ea580c;
            --bg: #fff7ed;
            --sidebar-bg: #7c2d12;
            --header-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #431407;
            --text-muted: #9a6a52;
            --border: #fed7aa;
            --glow: rgba(234, 88, 12, 0.2);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.3s, color 0.3s; }
        body { background: var(--bg); color: var(--text-main); overflow-x: hidden; }

        /* HEADER & SIDEBAR */
        .header { height: 75px; background: var(--header-bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 25px; position: fixed; top: 0; width: 100%; z-index: 1000; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .logo { font-size: 22px; font-weight: 800; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .menu-btn { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 10px; cursor: pointer; }
        .header-right { display: flex; align-items: center; gap: 12px; position: relative; }
        .icon-btn { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border); width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; }
        .icon-btn:hover { background: var(--primary); color: white; }
        .notif-dropdown { position: absolute; top: 56px; right: 100px; width: 320px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 10px 24px rgba(0,0,0,0.12); display: none; z-index: 1200; overflow: hidden; }
        .notif-dropdown.show { display: block; }
        .notif-header { padding: 14px 16px; border-bottom: 1px solid var(--border); font-weight: 700; display: flex; justify-content: space-between; }
        .notif-item { padding: 12px 16px; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit; display: block; }
        .notif-item:hover { background: rgba(0,0,0,0.03); }
        .notif-item strong { display: block; font-size: 13px; margin-bottom: 4px; }
        .notif-item small { color: var(--text-muted); font-size: 11px; }
        .profile-pill { background: var(--card-bg); border: 1px solid var(--border); padding: 5px 15px; border-radius: 15px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; }
        .avatar { width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; }

        .sidebar { width: 70px; height: 100vh; background: var(--sidebar-bg); position: fixed; top: 0; left: 0; padding-top: 85px; display: flex; flex-direction: column; align-items: center; z-index: 999; }
        .sidebar a { color: rgba(255,255,255,0.5); text-decoration: none; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px; margin-bottom: 20px; font-size: 18px; }
        .sidebar a.active { background: var(--primary); color: white; }
        .sidebar a:hover:not(.active) { color: white; background: rgba(255,255,255,0.1); }

        .main-content { margin-left: 70px; margin-top: 75px; padding: 40px; min-height: calc(100vh - 75px); }
        
        .edit-container { max-width: 900px; margin: auto; background: var(--card-bg); padding: 40px; border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); }
        .page-title { font-size: 28px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; color: var(--text-main); }
        .btn-cancel { background: var(--bg); border: 1px solid var(--border); color: var(--text-main); text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }
        .btn-cancel:hover { background: var(--border); }

        h2 { color: var(--primary); margin-top: 35px; margin-bottom: 20px; font-size: 18px; border-bottom: 1px solid var(--border); padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full { grid-column: 1 / -1; }
        
        label { font-weight: 700; font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        input, textarea, select { width: 100%; padding: 14px 16px; border: 1px solid var(--border); background: var(--bg); color: var(--text-main); border-radius: 12px; font-size: 15px; outline: none; transition: all 0.2s; }
        input:focus, textarea:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--glow); background: var(--card-bg); }
        textarea { resize: vertical; min-height: 100px; }

        .btn-submit { margin-top: 40px; padding: 16px 30px; background: var(--primary); color: white; border: none; border-radius: 14px; cursor: pointer; font-size: 16px; font-weight: 800; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.2s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px var(--glow); }

        .photo-preview-box { display: flex; align-items: center; gap: 15px; padding: 15px; background: var(--bg); border: 1px dashed var(--border); border-radius: 12px; }
        .photo-preview-box img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); background: var(--card-bg); }

        /* THEME MODAL */
        .theme-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; }
        .theme-modal.active { display: flex; }
        .theme-card { background: var(--card-bg); padding: 30px; border-radius: 24px; width: min(90%, 420px); text-align: center; border: 1px solid var(--border); }
        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .theme-opt { padding: 15px; border-radius: 15px; border: 2px solid var(--border); cursor: pointer; font-weight: 700; display: flex; align-items: center; gap: 10px; color: var(--text-main); }
        .theme-opt:hover { border-color: var(--primary); background: var(--bg); }
    </style>
</head>
<body>

    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
            <a href="#" class="logo"><i class="fa-solid fa-graduation-cap"></i> ZEALHUB</a>
        </div>
        <div class="header-right">
            <button class="icon-btn" id="themeBtn" type="button" aria-label="Choose theme"><i class="fa-solid fa-palette" id="themeIcon"></i></button>
            <button class="icon-btn" id="notifBtn" type="button" aria-label="Notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if($notifCount > 0): ?><span style="position:absolute; top:-5px; right:-5px; background:red; color:white; font-size:10px; padding:2px 6px; border-radius:50%;"><?= $notifCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header"><span>Notifications</span><span><?= $notifCount ?></span></div>
                <?php if($notifications && $notifications->num_rows > 0): while($n = $notifications->fetch_assoc()): ?>
                    <a class="notif-item" href="student_queries.php">
                        <strong><?= htmlspecialchars($n['title']) ?></strong>
                        <small><?= htmlspecialchars(substr($n['message'], 0, 60)) ?>...</small>
                    </a>
                <?php endwhile; else: ?>
                    <div class="notif-item"><strong>No notifications yet</strong><small>New announcements will appear here.</small></div>
                <?php endif; ?>
            </div>
            <a href="student_profile.php" class="profile-pill">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800; color: var(--text-main);"><?= $studentName ?></p>
                    <p style="font-size: 9px; color: var(--text-muted);">STUDENT</p>
                </div>
                <div class="avatar">ST</div>
            </a>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <a href="student_dashboard.php"><i class="fa-solid fa-house"></i> </a>
        <a href="student_profile.php" class="active"><i class="fa-solid fa-user"></i> </a>
        <a href="student_logout.php" style="margin-top:auto; margin-bottom:20px;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
    </aside>

    <main class="main-content">
        <div class="edit-container">
            <div class="page-title">
                Edit Profile
                <a href="student_profile.php" class="btn-cancel"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
            </div>

            <form method="POST" enctype="multipart/form-data">
                
                <h2><i class="fa-solid fa-camera"></i> Profile Photo</h2>
                <div class="form-group full">
                    <label>Upload Student Photo</label>
                    <?php if(!empty($student['photo'])): ?>
                    <div class="photo-preview-box">
                        <img src="assets/uploads/photos/<?= htmlspecialchars($student['photo']); ?>" alt="Current Photo">
                        <div>
                            <div style="font-weight:700; font-size:14px; color:var(--text-main);">Current Photo</div>
                            <div style="font-size: 12px; color: var(--text-muted);">You can upload a new photo to replace this.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="photo" accept="image/png, image/jpeg, image/jpg, image/webp" style="background:var(--card-bg);">
                </div>

                <h2><i class="fa-solid fa-user"></i> Basic Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required maxlength="50" pattern="[A-Za-z ]+" oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required maxlength="100" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" oninput="this.value=this.value.replace(/\s/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="tel" name="mobile" value="<?= htmlspecialchars($student['mobile'] ?? '') ?>" required maxlength="10" pattern="[6-9]{1}[0-9]{9}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>ABC ID</label>
                        <input type="text" name="abc" value="<?= htmlspecialchars($student['abc_id'] ?? '') ?>" maxlength="20" required pattern="[0-9]{12,20}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" value="<?= htmlspecialchars($student['dob'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?= (($student['gender']??"")=="Male")?"selected":"" ?>>Male</option>
                            <option value="Female" <?= (($student['gender']??"")=="Female")?"selected":"" ?>>Female</option>
                            <option value="Other" <?= (($student['gender']??"")=="Other")?"selected":"" ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group</label>
                        <select name="blood" required>
                            <option value="">Select Blood Group</option>
                            <option value="A+" <?= (($student['blood_group']??"")=="A+")?"selected":"" ?>>A+</option>
                            <option value="A-" <?= (($student['blood_group']??"")=="A-")?"selected":"" ?>>A-</option>
                            <option value="B+" <?= (($student['blood_group']??"")=="B+")?"selected":"" ?>>B+</option>
                            <option value="B-" <?= (($student['blood_group']??"")=="B-")?"selected":"" ?>>B-</option>
                            <option value="AB+" <?= (($student['blood_group']??"")=="AB+")?"selected":"" ?>>AB+</option>
                            <option value="AB-" <?= (($student['blood_group']??"")=="AB-")?"selected":"" ?>>AB-</option>
                            <option value="O+" <?= (($student['blood_group']??"")=="O+")?"selected":"" ?>>O+</option>
                            <option value="O-" <?= (($student['blood_group']??"")=="O-")?"selected":"" ?>>O-</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Address</label>
                        <textarea name="address" required maxlength="250"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <select name="state" id="state" required>
                            <option value="">Select State</option>
                            <option value="Maharashtra" <?= (($student['state']??"")=="Maharashtra")?"selected":"" ?>>Maharashtra</option>
                            <option value="Gujarat" <?= (($student['state']??"")=="Gujarat")?"selected":"" ?>>Gujarat</option>
                            <option value="Rajasthan" <?= (($student['state']??"")=="Rajasthan")?"selected":"" ?>>Rajasthan</option>
                            <option value="Karnataka" <?= (($student['state']??"")=="Karnataka")?"selected":"" ?>>Karnataka</option>
                            <option value="Goa" <?= (($student['state']??"")=="Goa")?"selected":"" ?>>Goa</option>
                            <option value="Delhi" <?= (($student['state']??"")=="Delhi")?"selected":"" ?>>Delhi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <select name="city" id="city" required>
                            <option value="">Select City</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" value="<?= htmlspecialchars($student['pincode'] ?? '') ?>" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                </div>

                <h2><i class="fa-solid fa-users"></i> Parents Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Father Name</label>
                        <input type="text" name="father" value="<?= htmlspecialchars($student['father_name'] ?? '') ?>" required maxlength="50" pattern="[A-Za-z ]+" oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Father Mobile</label>
                        <input type="tel" name="father_mobile" value="<?= htmlspecialchars($student['father_mobile'] ?? '') ?>" required maxlength="10" pattern="[6-9]{1}[0-9]{9}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Father Occupation</label>
                        <input type="text" name="father_occupation" value="<?= htmlspecialchars($student['father_occupation'] ?? '') ?>" required maxlength="40" pattern="[A-Za-z .-]+" oninput="this.value=this.value.replace(/[^A-Za-z .-]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Mother Name</label>
                        <input type="text" name="mother" value="<?= htmlspecialchars($student['mother_name'] ?? '') ?>" required maxlength="50" pattern="[A-Za-z ]+" oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Mother Mobile</label>
                        <input type="tel" name="mother_mobile" value="<?= htmlspecialchars($student['mother_mobile'] ?? '') ?>" required maxlength="10" pattern="[6-9]{1}[0-9]{9}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Mother Occupation</label>
                        <input type="text" name="mother_occupation" value="<?= htmlspecialchars($student['mother_occupation'] ?? '') ?>" required maxlength="40" pattern="[A-Za-z .-]+" oninput="this.value=this.value.replace(/[^A-Za-z .-]/g,'')">
                    </div>
                </div>

                <h2><i class="fa-solid fa-heart-pulse"></i> Medical Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Medical Condition</label>
                        <select name="medical" required>
                            <option value="None" <?= (($student['medical_condition']??"")=="None")?"selected":"" ?>>None</option>
                            <option value="Asthma" <?= (($student['medical_condition']??"")=="Asthma")?"selected":"" ?>>Asthma</option>
                            <option value="Diabetes" <?= (($student['medical_condition']??"")=="Diabetes")?"selected":"" ?>>Diabetes</option>
                            <option value="Heart Disease" <?= (($student['medical_condition']??"")=="Heart Disease")?"selected":"" ?>>Heart Disease</option>
                            <option value="Blood Pressure" <?= (($student['medical_condition']??"")=="Blood Pressure")?"selected":"" ?>>Blood Pressure</option>
                            <option value="Other" <?= (($student['medical_condition']??"")=="Other")?"selected":"" ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="tel" name="emergency_mobile" value="<?= htmlspecialchars($student['emergency_contact'] ?? '') ?>" required maxlength="10" pattern="[6-9]{1}[0-9]{9}" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                </div>

                <button type="submit" name="update" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Update Profile</button>
            </form>
        </div>
    </main>

    <!-- Theme Modal -->
    <div id="themeModal" class="theme-modal">
        <div class="theme-card">
            <h3 style="color:var(--text-main); margin-bottom:10px;">Choose a Theme</h3>
            <div class="theme-grid">
                <div class="theme-opt" data-theme="light"><span style="width:15px;height:15px;background:#4361ee;border-radius:50%;"></span> Light</div>
                <div class="theme-opt" data-theme="dark"><span style="width:15px;height:15px;background:#0f172a;border-radius:50%;"></span> Dark</div>
                <div class="theme-opt" data-theme="sunset"><span style="width:15px;height:15px;background:#ea580c;border-radius:50%;"></span> Sunset</div>
                <div class="theme-opt" data-theme="ocean"><span style="width:15px;height:15px;background:#0891b2;border-radius:50%;"></span> Ocean</div>
                <div class="theme-opt" data-theme="midnight"><span style="width:15px;height:15px;background:#1e293b;border-radius:50%;"></span> Midnight</div>
                <div class="theme-opt" data-theme="forest"><span style="width:15px;height:15px;background:#15803d;border-radius:50%;"></span> Forest</div>
                <div class="theme-opt" data-theme="pink"><span style="width:15px;height:15px;background:#ec4899;border-radius:50%;"></span> Light Pink</div>
            </div>
            <button type="button" id="closeThemeModal" style="margin-top:20px; width:100%; padding:10px; border:none; background:var(--primary); color:white; border-radius:10px; cursor:pointer; font-weight:700;">Close</button>
        </div>
    </div>

    <script>
        // Sidebar
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        if (menuBtn && sidebar) menuBtn.addEventListener('click', () => sidebar.classList.toggle('expanded'));

        // Notifications
        const notifBtn = document.getElementById('notifBtn');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifBtn) notifBtn.addEventListener('click', (e) => { e.stopPropagation(); notifDropdown.classList.toggle('show'); });
        document.addEventListener('click', (e) => { if (notifDropdown && !notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) notifDropdown.classList.remove('show'); });

        // Themes
        const themeBtn = document.getElementById('themeBtn');
        const themeIcon = document.getElementById('themeIcon');
        const themeModal = document.getElementById('themeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModal');

        const themes = {
            light: { primary: '#4361ee', bg: '#f3f4f9', sidebar: '#ffffff', header: '#ffffff', card: '#ffffff', text: '#1e293b', muted: '#64748b', border: '#e2e8f0', glow: 'rgba(67, 97, 238, 0.2)' },
            dark: { primary: '#6366f1', bg: '#0f172a', sidebar: '#1e293b', header: '#0f172a', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99, 102, 241, 0.2)' },
            sunset: { primary: '#ea580c', bg: '#fff7ed', sidebar: '#7c2d12', header: '#ffffff', card: '#ffffff', text: '#431407', muted: '#9a6a52', border: '#fed7aa', glow: 'rgba(234, 88, 12, 0.2)' },
            ocean: { primary: '#0891b2', bg: '#ecfeff', sidebar: '#164e63', header: '#ffffff', card: '#ffffff', text: '#083344', muted: '#5b8a99', border: '#a5f3fc', glow: 'rgba(8, 145, 178, 0.2)' },
            midnight: { primary: '#6366f1', bg: '#0f172a', sidebar: '#1e293b', header: '#0f172a', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99, 102, 241, 0.2)' },
            forest: { primary: '#15803d', bg: '#f0fdf4', sidebar: '#14532d', header: '#ffffff', card: '#ffffff', text: '#052e16', muted: '#4d7c62', border: '#bbf7d0', glow: 'rgba(21, 128, 61, 0.2)' },
            pink: { primary: '#ec4899', bg: '#fff5f7', sidebar: '#be185d', header: '#ffffff', card: '#ffffff', text: '#4a1034', muted: '#9f4b70', border: '#fbcfe8', glow: 'rgba(236, 72, 153, 0.2)' }
        };

        function applyTheme(key) {
            const selected = themes[key] || themes.light;
            const root = document.documentElement;
            root.style.setProperty('--primary', selected.primary);
            root.style.setProperty('--bg', selected.bg);
            root.style.setProperty('--sidebar-bg', selected.sidebar);
            root.style.setProperty('--header-bg', selected.header);
            root.style.setProperty('--card-bg', selected.card);
            root.style.setProperty('--text-main', selected.text);
            root.style.setProperty('--text-muted', selected.muted);
            root.style.setProperty('--border', selected.border);
            root.style.setProperty('--glow', selected.glow);
            document.body.setAttribute('data-theme', key);
            if (themeIcon) themeIcon.className = 'fa-solid fa-palette';
        }

        if (themeBtn) themeBtn.addEventListener('click', (e) => { e.stopPropagation(); themeModal.classList.add('active'); });
        if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', () => themeModal.classList.remove('active'));
        document.querySelectorAll('.theme-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                applyTheme(opt.dataset.theme);
                localStorage.setItem('user-theme', opt.dataset.theme);
                themeModal.classList.remove('active');
            });
        });
        document.addEventListener('click', (e) => { if (themeModal && themeModal.contains(e.target) || themeBtn?.contains(e.target)) return; themeModal.classList.remove('active'); });

        const savedTheme = localStorage.getItem('user-theme') || 'light';
        applyTheme(savedTheme);

        // City Selector Logic
        let cities = {
            "Maharashtra": ["Pune", "Mumbai", "Nashik", "Nagpur"],
            "Gujarat": ["Ahmedabad", "Surat", "Vadodara", "Rajkot", "Gandhinagar"],
            "Rajasthan": ["Jaipur", "Jodhpur", "Kota", "Udaipur"],
            "Karnataka": ["Bangalore", "Mysore", "Mangalore"],
            "Goa": ["Panaji", "Margao"],
            "Delhi": ["New Delhi"]
        };
        let state = document.getElementById("state");
        let city = document.getElementById("city");
        function loadCities() {
            city.innerHTML = '<option value="">Select City</option>';
            let list = cities[state.value];
            if(list) {
                list.forEach(function(item){
                    let option = document.createElement("option");
                    option.value = item;
                    option.text = item;
                    if(item == "<?= addslashes($student['city'] ?? '') ?>") {
                        option.selected = true;
                    }
                    city.appendChild(option);
                });
            }
        }
        state.addEventListener("change", loadCities);
        loadCities();
    </script>
</body>
</html>