/* -------------------------------------------------------
   Criminal History – Frontend bridge to PHP API
   Safe to use with your current HTML (defensive selectors)
--------------------------------------------------------*/

const API = {
  list: '/Crimsys/api/history_list.php',
  get: '/Crimsys/api/history_get.php',
  fir: '/Crimsys/api/history_fir_links.php',
  update: '/Crimsys/api/history_update.php',
  crimetypes: '/Crimsys/api/history_crimetypes.php',
  cops: '/Crimsys/api/history_cops.php'
};

/* ---------- helpers ---------- */
const $ = (sel, root = document) => root.querySelector(sel);
const create = (tag, opts = {}) => Object.assign(document.createElement(tag), opts);
const fmt = (v) => (v === null || v === undefined || v === '' ? '—' : v);

/* Defensive: grab inputs by several possible IDs/placeholders */
const inpName =
  document.getElementById('qName') ||
  document.getElementById('nameInput') ||
  document.getElementById('searchName') ||
  document.querySelector('input[placeholder="Search by Name"]');

const inpNid =
  document.getElementById('qNid') ||
  document.getElementById('nidInput') ||
  document.getElementById('searchNid') ||
  document.querySelector('input[placeholder="Search by NID"]');

const inpCriminalId =
  document.getElementById('qCriminalId') ||
  document.getElementById('criminalIdInput') ||
  document.getElementById('searchCriminalId') ||
  document.querySelector('input[placeholder="Search by Criminal ID"]');

const selStatus =
  document.getElementById('qStatus') ||
  document.getElementById('statusFilter') ||
  document.querySelector('select');

const btnSearch =
  document.getElementById('btnSearch') ||
  document.getElementById('searchBtn') ||
  document.querySelector('.search-bar button, .top-search button');

const tbody =
  document.getElementById('historyBody') ||
  document.querySelector('#historyTable tbody') ||
  document.querySelector('table tbody');

