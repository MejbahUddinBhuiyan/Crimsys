// /Crimsys/js/cop_search_criminal.js
(function () {
  "use strict";

  const $ = (sel) => document.querySelector(sel);
  const el = (tag, cls) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    return n;
  };
  const nf = (v) => (v == null ? "" : String(v));

  function normalizePhotoPath(p) {
    if (!p) return "/Crimsys/img/placeholder-avatar.png";
    let s = p.replaceAll("\\", "/");
    if (s.startsWith("/img/")) s = "/Crimsys" + s;
    if (!s.startsWith("/Crimsys/")) s = "/Crimsys/" + s.replace(/^\/+/, "");
    return s;
  }

  function resolveUI() {
    return {
      q: $("#q"),
      role: $("#role"),
      limit: $("#limit"),
      btn: $("#btnSearch"),
      summary: $("#resultSummary"),
      list: $("#results"),
      prev: $("#prevPage"),
      next: $("#nextPage"),
    };
  }

  function renderEmpty(ui, msg) {
    if (ui.list) {
      ui.list.innerHTML = `<div class="empty-state">${msg || "No records found."}</div>`;
    }
    if (ui.summary) ui.summary.textContent = "";
  }

  function renderItems(ui, items) {
    if (!ui.list) return;
    ui.list.innerHTML = "";
    if (ui.summary) ui.summary.textContent = `${items.length} record(s) found.`;

    for (const it of items) {
      const card = el("div", "result-card p-3 mb-2");
      const row = el("div", "d-flex align-items-center gap-3");

      const avatar = el("img", "avatar");
      avatar.src = normalizePhotoPath(it.photo);
      avatar.alt = "photo";
      avatar.onerror = () => (avatar.src = "/Crimsys/img/placeholder-avatar.png");

      const mid = el("div", "flex-grow-1");

      const name = el("div", "fw-semibold text-white");
      name.textContent = nf(it.fullName);

      // ⬇️ Only change: make metadata line white (was "small text-muted")
      const meta = el("div", "small text-white");
      meta.textContent = `NID: ${nf(it.nid)} • ${nf(it.street)}, ${nf(it.city)} ${nf(it.zip)} • ${it.firCount || 0} FIRs`;

      mid.appendChild(name);
      mid.appendChild(meta);

      const openBtn = el("a", "btn btn-outline-accent btn-sm");
      openBtn.textContent = "Open";
      openBtn.href = `/Crimsys/html/cop_criminal_profile.html?id=${encodeURIComponent(it.criminalId)}`;

      row.appendChild(avatar);
      row.appendChild(mid);
      row.appendChild(openBtn);
      card.appendChild(row);
      ui.list.appendChild(card);
    }
  }

  async function search() {
    const ui = resolveUI();
    const q = (ui.q?.value || "").trim();
    const role = ui.role?.value || "";
    const limit = ui.limit?.value || "20";

    if (!q) {
      renderEmpty(ui, "Start by typing a NID or name, then click Search.");
      return;
    }

    const params = new URLSearchParams({ q, limit });
    if (role) params.set("role", role);

    renderEmpty(ui, "Searching…");

    try {
      const resp = await fetch(`/Crimsys/api/cop/search_criminal.php?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const data = await resp.json();

      if (!data || data.ok !== true) {
        renderEmpty(ui, data?.error || "Could not load results.");
        return;
      }

      const items = Array.isArray(data.items) ? data.items : [];
      if (items.length === 0) {
        renderEmpty(ui, "No records found.");
        return;
      }
      renderItems(ui, items);
    } catch (err) {
      console.error(err);
      renderEmpty(ui, "Search failed. Please try again.");
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const ui = resolveUI();
    ui.q?.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        search();
      }
    });
    ui.btn?.addEventListener("click", (e) => {
      e.preventDefault();
      search();
    });
  });
})();
