import os
import re

target_files = [
    'student_questionbank.php',
    'student_study_material.php',
    'student_syllabus.php',
    'student_queries.php',
    'student_lab.php',
    'student_raise_queries.php',
    'student_library.php'
]

# 1. Read student_dashboard.php
with open('student_dashboard.php', 'r', encoding='utf-8') as f:
    dashboard_content = f.read()

# Extract PHP top variables block from dashboard (from start up to <!DOCTYPE html>)
php_block_match = re.search(r'^(.*?)<!DOCTYPE html>', dashboard_content, re.DOTALL)
if php_block_match:
    dashboard_php = php_block_match.group(1)
else:
    print("Could not find PHP block in student_dashboard.php")
    exit(1)

# Extract layout from <!DOCTYPE html> up to <main class="main-content">
layout_match = re.search(r'(<!DOCTYPE html>.*?)<main class="main-content">', dashboard_content, re.DOTALL)
if layout_match:
    dashboard_layout = layout_match.group(1) + '<main class="main-content">'
else:
    print("Could not find layout in student_dashboard.php")
    exit(1)

# Ensure PHP block has notifications fetching logic
php_vars_required = """
// Fetch Student Name
$query = "SELECT name FROM student WHERE id='$id'";
$result = mysqli_query($conn, $query);
$studentName = ($result && $row = mysqli_fetch_assoc($result)) ? $row['name'] : "Student";

// Fetch Announcements Count (Unread)
$notifCountQuery = "SELECT COUNT(*) as total FROM announcements a WHERE a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE student_id = '$id')";
$notifCount = ($res = $conn->query($notifCountQuery)) ? $res->fetch_assoc()['total'] : 0;

// Fetch Latest 5 Announcements
$notifications = $conn->query("SELECT a.* FROM announcements a ORDER BY a.created_at DESC LIMIT 5");
"""

for fname in target_files:
    if not os.path.exists(fname):
        continue
        
    with open(fname, 'r', encoding='utf-8') as f:
        content = f.read()
    
    print(f"Processing {fname}...")
    
    # Check and inject PHP vars if missing
    if '$notifications = ' not in content:
        # Find session_start(); include("db.php"); or similar
        # Let's just inject right before <!DOCTYPE html>
        content = content.replace('<!DOCTYPE html>', php_vars_required + '\n?>\n<!DOCTYPE html>')
        # Remove the extra ?> if it causes issues, but we should probably do a smarter inject.
        # Actually, let's inject before ?>\n<!DOCTYPE html>
        content = re.sub(r'\?>\s*<!DOCTYPE html>', php_vars_required + '\n?>\n<!DOCTYPE html>', content)

    # Now replace the layout
    # The layout in the target file is from <!DOCTYPE html> to <main class="main-content"> (or <main...>)
    layout_target_match = re.search(r'<!DOCTYPE html>.*?(<main[^>]*>)', content, re.DOTALL)
    if layout_target_match:
        old_layout = layout_target_match.group(0)
        # Update active class in dashboard layout for this specific file
        # Dashboard layout has <a href="student_dashboard.php" class="active">
        # We need to move the active class to the link matching fname
        custom_layout = dashboard_layout
        custom_layout = custom_layout.replace('class="active"', '')
        custom_layout = custom_layout.replace(f'href="{fname}"', f'href="{fname}" class="active"')
        
        new_content = content.replace(old_layout, custom_layout)
        
        with open(fname, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Updated layout for {fname}")
    else:
        print(f"Could not find old layout in {fname}")

print("Done!")
