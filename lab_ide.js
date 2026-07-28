/**
 * Online Coding Platform - Monaco Editor Controller & IDE Engine
 */

let editor = null;
let currentLanguage = 'python';
let autoSaveTimer = null;

// Default Starter Code Snippets per Language
const defaultSnippets = {
    c: `#include <stdio.h>\n\nint main() {\n    // Welcome to ZealHub Online C Compiler\n    printf("Hello World! Welcome to Study Portal Lab Practice.\\n");\n    return 0;\n}`,
    cpp: `#include <iostream>\nusing namespace std;\n\nint main() {\n    // Welcome to ZealHub Online C++ Compiler\n    cout << "Hello World! Welcome to Study Portal Lab Practice." << endl;\n    return 0;\n}`,
    java: `public class Main {\n    public static void main(String[] args) {\n        // Welcome to ZealHub Online Java Compiler\n        System.out.println("Hello World! Welcome to Study Portal Lab Practice.");\n    }\n}`,
    python: `# Welcome to ZealHub Online Python Compiler\ndef main():\n    print("Hello World! Welcome to Study Portal Lab Practice.")\n\nif __name__ == "__main__":\n    main()`,
    php: `<?php
// Welcome to ZealHub Online PHP Executor
echo "Hello World! Welcome to Study Portal Lab Practice.\n";
?>`,
    javascript: `// Welcome to ZealHub Online JavaScript Engine
function greeting() {
    console.log("Hello World! Welcome to Study Portal Lab Practice.");
}

greeting();`,
    sql: `-- Welcome to ZealHub SQL Practice Mode
-- Example query execution:
SELECT 'Hello World! Welcome to Study Portal SQL Lab' AS Message;`
};

// Monaco Language mapping
const monacoLangMap = {
    'c': 'c',
    'cpp': 'cpp',
    'java': 'java',
    'python': 'python',
    'php': 'php',
    'javascript': 'javascript',
    'sql': 'sql'
};

document.addEventListener('DOMContentLoaded', () => {
    initMonacoEditor();
    initEventListeners();
    startAutoSaveInterval();
});

/**
 * Initialize Monaco Editor with VS Code dark theme and specs
 */
function initMonacoEditor() {
    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' } });

    require(['vs/editor/editor.main'], function () {
        const container = document.getElementById('monacoEditorContainer');
        if (!container) return;

        editor = monaco.editor.create(container, {
            value: defaultSnippets['python'],
            language: 'python',
            theme: 'vs-dark',
            fontSize: 16,
            fontFamily: "'Fira Code', 'Consolas', 'Courier New', monospace",
            automaticLayout: true,
            minimap: { enabled: true },
            lineNumbers: 'on',
            autoClosingBrackets: 'always',
            autoClosingQuotes: 'always',
            formatOnType: true,
            formatOnPaste: true,
            cursorBlinking: 'smooth',
            smoothScrolling: true,
            tabSize: 4,
            contextmenu: false // Disable Monaco context menu
        });

        // Disable Copy-Paste, Drag-and-Drop & Shortcuts ONLY on Code Editor
        disableEditorCopyPaste(editor, container);

        // Trigger draft check if initial code exists
        checkAndLoadDraft('python');
    });
}

/**
 * Toast Notification for Disabled Paste
 */
function showToast(message) {
    let toast = document.getElementById("ideToastNotice");
    if (!toast) {
        toast = document.createElement("div");
        toast.id = "ideToastNotice";
        toast.style.cssText = `
            position: fixed; bottom: 25px; right: 25px; background: #ef4444; color: #ffffff;
            padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); z-index: 100000; transition: opacity 0.3s ease;
            pointer-events: none;
        `;
        document.body.appendChild(toast);
    }
    toast.innerText = message;
    toast.style.opacity = "1";
    toast.style.display = "block";
    if (toast.timeoutId) clearTimeout(toast.timeoutId);
    toast.timeoutId = setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => { toast.style.display = "none"; }, 300);
    }, 2200);
}

/**
 * Disable Copy-Paste, Drag-and-Drop & Shortcuts ONLY on Code Editor
 */
function disableEditorCopyPaste(editorInstance, containerElem) {
    if (!containerElem) return;

    // 1. Right-Click Context Menu
    containerElem.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showToast("Copy-Paste is disabled in this Coding Lab.");
        return false;
    }, true);

    // 2. Paste Event
    containerElem.addEventListener('paste', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showToast("Copy-Paste is disabled in this Coding Lab.");
        return false;
    }, true);

    // 3. Drag and Drop
    containerElem.addEventListener('dragover', (e) => {
        e.preventDefault();
    }, true);

    containerElem.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showToast("Copy-Paste is disabled in this Coding Lab.");
        return false;
    }, true);

    // 4. Keyboard Shortcuts: Ctrl+V, Cmd+V, Shift+Insert
    containerElem.addEventListener('keydown', (e) => {
        const isCtrlV = (e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V' || e.keyCode === 86);
        const isShiftInsert = e.shiftKey && (e.key === 'Insert' || e.keyCode === 45);

        if (isCtrlV || isShiftInsert) {
            e.preventDefault();
            e.stopPropagation();
            showToast("Copy-Paste is disabled in this Coding Lab.");
            return false;
        }
    }, true);
}

/**
 * Register UI Event Listeners
 */
