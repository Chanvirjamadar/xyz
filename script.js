// Monaco Editor Engine & Button Handlers for ZEALHUB Coding Lab

require.config({
    paths: {
        vs: "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs"
    }
});

let editor = null;

const defaultSnippets = {
    "C": `#include <stdio.h>\n\nint main() {\n    printf("Hello ZEALHUB C\\n");\n    return 0;\n}`,
    "C++": `#include <iostream>\nusing namespace std;\n\nint main() {\n    cout << "Hello ZEALHUB";\n    return 0;\n}`,
    "Java": `public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello ZEALHUB Java");\n    }\n}`,
    "Python": `def main():\n    print("Hello ZEALHUB Python")\n\nif __name__ == "__main__":\n    main()`,
    "PHP": `<?php\necho "Hello ZEALHUB PHP\\n";\n?>`,
    "JavaScript": `console.log("Hello ZEALHUB JavaScript");`,
    "SQL": `SELECT 'Hello ZEALHUB SQL' AS Output;`
};

const languageMap = {
    "C": "c",
    "C++": "cpp",
    "Java": "java",
    "Python": "python",
    "PHP": "php",
    "JavaScript": "javascript",
    "SQL": "sql"
};

document.addEventListener("DOMContentLoaded", () => {
    initMonaco();
    initButtons();
    startAutoSave();
});

function initMonaco() {
    require(["vs/editor/editor.main"], function () {
        const container = document.getElementById("editor");
        if (!container) return;

        editor = monaco.editor.create(container, {
            value: defaultSnippets["C++"],
            language: "cpp",
            theme: "vs-dark",
            automaticLayout: true,
            fontSize: 16,
            minimap: { enabled: true },
            lineNumbers: "on",
            autoClosingBrackets: "always",
            autoClosingQuotes: "always",
            contextmenu: false
        });

        disableEditorCopyPaste(editor, container);

        const langSelect = document.getElementById("language");
        if (langSelect) {
            langSelect.addEventListener("change", function () {
                const langVal = this.value;
                const monacoLang = languageMap[langVal] || "plaintext";
                monaco.editor.setModelLanguage(editor.getModel(), monacoLang);
                if (defaultSnippets[langVal]) {
                    editor.setValue(defaultSnippets[langVal]);
                }
            });
        }
    });
}

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

function disableEditorCopyPaste(editorInstance, containerElem) {
    if (!containerElem) return;

    containerElem.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showToast("Copy-Paste is disabled in this Coding Lab.");
        return false;
    }, true);

    containerElem.addEventListener('paste', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showToast("Copy-Paste is disabled in this Coding Lab.");
        return false;
    }, true);

    containerElem.addEventListener('dragover', (e) => {
        e.preventDefault();
    }, true);

    containerElem.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showToast("Copy-Paste is disabled in this Coding Lab.");
        return false;
    }, true);

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

function initButtons() {
    const runBtn = document.getElementById("runBtn");
    const testBtn = document.getElementById("testBtn");
    const saveBtn = document.getElementById("saveBtn");
    const historyBtn = document.getElementById("historyBtn");

    if (runBtn) {
        runBtn.onclick = function (e) {
            e.preventDefault();
            runCode();
        };
    }

    if (testBtn) {
        testBtn.onclick = function (e) {
            e.preventDefault();
            runCode(true);
        };
    }

    if (saveBtn) {
        saveBtn.onclick = function (e) {
            e.preventDefault();
            saveCode(false);
        };
    }

    if (historyBtn) {
        historyBtn.onclick = function (e) {
            e.preventDefault();
            openHistoryModal();
        };
    }
}

async function runCode(isTest = false) {
    if (!editor) return;

    const code = editor.getValue();
    const language = document.getElementById("language").value;
    const input = document.getElementById("input") ? document.getElementById("input").value : "";
    const outputElem = document.getElementById("output");

    if (outputElem) {
        outputElem.value = isTest ? "⚡ Running Test Suite..." : "⚡ Compiling & Executing code...";
    }

    try {
        const formData = new FormData();
        formData.append("code", code);
        formData.append("language", language);
        formData.append("input", input);

        const response = await fetch("run.php", {
            method: "POST",
            body: formData
        });

        const resultText = await response.text();
        if (outputElem) {
            outputElem.value = resultText;
        }
    } catch (err) {
        if (outputElem) {
            outputElem.value = "Error executing code: " + err.message;
        }
    }
}

