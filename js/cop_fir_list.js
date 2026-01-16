(() => {
  // ------- helpers / refs -------
  const $ = (s) => document.querySelector(s);

  const refs = {
    q:   $('#q')   || $('#search') || $('#txtQ'),
    st:  $('#status') || $('#st')  || $('#selStatus'),
    d1:  $('#from')   || $('#d1')  || $('#dateFrom'),
    d2:  $('#to')     || $('#d2')  || $('#dateTo'),
    cop: $('#cop')    || $('#assignedCopId') || $('#txtCop'),

    apply: $('#apply') || $('#btnApply') || $('[data-role="apply"]'),
    reset: $('#reset') || $('#btnReset') || $('[data-role="reset"]'),

    tbody: $('#rows') || document.querySelector('table tbody')
  };

  const statusChip = (s) => {
    if (s === 'Resolved') return `<span class="chip chip-done">Resolved</span>`;
    if (s === 'Under Investigation') return `<span class="chip chip-prog">Under Investigation</span>`;
    return `<span class="chip chip-open">${s || 'Open'}</span>`;
  };

  const rowHTML = (it) => {
    const created   = it.CreatedAt || it.IncidentDate || '—';
    const status    = it.Status || 'Open';
    const crime     = it.Crime ?? it.CrimeType ?? it.CrimeTypeID ?? '—';
    const location  = it.Location || '—';
    const copId     = it.AssignedCopID ?? it.AssignedCop ?? it.CopID;
    const copLabel  = copId ? `Cop #${copId}` : '—';

    return `
      <tr>
        <td>${it.FirID}</td>
        <td>${created}</td>
        <td>${statusChip(status)}</td>
        <td>${crime}</td>
        <td>${location}</td>
        <td>${copLabel}</td>
        <td style="text-align:right">
          <a class="btn-ghost" href="/Crimsys/html/cop_fir_view.html?firId=${it.FirID}">Open</a>
        </td>
      </tr>`;
  };

  function readFilters() {
    const statusVal = (refs.st?.value ?? '').trim();
    return {
      q: (refs.q?.value ?? '').trim(),
      status: statusVal === '' || statusVal === 'all' ? '' : statusVal,
      from: (refs.d1?.value ?? '').trim(),
      to:   (refs.d2?.value ?? '').trim(),
      assignedCopId: (refs.cop?.value ?? '').trim()
    };
  }

  function showEmpty(msg) {
    if (!refs.tbody) return;
    refs.tbody.innerHTML = `<tr id="emptyRow"><td colspan="7" class="text-muted text-center py-3">${msg}</td></tr>`;
  }

  let loading = false;

  async function loadList() {
    if (!refs.tbody || loading) return;
    loading = true;
    showEmpty('Loading...');

    const f = readFilters();
    // build a GET query so it works with simple PHP handlers
    const qs = new URLSearchParams();
    if (f.q) qs.set('q', f.q);
    if (f.status) qs.set('status', f.status);
    if (f.from) qs.set('from', f.from);
    if (f.to) qs.set('to', f.to);
    if (f.assignedCopId) qs.set('assignedCopId', f.assignedCopId);

    try {
      const res = await fetch(`/Crimsys/api/cop/fir_list.php?${qs.toString()}`, { method: 'GET' });
      if (!res.ok) { showEmpty('Could not load list.'); return; }

      const data = await res.json();
      if (!data || !data.ok) { showEmpty((data && data.error) || 'No data.'); return; }

      const rows = Array.isArray(data.items) ? data.items : [];
      if (rows.length === 0) { showEmpty('No records found.'); return; }

      refs.tbody.innerHTML = rows.map(rowHTML).join('');
    } catch (e) {
      console.error(e);
      showEmpty('Could not load list.');
    } finally {
      loading = false;
    }
  }

  // buttons
  if (refs.apply) {
    refs.apply.type = 'button';
    refs.apply.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); loadList(); });
  }
  if (refs.reset) {
    refs.reset.type = 'button';
    refs.reset.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      if (refs.q) refs.q.value = '';
      if (refs.st) refs.st.value = '';
      if (refs.d1) refs.d1.value = '';
      if (refs.d2) refs.d2.value = '';
      if (refs.cop) refs.cop.value = '';
      loadList();
    });
  }

  // initial
  document.addEventListener('DOMContentLoaded', loadList, { once: true });
})();