/* ---------- modal factory (creates once) ---------- */
function ensureModals() {
  if (!document.getElementById('viewModal')) {
    const view = document.createElement('div');
    view.id = 'viewModal';
    view.style.cssText =
      'display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.6);';
    view.innerHTML = `
      <div style="background:#0e0e0e;border:1px solid #9c3a3f;color:#fff;
                  width:min(640px,90vw);margin:8vh auto;padding:18px;border-radius:12px;position:relative">
        <button id="closeModal"
                style="position:absolute;right:10px;top:10px;background:#222;border:1px solid #444;color:#fff;padding:6px 8px;border-radius:6px;cursor:pointer">✕</button>
        <div id="modalContent"></div>
      </div>`;
    document.body.appendChild(view);
  }
  if (!document.getElementById('editModal')) {
    const edit = document.createElement('div');
    edit.id = 'editModal';
    edit.style.cssText =
      'display:none;position:fixed;inset:0;z-index:1001;background:rgba(0,0,0,.6);';
    edit.innerHTML = `
      <div style="background:#0e0e0e;border:1px solid #9c3a3f;color:#fff;
                  width:min(680px,92vw);margin:6vh auto;padding:18px;border-radius:12px;position:relative">
        <button id="closeEdit"
                style="position:absolute;right:10px;top:10px;background:#222;border:1px solid #444;color:#fff;padding:6px 8px;border-radius:6px;cursor:pointer">✕</button>
        <h3 style="margin:0 0 12px 0;">Edit History</h3>
        <form id="editForm" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label style="display:flex;flex-direction:column;gap:6px;">
            <span>Status</span>
            <select name="CaseStatus" id="editStatus"
                    style="background:#111;color:#fff;border:1px solid #444;border-radius:8px;padding:8px">
              <option value="Open">Open</option>
              <option value="Under Trial">Under Trial</option>
              <option value="Solved">Solved</option>
            </select>
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;">
            <span>Crime Type</span>
            <select name="CrimeTypeID" id="editCrimeType"
                    style="background:#111;color:#fff;border:1px solid #444;border-radius:8px;padding:8px"></select>
          </label>
          <label style="grid-column:span 2;display:flex;flex-direction:column;gap:6px;">
            <span>Officer in Charge</span>
            <select name="OfficerInCharge" id="editOfficer"
                    style="background:#111;color:#fff;border:1px solid #444;border-radius:8px;padding:8px"></select>
          </label>
          <label style="grid-column:span 2;display:flex;flex-direction:column;gap:6px;">
            <span>Location</span>
            <input name="Location" id="editLocation" type="text"
                   style="background:#111;color:#fff;border:1px solid #444;border-radius:8px;padding:8px"
                   placeholder="Enter location">
          </label>
          <input type="hidden" name="HistoryID" id="editHistoryId">
        </form>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;">
          <button id="btnSaveEdit"
                  style="background:#16a34a;border:none;color:#fff;padding:8px 14px;border-radius:8px;cursor:pointer">
            Save
          </button>
        </div>
        <div id="editMsg" style="margin-top:8px;font-size:12px;opacity:.9;"></div>
      </div>`;
    document.body.appendChild(edit);
  }

  // attach listeners (guarded)
  const viewModal = document.getElementById('viewModal');
  const closeModalBtn = document.getElementById('closeModal');
  if (closeModalBtn && !closeModalBtn._bound) {
    closeModalBtn._bound = true;
    closeModalBtn.addEventListener('click', () => (viewModal.style.display = 'none'));
  }
  if (viewModal && !viewModal._bgBound) {
    viewModal._bgBound = true;
    viewModal.addEventListener('click', (e) => {
      if (e.target === viewModal) viewModal.style.display = 'none';
    });
  }

  const editModal = document.getElementById('editModal');
  const closeEdit = document.getElementById('closeEdit');
  if (closeEdit && !closeEdit._bound) {
    closeEdit._bound = true;
    closeEdit.addEventListener('click', () => (editModal.style.display = 'none'));
  }
  if (editModal && !editModal._bgBound) {
    editModal._bgBound = true;
    editModal.addEventListener('click', (e) => {
      if (e.target === editModal) editModal.style.display = 'none';
    });
  }
}

/* ---------- rendering ---------- */

function badge(status) {
  const colors = {
    Open: '#dc2626',
    'Under Trial': '#f59e0b',
    Solved: '#16a34a'
  };
  const bg = colors[status] || '#6b7280';
  return `<span style="display:inline-block;padding:2px 10px;border-radius:9999px;
          font-size:12px;background:${bg}22;border:1px solid ${bg};color:#fff">${status || '—'}</span>`;
}

/* ===== CHANGED ONLY THIS FUNCTION ===== */
function buildRow(item) {
  const tr = create('tr');
  tr.innerHTML = `
    <td>${fmt(item.CriminalID)}</td>
    <td>${fmt(item.Name || item.FullName)}</td>
    <td>${fmt(item.DateTime)}</td>
    <td>${badge(item.CaseStatus)}</td>
    <td>${fmt(item.Location)}</td>
    <td>${fmt(item.CrimeTypeName)}</td>
    <td>${fmt(item.OfficerName)}</td>
    <td>
      <button class="btn-view" data-id="${item.HistoryID}"
        style="background:#2563eb;border:none;color:#fff;padding:6px 10px;border-radius:8px;cursor:pointer">View</button>
      <button class="btn-edit" data-id="${item.HistoryID}"
        style="background:#d97706;border:none;color:#fff;padding:6px 10px;border-radius:8px;cursor:pointer;margin-left:6px">Edit</button>
    </td>
  `;
  return tr;
}
/* ===== END OF CHANGE ===== */

function renderEmpty() {
  const tr = create('tr');
  tr.innerHTML = `<td colspan="6">No records yet</td>`;
  return tr;
}

