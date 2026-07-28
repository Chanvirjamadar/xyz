import os
import re

target_files = [
    'student_dashboard.php',
    'student_questionbank.php',
    'student_study_material.php',
    'student_syllabus.php',
    'student_queries.php',
    'student_lab.php',
    'student_raise_queries.php',
    'student_library.php'
]

for fname in target_files:
    if not os.path.exists(fname):
        continue
        
    with open(fname, 'r', encoding='utf-8') as f:
        content = f.read()
        
    print(f"Fixing {fname}...")
    
    # Replace plain text include('includes/student_header.php'); outside php tags
    # Case 1: ?>\ninclude('includes/student_header.php');\n?>
    # Case 2: ?>\r\ninclude('includes/student_header.php');\r\n?>
    # Case 3: include('includes/student_header.php'); appearing outside <?php ?>
    
    content = re.sub(
        r'\?>\s*include\([\'"]includes/student_header\.php[\'"]\);\s*\?>',
        "include('includes/student_header.php');\n?>",
        content
    )
    
    # In case include('includes/student_header.php'); is floating without <?php
    if "<?php include('includes/student_header.php'); ?>" not in content and "include('includes/student_header.php');" in content:
        # Check if it is inside <?php
        php_blocks = re.findall(r'<\?php(.*?)\?>', content, re.DOTALL)
        inside_php = any("include('includes/student_header.php');" in block for block in php_blocks)
        if not inside_php:
            content = content.replace("include('includes/student_header.php');", "<?php include('includes/student_header.php'); ?>")
            
    with open(fname, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f"Fixed {fname}")

print("All fixed!")
