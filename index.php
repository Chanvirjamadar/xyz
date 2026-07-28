<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZEALHUB Coding Lab</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="header">
    <h2>ZEALHUB Coding Lab</h2>
    <a href="../student_dashboard.php" style="color:white;text-decoration:none;">⬅ Dashboard</a>
</div>

<div class="container">
    <div class="topbar">
        <select id="language">
            <option>C</option>
            <option selected>C++</option>
            <option>Java</option>
            <option>Python</option>
            <option>PHP</option>
            <option>SQL</option>
            <option>JavaScript</option>
        </select>

        <div class="buttons">
            <button id="runBtn" class="run">▶ Run</button>
            <button id="testBtn" class="test">✔ Test</button>
            <button id="saveBtn" class="save">💾 Save</button>
            <button id="historyBtn" class="history">📜 History</button>
        </div>
    </div>

    <div id="editor" class="editor"></div>

    <div class="console">
        <div class="box">
            <h3>Input</h3>
            <textarea id="input" placeholder="Enter input here..."></textarea>
        </div>

        <div class="box">
            <h3>Output</h3>
            <textarea id="output" readonly placeholder="Program output..."></textarea>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
<script src="script.js"></script>

</body>
</html>