/* ---------- API calls ---------- */
async function fetchJSON(url, opts) {
  const res = await fetch(url, opts);
  return res.json();
}

async function loadList() {
  ensureModals();

  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="6">Loading…</td></tr>`;

  const params = new URLSearchParams();
  if (inpName && inpName.value.trim()) params.set('name', inpName.value.trim());
  if (inpNid && inpNid.value.trim()) params.set('nid', inpNid.value.trim());
  if (inpCriminalId && inpCriminalId.value.trim()) params.set('criminalId', inpCriminalId.value.trim());
  if (selStatus && selStatus.value && selStatus.value !== 'All') params.set('status', selStatus.value);

  try {
    const data = await fetchJSON(`${API.list}?${params.toString()}`);
    tbody.innerHTML = '';
    const list = Array.isArray(data?.data) ? data.data : [];
    if (!list.length) {
      tbody.appendChild(renderEmpty());
    } else {
      list.forEach((row) => tbody.appendChild(buildRow(row)));
    }
    bindRowActions();
  } catch (e) {
    console.error(e);
    tbody.innerHTML = `<tr><td colspan="6">Error loading data</td></tr>`;
  }
}

function bindRowActions() {
  tbody.querySelectorAll('.btn-view').forEach((btn) =>
    btn.addEventListener('click', () => openView(btn.getAttribute('data-id')))
  );
  tbody.querySelectorAll('.btn-edit').forEach((btn) =>
    btn.addEventListener('click', () => openEdit(btn.getAttribute('data-id')))
  );
}

/* ---------- View modal ---------- */
async function openView(historyId) {
  ensureModals();
  const viewModal = document.getElementById('viewModal');
  const content = document.getElementById('modalContent');
  content.innerHTML = 'Loading…';
  viewModal.style.display = 'block';

  try {
    const detail = await fetchJSON(`${API.get}?historyId=${encodeURIComponent(historyId)}`);
    const d = detail?.data || {};
    const fir = await fetchJSON(`${API.fir}?criminalId=${encodeURIComponent(d.CriminalID || '')}`);
    const firList = Array.isArray(fir?.data) ? fir.data : [];

    content.innerHTML = `
      <div style="display:grid;grid-template-columns:160px 1fr;gap:8px 14px;">
        <div style="opacity:.75;">Date/Time</div><div>${fmt(d.DateTime)}</div>
        <div style="opacity:.75;">Status</div><div>${badge(d.CaseStatus)}</div>
        <div style="opacity:.75;">Location</div><div>${fmt(d.Location)}</div>
        <div style="opacity:.75;">Crime Type</div><div>${fmt(d.CrimeTypeName)}</div>
        <div style="opacity:.75;">Officer in Charge</div><div>${fmt(d.OfficerName)}</div>
      </div>
      <div style="height:1px;background:#9c3a3f;margin:14px 0;opacity:.4;"></div>
      <div style="margin-bottom:6px;">Linked FIRs</div>
      ${
        firList.length
          ? `<div id="firsBox" style="display:flex;flex-direction:column;gap:6px;"></div>`
          : `<div style="opacity:.75;">No linked FIRs</div>`
      }
    `;

    if (firList.length) {
      const box = document.getElementById('firsBox');
      firList.forEach((f) => {
        const row = create('div', {
          style:
            'display:flex;justify-content:space-between;background:#141414;border:1px solid #333;padding:8px 10px;border-radius:8px'
        });
        row.innerHTML = `<div>FIR #${f.FirID}</div><div style="opacity:.8">${fmt(
          f.CreatedAt
        )}</div><div>${fmt(f.RoleInCase)}</div>`;
        box.appendChild(row);
      });
    }
  } catch (e) {
    console.error(e);
    content.innerHTML = 'Failed to load.';
  }
}

/* ---------- Edit modal ---------- */
async function openEdit(historyId) {
  ensureModals();
  const editModal = document.getElementById('editModal');
  const msg = document.getElementById('editMsg');
  const form = document.getElementById('editForm');
  const fStatus = document.getElementById('editStatus');
  const fCrime = document.getElementById('editCrimeType');
  const fOfficer = document.getElementById('editOfficer');
  const fLoc = document.getElementById('editLocation');
  const fId = document.getElementById('editHistoryId');

  msg.textContent = 'Loading…';
  msg.style.color = '#aaa';
  editModal.style.display = 'block';

  try {
    const [detail, types, cops] = await Promise.all([
      fetchJSON(`${API.get}?historyId=${encodeURIComponent(historyId)}`),
      fetchJSON(API.crimetypes),
      fetchJSON(API.cops)
    ]);

    const d = detail?.data || {};
    fId.value = historyId;

    // fill selects
    fCrime.innerHTML = '';
    (types?.data || []).forEach((ct) => {
      const opt = create('option', { value: ct.CrimeTypeID, textContent: `${ct.Name}` });
      fCrime.appendChild(opt);
    });

    fOfficer.innerHTML = '';
    (cops?.data || []).forEach((c) => {
      const opt = create('option', { value: c.CopID, textContent: `${c.Name}` });
      fOfficer.appendChild(opt);
    });

    // set values
    fStatus.value = d.CaseStatus || 'Open';
    if (d.CrimeTypeID) fCrime.value = String(d.CrimeTypeID);
    if (d.OfficerInCharge) fOfficer.value = String(d.OfficerInCharge);
    fLoc.value = d.Location || '';

    msg.textContent = '';
  } catch (e) {
    console.error(e);
    msg.textContent = 'Failed to load form data.';
    msg.style.color = '#dc2626';
  }

  // Save
  const btnSave = document.getElementById('btnSaveEdit');
  if (!btnSave._bound) {
    btnSave._bound = true;
    btnSave.addEventListener('click', async () => {
      msg.textContent = 'Saving…';
      msg.style.color = '#aaa';

      // Build payload explicitly and send BOTH naming styles
      const idVal = (document.getElementById('editHistoryId').value || '').trim();
      const caseStatusVal = (document.getElementById('editStatus').value || '').trim();
      const crimeTypeVal = (document.getElementById('editCrimeType').value || '').trim();
      const officerVal = (document.getElementById('editOfficer').value || '').trim();
      const locationVal = (document.getElementById('editLocation').value || '').trim();

      const payload = new FormData();
      // history id
      payload.set('HistoryID', idVal);
      payload.set('historyId', idVal);

      // status
      payload.set('CaseStatus', caseStatusVal);
      payload.set('caseStatus', caseStatusVal);

      // crime type
      payload.set('CrimeTypeID', crimeTypeVal);
      payload.set('crimeTypeId', crimeTypeVal);

      // officer
      payload.set('OfficerInCharge', officerVal);
      payload.set('officerId', officerVal);

      // location
      payload.set('Location', locationVal);
      payload.set('location', locationVal);

      try {
        const res = await fetch(API.update, { method: 'POST', body: payload });
        const json = await res.json();
        if (json?.success) {
          msg.textContent = 'Saved.';
          msg.style.color = '#16a34a';
          await loadList();
          setTimeout(() => (document.getElementById('editModal').style.display = 'none'), 500);
        } else {
          msg.textContent = json?.error || 'Save failed.';
          msg.style.color = '#dc2626';
        }
      } catch (e) {
        console.error(e);
        msg.textContent = 'Save failed.';
        msg.style.color = '#dc2626';
      }
    });
  }
}

/* ---------- wire up ---------- */
if (btnSearch) btnSearch.addEventListener('click', loadList);
[inpName, inpNid, inpCriminalId, selStatus].forEach((el) => {
  if (el) el.addEventListener('keydown', (e) => e.key === 'Enter' && loadList());
});

/* initial load */
document.addEventListener('DOMContentLoaded', () => {
  ensureModals();
  loadList();
});