async function saveCode(isSilent = true) {
    if (!editor) return;

    const code = editor.getValue();
    const language = document.getElementById("language").value;
    const outputElem = document.getElementById("output");

    try {
        const formData = new FormData();
        formData.append("code", code);
        formData.append("language", language);

        const response = await fetch("save.php", {
            method: "POST",
            body: formData
        });

        const data = await response.json();
        if (!isSilent && outputElem) {
            outputElem.value = `[${data.time || 'Saved'}] ${data.message || 'Code draft saved successfully.'}`;
        }
    } catch (err) {
        console.error("Save error:", err);
    }
}

function startAutoSave() {
    setInterval(() => {
        saveCode(true);
    }, 10000);
}

async function openHistoryModal() {
    let modalOverlay = document.getElementById("historyModalOverlay");
    if (!modalOverlay) {
        modalOverlay = document.createElement("div");
        modalOverlay.id = "historyModalOverlay";
        modalOverlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.75); display: flex; align-items: center; justify-content: center;
            z-index: 10000;
        `;
        document.body.appendChild(modalOverlay);
    }

    modalOverlay.innerHTML = `
        <div style="background: #1e293b; color: white; width: 90%; max-width: 600px; border-radius: 12px; padding: 24px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; border-bottom: 1px solid #334155; padding-bottom: 12px;">
                <h3 style="margin: 0; color: #38bdf8;">📜 Coding History</h3>
                <button id="closeHistoryBtn" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">✖</button>
            </div>
            <div id="historyItemsContent" style="flex: 1; overflow-y: auto; padding-right: 5px;">
                <p style="text-align: center; color: #94a3b8;">Loading history...</p>
            </div>
        </div>
    `;

    document.getElementById("closeHistoryBtn").onclick = () => {
        modalOverlay.remove();
    };

    try {
        const response = await fetch("history.php?action=list");
        const json = await response.json();

        const contentDiv = document.getElementById("historyItemsContent");
        if (json.status === "success" && json.data.length > 0) {
            let html = "";
            json.data.forEach(item => {
                html += `
                    <div style="background: #0f172a; padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; cursor: pointer; border: 1px solid #334155; transition: background 0.2s;"
                         onclick="loadHistoryEntry(${item.id})">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; color: #38bdf8; margin-bottom: 6px;">
                            <span>${item.language}</span>
                            <span style="color: #94a3b8; font-weight: normal;">${item.date}</span>
                        </div>
                        <code style="color: #e2e8f0; font-size: 13px;">${escapeHtml(item.snippet)}</code>
                    </div>
                `;
            });
            contentDiv.innerHTML = html;
        } else {
            contentDiv.innerHTML = `<p style="text-align: center; color: #94a3b8;">No coding history found.</p>`;
        }
    } catch (err) {
        document.getElementById("historyItemsContent").innerHTML = `<p style="color: #ef4444; text-align: center;">Failed to load history.</p>`;
    }
}

async function loadHistoryEntry(id) {
    try {
        const res = await fetch(`history.php?action=get&id=${id}`);
        const json = await res.json();
        if (json.status === "success" && json.data) {
            const item = json.data;
            const langSelect = document.getElementById("language");
            if (langSelect) {
                for (let i = 0; i < langSelect.options.length; i++) {
                    if (langSelect.options[i].value.toLowerCase() === item.language.toLowerCase() ||
                        langSelect.options[i].text.toLowerCase() === item.language.toLowerCase()) {
                        langSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            if (editor) {
                const monacoLang = languageMap[langSelect.value] || "plaintext";
                monaco.editor.setModelLanguage(editor.getModel(), monacoLang);
                editor.setValue(item.code);
            }
            if (document.getElementById("input") && item.program_input) {
                document.getElementById("input").value = item.program_input;
            }
            if (document.getElementById("output") && item.program_output) {
                document.getElementById("output").value = item.program_output;
            }
            const modalOverlay = document.getElementById("historyModalOverlay");
            if (modalOverlay) modalOverlay.remove();
        }
    } catch (err) {
        console.error("Error loading history item:", err);
    }
}

function escapeHtml(str) {
    if (!str) return "";
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
