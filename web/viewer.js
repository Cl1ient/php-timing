"use strict";

// Viewer state.
const state = {
    data: null,        // parsed report payload
    sortKey: "totalTimeNs",
    sortDir: "desc",   // "asc" | "desc"
    collapsed: new Set(),
};

const el = {
    dropzone: document.getElementById("dropzone"),
    report: document.getElementById("report"),
    summary: document.getElementById("summary"),
    rows: document.getElementById("rows"),
    fileInput: document.getElementById("file-input"),
    pasteBtn: document.getElementById("paste-btn"),
    pasteDialog: document.getElementById("paste-dialog"),
    pasteArea: document.getElementById("paste-area"),
    pasteLoad: document.getElementById("paste-load"),
};

// --- Formatting helpers -----------------------------------------------------

function formatMs(ns) {
    return (ns / 1e6).toFixed(3) + " ms";
}

function formatPercent(value) {
    return value.toFixed(1) + "%";
}

function formatDate(unixSeconds) {
    return new Date(unixSeconds * 1000).toLocaleString();
}

// --- Loading ----------------------------------------------------------------

function loadFromText(text) {
    let payload;
    try {
        payload = JSON.parse(text);
    } catch (e) {
        alert("Invalid JSON: " + e.message);
        return;
    }
    if (!payload || !Array.isArray(payload.records)) {
        alert("This file does not look like a timings report (missing 'records').");
        return;
    }
    state.data = payload;
    state.collapsed.clear();
    render();
}

function loadFromFile(file) {
    const reader = new FileReader();
    reader.onload = () => loadFromText(String(reader.result));
    reader.readAsText(file);
}

// --- Tree building ----------------------------------------------------------

function buildTree(records) {
    const byId = new Map();
    for (const r of records) {
        byId.set(r.id, { record: r, children: [] });
    }
    const roots = [];
    for (const node of byId.values()) {
        const parentId = node.record.parentId;
        if (parentId != null && byId.has(parentId)) {
            byId.get(parentId).children.push(node);
        } else {
            roots.push(node);
        }
    }
    return roots;
}

function metric(record, key, sampleTimeNs) {
    switch (key) {
        case "name": return record.name.toLowerCase();
        case "avg": return record.count > 0 ? record.totalTimeNs / record.count : 0;
        case "percent": return sampleTimeNs > 0 ? record.totalTimeNs / sampleTimeNs : 0;
        default: return record[key];
    }
}

function sortNodes(nodes, sampleTimeNs) {
    const dir = state.sortDir === "asc" ? 1 : -1;
    nodes.sort((a, b) => {
        const va = metric(a.record, state.sortKey, sampleTimeNs);
        const vb = metric(b.record, state.sortKey, sampleTimeNs);
        if (va < vb) return -1 * dir;
        if (va > vb) return 1 * dir;
        return 0;
    });
    for (const node of nodes) {
        sortNodes(node.children, sampleTimeNs);
    }
}

// --- Rendering --------------------------------------------------------------

function render() {
    const data = state.data;
    if (!data) return;

    el.dropzone.hidden = true;
    el.report.hidden = false;

    renderSummary(data);
    renderSortIndicators();

    const sampleTimeNs = data.sampleTimeNs || 0;
    const roots = buildTree(data.records);
    sortNodes(roots, sampleTimeNs);

    el.rows.innerHTML = "";
    for (const node of roots) {
        renderNode(node, 0, sampleTimeNs);
    }
}

function renderSummary(data) {
    const items = [
        ["Created", data.createdAt ? formatDate(data.createdAt) : "—"],
        ["Sample time", formatMs(data.sampleTimeNs || 0)],
        ["Tick limit", formatMs(data.tickDurationLimitNs || 0)],
        ["Records", String(data.records.length)],
    ];
    el.summary.innerHTML = items
        .map(([label, value]) =>
            `<div class="item"><span class="label">${label}</span><span class="value">${value}</span></div>`)
        .join("");
}