function initEventListeners() {
    const langSelect = document.getElementById('languageSelect');
    if (langSelect) {
        langSelect.addEventListener('change', (e) => {
            const newLang = e.target.value;
            switchLanguage(newLang);
        });
    }

    const runBtn = document.getElementById('runCodeBtn');
    if (runBtn) {
        runBtn.addEventListener('click', executeCode);
    }

    const saveBtn = document.getElementById('saveCodeBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', triggerManualSave);
    }

    const historyBtn = document.getElementById('historyModalBtn');
    if (historyBtn) {
        historyBtn.addEventListener('click', openCodingHistoryModal);
    }

    const clearConsoleBtn = document.getElementById('clearConsoleBtn');
    if (clearConsoleBtn) {
        clearConsoleBtn.addEventListener('click', () => {
            document.getElementById('consoleOutput').innerText = 'Output console cleared.';
        });
    }
}

function switchLanguage(lang) {
    currentLanguage = lang;
    if (editor) {
        const targetMonacoLang = monacoLangMap[lang] || 'plaintext';
        monaco.editor.setModelLanguage(editor.getModel(), targetMonacoLang);
        checkAndLoadDraft(lang);
    }
}

function checkAndLoadDraft(lang) {
    if (editor && defaultSnippets[lang]) {
        editor.setValue(defaultSnippets[lang]);
    }
}

function startAutoSaveInterval() {
    if (autoSaveTimer) clearInterval(autoSaveTimer);
    autoSaveTimer = setInterval(() => {
        saveCurrentDraft(true);
    }, 10000);
}

function saveCurrentDraft(isSilent = false) {
    if (!editor) return;

    const code = editor.getValue();
    const saveBadge = document.getElementById('saveStatusBadge');

    if (!isSilent && saveBadge) {
        saveBadge.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-primary"></i> Saving...`;
    }

    fetch('save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            language: currentLanguage,
            code: code
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (saveBadge) {
                    saveBadge.innerHTML = `<span class="pulse-dot"></span> Saved (${data.saved_at})`;
                }
                const lastSavedElem = document.getElementById('lastSavedTime');
                if (lastSavedElem) {
                    lastSavedElem.innerText = data.saved_at;
                }
            } else {
                if (!isSilent && saveBadge) {
                    saveBadge.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-danger"></i> Save Failed`;
                }
            }
        })
        .catch(err => {
            console.error("Auto-save error:", err);
            if (!isSilent && saveBadge) {
                saveBadge.innerHTML = `<i class="fa-solid fa-wifi text-danger"></i> Network Error`;
            }
        });
}

function triggerManualSave() {
    saveCurrentDraft(false);
}

function executeCode() {
    if (!editor) return;

    const code = editor.getValue();
    const stdin = document.getElementById('programInput') ? document.getElementById('programInput').value : '';
    const runBtn = document.getElementById('runCodeBtn');
    const outputConsole = document.getElementById('consoleOutput');

    if (runBtn) {
        runBtn.disabled = true;
        runBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Running...`;
    }

    outputConsole.innerText = "⚡ Compiling & Running code...";

    fetch('run.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            language: currentLanguage,
            code: code,
            input: stdin,
            provider: 'local'
        })
    })
        .then(res => res.text())
        .then(data => {
            if (runBtn) {
                runBtn.disabled = false;
                runBtn.innerHTML = `<i class="fa-solid fa-play"></i> Run Code`;
            }
            outputConsole.innerText = data;
            saveCurrentDraft(true);
        })
        .catch(err => {
            if (runBtn) {
                runBtn.disabled = false;
                runBtn.innerHTML = `<i class="fa-solid fa-play"></i> Run Code`;
            }
            outputConsole.innerText = `Network Error: Unable to execute program. Details: ${err.message}`;
        });
}

function openCodingHistoryModal() {
    const modalListContainer = document.getElementById('historyListContainer');
    if (!modalListContainer) return;

    modalListContainer.innerHTML = `<div class="text-center p-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Loading history...</p></div>`;

    const historyModalElem = document.getElementById('codingHistoryModal');
    let bsModal = bootstrap.Modal.getInstance(historyModalElem);
    if (!bsModal) {
        bsModal = new bootstrap.Modal(historyModalElem);
    }
    bsModal.show();

    fetch('history.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
                let html = '';
                data.data.forEach(item => {
                    html += `
                <div class="history-item-card" onclick="loadHistoryItem(${item.id})">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge bg-primary">${item.language}</span>
                        <small class="text-muted"><i class="fa-regular fa-clock"></i> ${item.date}</small>
                    </div>
                    <code style="color: #38bdf8; font-size: 12.5px;">${escapeHtml(item.snippet)}</code>
                </div>`;
                });
                modalListContainer.innerHTML = html;
            } else {
                modalListContainer.innerHTML = `<div class="text-center p-4 text-muted"><i class="fa-solid fa-box-open fa-2x mb-2"></i><p>No previous coding history found.</p></div>`;
            }
        })
        .catch(err => {
            modalListContainer.innerHTML = `<div class="text-center p-4 text-danger"><p>Failed to load history.</p></div>`;
        });
}

function loadHistoryItem(id) {
    fetch(`history.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const item = data.data;
                const langSelect = document.getElementById('languageSelect');
                if (langSelect) {
                    langSelect.value = item.language.toLowerCase();
                }
                switchLanguage(item.language.toLowerCase());

                if (editor) {
                    editor.setValue(item.code);
                }

                if (document.getElementById('programInput') && item.program_input) {
                    document.getElementById('programInput').value = item.program_input;
                }

                if (document.getElementById('consoleOutput') && item.program_output) {
                    document.getElementById('consoleOutput').innerText = item.program_output;
                }

                const historyModalElem = document.getElementById('codingHistoryModal');
                const bsModal = bootstrap.Modal.getInstance(historyModalElem);
                if (bsModal) bsModal.hide();
            }
        });
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
