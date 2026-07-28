import os
import re

base_dir = r"c:\xampp\htdocs\study_material_portal"
staff_files = [
    "staff_dashboard.php",
    "staff_studymaterial.php",
    "staff_questionbank.php",
    "staff_syllabus.php",
    "staff_queries.php",
    "staff_alert.php",
    "staff_lab.php",
    "staff_library.php",
    "staff_profile.php",
    "staff_help.php"
]

notif_code_pattern = re.compile(
    r'(// Ensure Staff Announcement Reads Table Exists.*?\nif \(\$notifications\) \{.*?\n\})',
    re.DOTALL
)

for fname in staff_files:
    fpath = os.path.join(base_dir, fname)
    if not os.path.exists(fpath):
        continue

    with open(fpath, "r", encoding="utf-8") as f:
        content = f.read()

    match = notif_code_pattern.search(content)
    if not match:
        print(f"No match in {fname}")
        continue

    notif_block = match.group(1)

    # Remove the notification block from wherever it is
    content_clean = content.replace(notif_block, "")

    # Now find the position where $staffID or $staffName is defined after db.php include
    # We want to place notif_block right after $staffID is set!
    pos_staff_id = content_clean.find("$staffID = ")
    if pos_staff_id != -1:
        # Find end of that line
        end_line = content_clean.find("\n", pos_staff_id)
        # Check if $staffName line is right after
        pos_staff_name = content_clean.find("$staffName = ", end_line)
        if pos_staff_name != -1 and pos_staff_name - end_line < 100:
            end_line = content_clean.find("\n", pos_staff_name)

        new_content = content_clean[:end_line+1] + "\n" + notif_block + "\n" + content_clean[end_line+1:]
    else:
        # Fallback: after include("db.php");
        pos_db = content_clean.find('include("db.php");')
        if pos_db == -1:
            pos_db = content_clean.find("include('db.php');")
        if pos_db == -1:
            pos_db = content_clean.find("require_once")

        if pos_db != -1:
            end_line = content_clean.find("\n", pos_db)
            new_content = content_clean[:end_line+1] + "\n" + notif_block + "\n" + content_clean[end_line+1:]
        else:
            print(f"Could not find insert pos in {fname}")
            continue

    with open(fpath, "w", encoding="utf-8") as f:
        f.write(new_content)

    print(f"Fixed order in {fname}")
