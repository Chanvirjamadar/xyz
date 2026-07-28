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
        
    print(f"Refactoring {fname}...")
    
    # 1. Replace Top HTML Layout (<!DOCTYPE html> ... <main...>) with include
    # We regex search from <!DOCTYPE html> to <main[^>]*>
    content = re.sub(
        r'<!DOCTYPE html>.*?(<main[^>]*>)',
        "include('includes/student_header.php');\n?>\n\\1",
        content,
        flags=re.DOTALL
    )
    
    # Clean up double closing/opening PHP tags if produced
    content = content.replace('?>\ninclude(\'includes/student_header.php\');\n?>', 'include(\'includes/student_header.php\');\n?>')
    content = content.replace('?>\r\ninclude(\'includes/student_header.php\');\r\n?>', 'include(\'includes/student_header.php\');\n?>')
    
    # 2. Replace Bottom Layout (</main> ... </html>) with include
    content = re.sub(
        r'</main>.*$',
        "</main>\n<?php include('includes/student_footer.php'); ?>",
        content,
        flags=re.DOTALL
    )
    
    with open(fname, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print(f"Successfully refactored {fname}")

print("All done!")
