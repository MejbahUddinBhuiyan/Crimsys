/* /Crimsys/js/zone_analysis.js
   Front-end for Zone Analysis (read-only). */

const $ = sel => document.querySelector(sel);

const el = {
  from:      $('#fFrom'),
  to:        $('#fTo'),
  district:  $('#fDistrict'),
  thana:     $('#fThana'),
  apply:     $('#btnApply'),
  reset:     $('#btnReset'),
  tblTotals: $('#tblTotals'),
  cTotals:   $('#chartTotals'),
  cMonthly:  $('#chartMonthly'),
  cStatus:   $('#chartStatus'),
  cHigh:     $('#chartHigh'),
  cAge:      $('#chartAge'),
};

let charts = {};

function qs(params) {
  const u = new URLSearchParams();
  Object.entries(params).forEach(([k,v]) => { if (v !== undefined && v !== null && v !== '') u.set(k,v); });
  return u.toString();
}

async function api(path, params) {
  const url = `/Crimsys/api/${path}${params ? ('?' + qs(params)) : ''}`;
  const r = await fetch(url);
  const j = await r.json();
  if (!j.ok) throw new Error(j.error || 'API failed');
  return j;
}

// ---------- Filters / lists ----------
async function loadLists() {
  // districts
  const j = await api('zone_list.php');
  el.district.innerHTML = `<option value="">All</option>` + j.districts.map(d => `<option>${d}</option>`).join('');
  // thanas disabled until a district is chosen
  el.thana.innerHTML = `<option value="">All</option>`;
  el.thana.disabled = true;
}

async function onDistrictChange() {
  const d = el.district.value;
  if (!d) {
    el.thana.innerHTML = `<option value="">All</option>`;
    el.thana.disabled = true;
    return;
  }
  const j = await api('zone_list.php', { district: d });
  el.thana.innerHTML = `<option value="">All</option>` + j.thanas.map(t => `<option>${t}</option>`).join('');
  el.thana.disabled = false;
}

function getFilters() {
  return {
    from:     el.from.value,
    to:       el.to.value,
    district: el.district.value,
    thana:    el.thana.value,
  };
}

// ---------- Rendering ----------
function renderTableTotals(rows) {
  if (!rows.length) {
    el.tblTotals.innerHTML = `<thead><tr><th>Zone</th><th>Total</th></tr></thead><tbody><tr><td colspan="2" class="text-center text-white-50">No data</td></tr></tbody>`;
    return;
  }
  const body = rows.slice(0, 15).map(r => `
    <tr>
      <td>${r.District} • ${r.Thana}</td>
      <td class="text-end fw-bold">${r.TotalFIRs}</td>
    </tr>
  `).join('');
  el.tblTotals.innerHTML = `
    <thead><tr><th>Zone</th><th class="text-end">Total FIRs</th></tr></thead>
    <tbody>${body}</tbody>`;
}

function ensureChart(key, ctx, config) {
  if (charts[key]) { charts[key].destroy(); }
  charts[key] = new Chart(ctx, config);
}

// ---------- Load data + charts ----------
async function loadTotals(filters) {
  const j = await api('zone_stats.php', { metric:'totals', ...filters });
  renderTableTotals(j.rows);

  const labels = j.rows.slice(0, 12).map(r => `${r.District} • ${r.Thana}`);
  const data   = j.rows.slice(0, 12).map(r => +r.TotalFIRs);

  ensureChart('totals', el.cTotals, {
    type: 'bar',
    data: { labels, datasets: [{ label:'FIRs', data }] },
    options: { responsive:true, plugins:{ legend:{display:false} } }
  });
}

async function loadMonthly(filters) {
  const j = await api('zone_stats.php', { metric:'monthly', ...filters });
  // Build month => count
  const map = new Map();
  j.rows.forEach(r => {
    const k = r.Month;
    map.set(k, (map.get(k) ?? 0) + (+r.FIR_Count));
  });
  const labels = [...map.keys()].sort();
  const data   = labels.map(k => map.get(k));

  ensureChart('monthly', el.cMonthly, {
    type: 'line',
    data: { labels, datasets: [{ label:'FIRs / month', data, tension:.25, fill:false }] },
    options: { responsive:true, plugins:{ legend:{display:false} } }
  });
}

async function loadStatus(filters) {
  const j = await api('zone_stats.php', { metric:'status', ...filters });
  const rows = j.rows.slice(0, 10);

  const labels = rows.map(r => `${r.District} • ${r.Thana}`);
  const open   = rows.map(r => +r.OpenCases);
  const inv    = rows.map(r => +r.UnderInvestigation);
  const res    = rows.map(r => +r.ResolvedCases);

  ensureChart('status', el.cStatus, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label:'Open', data: open, stack:'s' },
        { label:'Under Investigation', data: inv, stack:'s' },
        { label:'Resolved', data: res, stack:'s' },
      ]
    },
    options: {
      responsive:true,
      scales: { x:{ stacked:true }, y:{ stacked:true } }
    }
  });
}

async function loadHigh(filters) {
  const j = await api('zone_stats.php', { metric:'high', ...filters });
  const rows = j.rows.slice(0, 10);
  ensureChart('high', el.cHigh, {
    type: 'bar',
    data: {
      labels: rows.map(r => `${r.District} • ${r.Thana}`),
      datasets: [{ label:'High priority', data: rows.map(r => +r.HighPriorityCases) }]
    },
    options: { responsive:true, plugins:{ legend:{display:false} } }
  });
}

async function loadAge(filters) {
  const j = await api('zone_stats.php', { metric:'age', ...filters });
  const rows = j.rows.slice(0, 10);
  ensureChart('age', el.cAge, {
    type: 'bar',
    data: {
      labels: rows.map(r => `${r.District} • ${r.Thana}`),
      datasets: [{ label:'Avg age (days)', data: rows.map(r => Math.round(+r.AvgAgeDays || 0)) }]
    },
    options: { responsive:true, plugins:{ legend:{display:false} } }
  });
}

async function refreshAll() {
  const filters = getFilters();
  await Promise.all([
    loadTotals(filters),
    loadMonthly(filters),
    loadStatus(filters),
    loadHigh(filters),
    loadAge(filters),
  ]);
}

// ---------- Events ----------
document.addEventListener('DOMContentLoaded', async () => {
  await loadLists();
  el.district.addEventListener('change', onDistrictChange);
  el.apply.addEventListener('click', refreshAll);
  el.reset.addEventListener('click', async () => {
    el.from.value = el.to.value = '';
    el.district.value = '';
    el.thana.innerHTML = `<option value="">All</option>`;
    el.thana.disabled = true;
    await refreshAll();
  });

  // Initial load
  await refreshAll();
});
