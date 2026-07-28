<?php
session_start();
header('Content-Type: text/plain; charset=utf-8');

// Fixed path: files are in root, not a subdirectory
require_once __DIR__ . '/db.php';

// Auto-create required tables if missing
$conn->query("CREATE TABLE IF NOT EXISTS `coding_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT DEFAULT 1,
    `language` VARCHAR(50) DEFAULT NULL,
    `code` LONGTEXT DEFAULT NULL,
    `program_input` TEXT DEFAULT NULL,
    `program_output` TEXT DEFAULT NULL,
    `output` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `coding_drafts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT DEFAULT 1,
    `language` VARCHAR(50) NOT NULL,
    `code` LONGTEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_draft` (`student_id`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Determine student_id (staff also uses this; default to 1)
$student_id = 1;
if (isset($_SESSION['student'])) {
    $student_id = intval($_SESSION['student']);
} elseif (isset($_SESSION['student_id'])) {
    $student_id = intval($_SESSION['student_id']);
} elseif (isset($_SESSION['staff'])) {
    $student_id = 0; // Staff run — store as student_id=0
}

// Parse input: supports both JSON body and form POST
$code     = '';
$language = '';
$input    = '';

$rawInput = file_get_contents('php://input');
$json     = json_decode($rawInput, true);

if ($json && is_array($json)) {
    $code     = isset($json['code'])     ? $json['code']                   : '';
    $language = isset($json['language']) ? strtolower(trim($json['language'])) : '';
    $input    = isset($json['input'])    ? $json['input']                  : '';
} else {
    $code     = isset($_POST['code'])     ? $_POST['code']                   : '';
    $language = isset($_POST['language']) ? strtolower(trim($_POST['language'])) : '';
    $input    = isset($_POST['input'])    ? $_POST['input']                  : '';
}

if (empty(trim($code))) {
    echo "Error: Code cannot be empty.";
    exit();
}

// ── Piston API Fallback Engine ──────────────────────────────────────────────
function runPistonAPI($language, $code, $input) {
    $langMap = [
        'c'          => ['language' => 'c', 'version' => '10.2.0'],
        'cpp'        => ['language' => 'c++', 'version' => '10.2.0'],
        'java'       => ['language' => 'java', 'version' => '15.0.2'],
        'python'     => ['language' => 'python', 'version' => '3.10.0'],
        'php'        => ['language' => 'php', 'version' => '8.2.3'],
        'javascript' => ['language' => 'javascript', 'version' => '18.15.0']
    ];

    if (!isset($langMap[$language])) {
        return null;
    }

    $pistonLang = $langMap[$language]['language'];
    $version    = $langMap[$language]['version'];

    $payload = [
        'language' => $pistonLang,
        'version'  => $version,
        'files'    => [
            [
                'name'    => ($language === 'java' ? 'Main.java' : 'main'),
                'content' => $code
            ]
        ],
        'stdin'    => $input
    ];

    $ch = curl_init('https://emkc.org/api/v2/piston/execute');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return null;
    }

    $resData = json_decode($response, true);
    if (isset($resData['run']['output'])) {
        return trim($resData['run']['output']);
    }

    return null;
}

// ── Inline Code Execution ──────────────────────────────────────────────────
function executeCode($language, $code, $input) {
    $output = '';

    // Create a secure temporary directory
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zealhub_' . uniqid();
    if (!mkdir($tmpDir, 0777, true)) {
        return "Error: Could not create temp directory for execution.";
    }

    $inputFile = $tmpDir . DIRECTORY_SEPARATOR . 'input.txt';
    file_put_contents($inputFile, $input);

    try {
        switch ($language) {
            case 'python': {
                $srcFile = $tmpDir . DIRECTORY_SEPARATOR . 'main.py';
                file_put_contents($srcFile, $code);
                $cmd = 'python "' . $srcFile . '" < "' . $inputFile . '" 2>&1';
                exec($cmd, $outArr, $retCode);
                $output = implode("\n", $outArr);
                if ($retCode !== 0 || empty(trim($output))) {
                    $pistonOut = runPistonAPI('python', $code, $input);
                    if ($pistonOut !== null) $output = $pistonOut;
                }
                break;
            }
            case 'php': {
                $srcFile = $tmpDir . DIRECTORY_SEPARATOR . 'main.php';
                file_put_contents($srcFile, $code);
                $cmd = 'php "' . $srcFile . '" < "' . $inputFile . '" 2>&1';
                exec($cmd, $outArr, $retCode);
                $output = implode("\n", $outArr);
                if ($retCode !== 0 || empty(trim($output))) {
                    $pistonOut = runPistonAPI('php', $code, $input);
                    if ($pistonOut !== null) $output = $pistonOut;
                }
                break;
            }
            case 'javascript': {
                $srcFile = $tmpDir . DIRECTORY_SEPARATOR . 'main.js';
                file_put_contents($srcFile, $code);
                $cmd = 'node "' . $srcFile . '" < "' . $inputFile . '" 2>&1';
                exec($cmd, $outArr, $retCode);
                $output = implode("\n", $outArr);
                if ($retCode !== 0 || empty(trim($output))) {
                    $pistonOut = runPistonAPI('javascript', $code, $input);
                    if ($pistonOut !== null) {
                        $output = $pistonOut;
                    } else {
                        $output = "Error: Node.js is not installed locally and API fallback reached timeout.";
                    }
                }
                break;
            }
            case 'c': {
                $srcFile = $tmpDir . DIRECTORY_SEPARATOR . 'main.c';
                $binFile = $tmpDir . DIRECTORY_SEPARATOR . 'main_out' . (stripos(PHP_OS, 'WIN') === 0 ? '.exe' : '');
                file_put_contents($srcFile, $code);
                $compileCmd = 'gcc "' . $srcFile . '" -o "' . $binFile . '" 2>&1';
                exec($compileCmd, $compileOut, $compileCode);
                if ($compileCode !== 0) {
                    $pistonOut = runPistonAPI('c', $code, $input);
                    if ($pistonOut !== null) {
                        $output = $pistonOut;
                    } else {
                        $output = "Compilation Error:\n" . implode("\n", $compileOut);
                    }
                } else {
                    exec('"' . $binFile . '" < "' . $inputFile . '" 2>&1', $outArr);
                    $output = implode("\n", $outArr);
                }
                break;
            }
            case 'cpp': {
                $srcFile = $tmpDir . DIRECTORY_SEPARATOR . 'main.cpp';
                $binFile = $tmpDir . DIRECTORY_SEPARATOR . 'main_out' . (stripos(PHP_OS, 'WIN') === 0 ? '.exe' : '');
                file_put_contents($srcFile, $code);
                $compileCmd = 'g++ "' . $srcFile . '" -o "' . $binFile . '" 2>&1';
                exec($compileCmd, $compileOut, $compileCode);
                if ($compileCode !== 0) {
                    $pistonOut = runPistonAPI('cpp', $code, $input);
                    if ($pistonOut !== null) {
                        $output = $pistonOut;
                    } else {
                        $output = "Compilation Error:\n" . implode("\n", $compileOut);
                    }
                } else {
                    exec('"' . $binFile . '" < "' . $inputFile . '" 2>&1', $outArr);
                    $output = implode("\n", $outArr);
                }
                break;
            }
            case 'java': {
                $srcFile = $tmpDir . DIRECTORY_SEPARATOR . 'Main.java';
                file_put_contents($srcFile, $code);
                $compileCmd = 'javac "' . $srcFile . '" 2>&1';
                exec($compileCmd, $compileOut, $compileCode);
                if ($compileCode !== 0) {
                    $pistonOut = runPistonAPI('java', $code, $input);
                    if ($pistonOut !== null) {
                        $output = $pistonOut;
                    } else {
                        $output = "Compilation Error:\n" . implode("\n", $compileOut);
                    }
                } else {
                    $runCmd = 'java -cp "' . $tmpDir . '" Main < "' . $inputFile . '" 2>&1';
                    exec($runCmd, $outArr);
                    $output = implode("\n", $outArr);
                }
                break;
            }
            case 'html':
            case 'html/css': {
                $output = "--- HTML / CSS Output Preview ---\n\n" . $code;
                break;
            }
            case 'sql': {
                // Safe SQL execution (SELECT only) using the portal's DB connection
                if (stripos(trim($code), 'SELECT') !== 0 && stripos(trim($code), 'SHOW') !== 0 && stripos(trim($code), 'DESCRIBE') !== 0) {
                    $output = "Error: Only SELECT, SHOW, or DESCRIBE statements are allowed in the practice lab for security reasons.";
                    break;
                }
                global $conn;
                if (!$conn) {
                    $output = "Error: Database connection failed.";
                    break;
                }
                
                // Allow multiple queries if separated by semicolon
                $queries = array_filter(array_map('trim', explode(';', $code)));
                $outputStr = "";
                
                foreach ($queries as $q) {
                    if (empty($q)) continue;
                    if (stripos($q, 'SELECT') !== 0 && stripos($q, 'SHOW') !== 0 && stripos($q, 'DESCRIBE') !== 0) {
                        $outputStr .= "Error: Query not allowed -> $q\n\n";
                        continue;
                    }
                    
                    $res = $conn->query($q);
                    if ($res === false) {
                        $outputStr .= "SQL Error: " . $conn->error . "\n\n";
                    } else if ($res === true) {
                        $outputStr .= "Query executed successfully.\n\n";
                    } else {
                        // Print result set as text table
                        $fields = $res->fetch_fields();
                        $headers = [];
                        foreach ($fields as $f) {
                            $headers[] = $f->name;
                        }
                        $outputStr .= implode(" | ", $headers) . "\n";
                        $outputStr .= str_repeat("-", count($headers) * 15) . "\n";
                        
                        while ($row = $res->fetch_assoc()) {
                            $outputStr .= implode(" | ", array_values($row)) . "\n";
                        }
                        $outputStr .= "\n(" . $res->num_rows . " rows returned)\n\n";
                    }
                }
                $output = trim($outputStr);
                break;
            }
            default:
                $output = "Error: Unsupported language '{$language}'.";
        }
    } finally {
        // Cleanup temp files
        if (is_dir($tmpDir)) {
            array_map('unlink', glob($tmpDir . '/*'));
            @rmdir($tmpDir);
        }
    }

    return $output ?: "(No output produced)";
}

$output = executeCode($language, $code, $input);

// ── Log into coding_history ────────────────────────────────────────────────
if ($student_id > 0) {
    $stmt = $conn->prepare(
        "INSERT INTO `coding_history` (`student_id`, `language`, `code`, `program_input`, `program_output`, `output`, `created_at`)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if ($stmt) {
        $stmt->bind_param("isssss", $student_id, $language, $code, $input, $output, $output);
        $stmt->execute();
        $stmt->close();
    }

    // ── Update draft ──────────────────────────────────────────────────────
    $draftStmt = $conn->prepare(
        "INSERT INTO `coding_drafts` (`student_id`, `language`, `code`, `updated_at`)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE `code` = VALUES(`code`), `updated_at` = NOW()"
    );
    if ($draftStmt) {
        $draftStmt->bind_param("iss", $student_id, $language, $code);
        $draftStmt->execute();
        $draftStmt->close();
    }
}

echo $output;
?>