function renderSortIndicators() {
    document.querySelectorAll("th[data-sort]").forEach((th) => {
        th.classList.remove("sorted-asc", "sorted-desc");
        if (th.dataset.sort === state.sortKey) {
            th.classList.add(state.sortDir === "asc" ? "sorted-asc" : "sorted-desc");
        }
    });
}

function renderNode(node, depth, sampleTimeNs) {
    const r = node.record;
    const hasChildren = node.children.length > 0;
    const isCollapsed = state.collapsed.has(r.id);

    const avg = r.count > 0 ? r.totalTimeNs / r.count : 0;
    const percent = sampleTimeNs > 0 ? (r.totalTimeNs / sampleTimeNs) * 100 : 0;

    const tr = document.createElement("tr");

    const toggleSymbol = hasChildren ? (isCollapsed ? "▶" : "▼") : "";
    const groupTag = depth === 0 ? `<span class="group-tag">${escapeHtml(r.group)}</span>` : "";

    tr.innerHTML = `
        <td class="name">
            <span class="name-inner" style="padding-left:${depth * 18}px">
                <span class="toggle${hasChildren ? "" : " leaf"}" data-id="${r.id}">${toggleSymbol}</span>
                <span>${escapeHtml(r.name)}</span>${groupTag}
            </span>
        </td>
        <td>${r.count}</td>
        <td>${formatMs(r.totalTimeNs)}</td>
        <td class="pct-cell">
            <span class="pct-bar" style="width:${Math.min(percent, 100)}%"></span>
            <span class="pct-value">${formatPercent(percent)}</span>
        </td>
        <td>${formatMs(avg)}</td>
        <td>${formatMs(r.peakTimeNs)}</td>
        <td class="${violationClass(r.violations)}">${r.violations}</td>
    `;
    el.rows.appendChild(tr);

    if (hasChildren && !isCollapsed) {
        for (const child of node.children) {
            renderNode(child, depth + 1, sampleTimeNs);
        }
    }
}

function violationClass(violations) {
    if (violations === 0) return "";
    return violations > 5 ? "viol-danger" : "viol-warn";
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

// --- Events -----------------------------------------------------------------

el.fileInput.addEventListener("change", () => {
    if (el.fileInput.files.length > 0) {
        loadFromFile(el.fileInput.files[0]);
    }
});

["dragenter", "dragover"].forEach((type) => {
    el.dropzone.addEventListener(type, (e) => {
        e.preventDefault();
        el.dropzone.classList.add("dragover");
    });
});

["dragleave", "drop"].forEach((type) => {
    el.dropzone.addEventListener(type, (e) => {
        e.preventDefault();
        el.dropzone.classList.remove("dragover");
    });
});

el.dropzone.addEventListener("drop", (e) => {
    const file = e.dataTransfer?.files?.[0];
    if (file) loadFromFile(file);
});

el.pasteBtn.addEventListener("click", () => {
    el.pasteArea.value = "";
    el.pasteDialog.showModal();
});

el.pasteLoad.addEventListener("click", (e) => {
    e.preventDefault();
    const text = el.pasteArea.value.trim();
    el.pasteDialog.close();
    if (text) loadFromText(text);
});

// Column sorting.
document.querySelectorAll("th[data-sort]").forEach((th) => {
    th.addEventListener("click", () => {
        const key = th.dataset.sort;
        if (state.sortKey === key) {
            state.sortDir = state.sortDir === "asc" ? "desc" : "asc";
        } else {
            state.sortKey = key;
            state.sortDir = key === "name" ? "asc" : "desc";
        }
        render();
    });
});

// Expand/collapse toggles (delegated).
el.rows.addEventListener("click", (e) => {
    const toggle = e.target.closest(".toggle");
    if (!toggle || toggle.classList.contains("leaf")) return;
    const id = Number(toggle.dataset.id);
    if (state.collapsed.has(id)) {
        state.collapsed.delete(id);
    } else {
        state.collapsed.add(id);
    }
    render();
});
