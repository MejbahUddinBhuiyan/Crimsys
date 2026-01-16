// cop_watchlist.js

const API_BASE = "/Crimsys/api";

document.addEventListener("DOMContentLoaded", () => {
  const searchForm = document.getElementById("searchForm");
  const tableBody = document.querySelector("#watchlistTable tbody");
  const modal = document.getElementById("viewModal");
  const closeBtn = document.querySelector(".close-btn");

  // Load watchlist on page load (without auto-open modal)
  fetchWatchlist("");

  searchForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const formData = new FormData(searchForm);
    const params = new URLSearchParams();
    for (let [key, value] of formData.entries()) {
      if (value) params.append(key, value);
    }
    fetchWatchlist(params.toString());
  });

  function fetchWatchlist(query = "") {
    fetch(`${API_BASE}/watchlist_list.php?${query}`)
      .then((res) => res.json())
      .then((json) => {
        if (!json.success) {
          alert("Failed to load watchlist");
          return;
        }
        renderTable(json.data);
      })
      .catch((err) => console.error("Error fetching watchlist:", err));
  }

  function resolvePhotoPath(photo) {
    if (photo && photo !== "") {
      if (photo.startsWith("/img/")) {
        return "/Crimsys" + photo; // DB already stores path
      } else {
        return "/Crimsys/img/criminals/" + photo; // DB stores filename only
      }
    }
    return "/Crimsys/img/criminals/placeholder-avatar.png"; // fallback
  }

  function renderTable(data) {
    tableBody.innerHTML = "";
    if (!data || data.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="10">No records found</td></tr>`;
      return;
    }

    data.forEach((row) => {
      const tr = document.createElement("tr");

      const photoPath = resolvePhotoPath(row.Photo);

      tr.innerHTML = `
        <td><img src="${photoPath}" alt="Photo" class="watchlist-photo"></td>
        <td>${row.FullName}</td>
        <td>${row.NID}</td>
        <td>${row.CriminalID}</td>
        <td>${row.City} / ${row.Street}</td>
        <td>${row.Age}</td>
        <td><span class="status-badge ${row.Status === "Active" ? "status-active" : "status-removed"}">${row.Status}</span></td>
        <td>${row.ReviewDate}</td>
        <td>${row.Reason}</td>
        <td class="actions">
          <button class="btn-view" data-id="${row.CriminalID}">View</button>
          ${row.Status === "Active"
            ? `<button class="btn-renew" data-id="${row.CriminalID}">Renew</button>
               <button class="btn-remove" data-id="${row.CriminalID}">Remove</button>`
            : `<button class="btn-reactivate" data-id="${row.CriminalID}">Reactivate</button>`}
        </td>
      `;

      tableBody.appendChild(tr);
    });

    attachActionHandlers();
  }

  function attachActionHandlers() {
    document.querySelectorAll(".btn-view").forEach((btn) =>
      btn.addEventListener("click", () => {
        fetch(`${API_BASE}/watchlist_get.php?criminalId=${btn.dataset.id}`)
          .then((res) => res.json())
          .then((json) => {
            if (json.success && json.data) openModal(json.data);
          });
      })
    );

    document.querySelectorAll(".btn-renew").forEach((btn) =>
      btn.addEventListener("click", () => renewEntry(btn.dataset.id))
    );
    document.querySelectorAll(".btn-remove").forEach((btn) =>
      btn.addEventListener("click", () => removeEntry(btn.dataset.id))
    );
    document.querySelectorAll(".btn-reactivate").forEach((btn) =>
      btn.addEventListener("click", () => reactivateEntry(btn.dataset.id))
    );
  }

  function openModal(d) {
    const photoPath = resolvePhotoPath(d.Photo);

    document.getElementById("modalPhoto").src = photoPath;
    document.getElementById("modalName").innerText = d.FullName;
    document.getElementById("modalNid").innerText = d.NID;
    document.getElementById("modalCid").innerText = d.CriminalID;
    document.getElementById("modalAddress").innerText = `${d.City} / ${d.Street}`;
    document.getElementById("modalAge").innerText = d.Age;
    document.getElementById("modalStatus").innerText = d.Status;
    document.getElementById("modalReview").innerText = d.ReviewDate;
    document.getElementById("modalReason").innerText = d.Reason;

    modal.style.display = "block";
  }

  closeBtn.addEventListener("click", () => (modal.style.display = "none"));
  window.addEventListener("click", (e) => {
    if (e.target === modal) modal.style.display = "none";
  });

  function renewEntry(id) {
    fetch(`${API_BASE}/watchlist_renew.php`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ criminalId: id }),
    }).then(() => fetchWatchlist());
  }

  function removeEntry(id) {
    fetch(`${API_BASE}/watchlist_remove.php`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ criminalId: id }),
    }).then(() => fetchWatchlist());
  }

  function reactivateEntry(id) {
    fetch(`${API_BASE}/watchlist_reactivate.php`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ criminalId: id }),
    }).then(() => fetchWatchlist());
  }
});
