// /Crimsys/js/cop_fir_new.js
(function () {
  const $ = (id) => document.getElementById(id);

  const msg = $('msg');
  function showMsg(text, ok = false) {
    if (!msg) return;
    msg.textContent = text;
    msg.className = 'alert-banner ' + (ok ? 'alert-success-banner' : 'alert-danger-banner');
    msg.classList.remove('d-none');
    setTimeout(() => msg.classList.add('d-none'), 5000);
  }
  function hideMsg() { if (msg) { msg.classList.add('d-none'); msg.textContent=''; } }

  // Convert "YYYY-MM-DDTHH:MM" -> "YYYY-MM-DD HH:MM:SS"
  function toMysqlDatetime(dtLocal) {
    if (!dtLocal) return null;
    const parts = dtLocal.trim().split('T');
    if (parts.length !== 2) return null;
    const date = parts[0];
    let time = parts[1];
    if (time.length === 5) time += ':00';
    return `${date} ${time}`;
  }

  const steps = [
    $('step-incident'), $('step-vw'), $('step-sus'),
    $('step-evi'), $('step-assign'), $('step-review')
  ];
  const chips = [$('c1'),$('c2'),$('c3'),$('c4'),$('c5'),$('c6')];
  let stepIndex = 0;

  const btnNext = $('btnNext');
  const btnBack = $('btnBack');
  const btnSubmit = $('btnSubmit');
  const btnSaveDraft = $('btnSaveDraft');

  const incidentDate = $('incidentDate');
  const crimeTypeSel = $('crimeType');
  const locationInp = $('location');
  const desc = $('description');
  const suspectedInfo = $('suspectedInfo');

  const vwList = $('vwList'), vwRole = $('vwRole'), vwName = $('vwName'), vwContact = $('vwContact'), btnAddVW = $('btnAddVW');
  const susList = $('susList'), susRole = $('susRole'), susName = $('susName'), susContact = $('susContact'), btnAddSus = $('btnAddSuspect');
  const assignedDisplay = $('assignedCopDisplay');
  const statusSel = $('status');

  const revIncidentBody = $('revIncidentBody');
  const revPersonsBody = $('revPersonsBody');
  const revSuspectsBody = $('revSuspectsBody');
  const revAssignBody = $('revAssignBody');

  const overlay = $('successOverlay');
  const overlayFirId = $('successFirId');

  const persons = [];   // Victim/Witness
  const suspects = [];  // Suspect/Accused

  // Load active crime types
  async function loadCrimeTypes() {
    try {
      const r = await fetch('/Crimsys/api/cop/crime_type_list.php?active=1', { credentials: 'include' });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error || 'Failed to load crime types');
      (d.items || []).forEach(it => {
        const opt = document.createElement('option');
        opt.value = it.CrimeTypeID;
        opt.textContent = it.Name;
        crimeTypeSel.appendChild(opt);
      });
    } catch (e) { showMsg(e.message || 'Could not load crime types'); }
  }
  loadCrimeTypes();

  function row(label, name, contact, onRemove) {
    const wrap = document.createElement('div');
    wrap.className = 'd-flex align-items-center justify-content-between py-2 border-bottom border-secondary-subtle';
    wrap.innerHTML = `<div><span class="mini-badge me-2">${label}</span><span class="text-white">${name || '—'}</span> <span class="text-dim ms-2">${contact || ''}</span></div>`;
    const right = document.createElement('div');
    const btn = document.createElement('button');
    btn.className = 'btn btn-outline-accent btn-sm';
    btn.textContent = 'Remove';
    btn.addEventListener('click', onRemove);
    right.appendChild(btn);
    wrap.appendChild(right);
    return wrap;
  }
  function renderVW(){ vwList.innerHTML=''; persons.forEach((p,i)=>vwList.appendChild(row(p.role,p.name,p.contact,()=>{persons.splice(i,1);renderVW();}))); }
  function renderSus(){ susList.innerHTML=''; suspects.forEach((s,i)=>susList.appendChild(row(s.role,s.name,s.contact,()=>{suspects.splice(i,1);renderSus();}))); }

  $('btnAddVW')?.addEventListener('click', () => {
    const name = (vwName.value || '').trim();
    if (!name) { showMsg('Enter a name for victim/witness.'); return; }
    persons.push({ role: (vwRole.value || 'Victim'), name, contact: (vwContact.value||'').trim() });
    vwName.value=''; vwContact.value=''; renderVW();
  });
  $('btnAddSuspect')?.addEventListener('click', () => {
    suspects.push({ role: (susRole.value||'Suspect'), name: (susName.value||'Unknown').trim()||'Unknown', contact: (susContact.value||'').trim() });
    susName.value=''; susContact.value=''; renderSus();
  });

  function showStep(i){
    steps.forEach((s,idx)=>s.classList.toggle('active', idx===i));
    chips.forEach((c,idx)=>c.classList.toggle('step-active', idx===i));
    stepIndex = i;
    btnSubmit.classList.toggle('d-none', i !== steps.length-1);
    btnNext.classList.toggle('d-none', i === steps.length-1);
  }
  showStep(0);

  function validateIncident(){
    if (!incidentDate.value || !locationInp.value.trim() || !desc.value.trim()){
      showMsg('Please fill in incident date, location, and short description.');
      return false;
    }
    hideMsg(); return true;
  }

  function buildReview(){
    const dt = incidentDate.value || '—';
    const ctype = crimeTypeSel.options[crimeTypeSel.selectedIndex]?.textContent || '—';
    revIncidentBody.innerHTML = `
      <div><span class="mini-badge">Date</span> <span class="ms-2 text-white">${dt}</span></div>
      <div><span class="mini-badge">Location</span> <span class="ms-2 text-white">${locationInp.value}</span></div>
      <div><span class="mini-badge">Crime Type</span> <span class="ms-2 text-white">${ctype}</span></div>
      <div><span class="mini-badge">Summary</span> <span class="ms-2 text-white">${desc.value}</span></div>
    `;
    revPersonsBody.innerHTML = persons.length
      ? persons.map(p=>`<div><span class="mini-badge">${p.role}</span> <span class="ms-2 text-white">${p.name}</span> <span class="text-dim ms-2">${p.contact||''}</span></div>`).join('')
      : '<div class="text-dim">No victims/witnesses recorded.</div>';
    revSuspectsBody.innerHTML = suspects.length
      ? suspects.map(s=>`<div><span class="mini-badge">${s.role}</span> <span class="ms-2 text-white">${s.name}</span> <span class="text-dim ms-2">${s.contact||''}</span></div>`).join('')
      : '<div class="text-dim">No suspects/accused recorded.</div>';
    const ass = (assignedDisplay.value || '').trim() || 'Me';
    revAssignBody.innerHTML = `<div><span class="mini-badge">Assigned</span> <span class="ms-2 text-white">${ass}</span></div>
                               <div><span class="mini-badge">Status</span> <span class="ms-2 text-white">${statusSel.value}</span></div>`;
  }

  $('btnNext')?.addEventListener('click', () => {
    hideMsg();
    if (stepIndex===0 && !validateIncident()) return;
    const next = Math.min(stepIndex+1, steps.length-1);
    if (next===steps.length-1) buildReview();
    showStep(next);
  });
  $('btnBack')?.addEventListener('click', () => showStep(Math.max(stepIndex-1,0)));

  // Submit
  $('firForm')?.addEventListener('submit', async (e) => {
    e.preventDefault(); hideMsg();

    const mysqlDate = toMysqlDatetime(incidentDate.value);
    if (!mysqlDate) { showMsg('Invalid incident date/time'); return; }

    const payload = {
      incidentDate: mysqlDate,
      location: locationInp.value.trim(),
      description: desc.value.trim(),
      suspectedInfo: suspectedInfo.value.trim(),
      crimeTypeId: crimeTypeSel.value ? Number(crimeTypeSel.value) : null,
      status: statusSel.value,
      persons,
      suspects
    };

    try {
      const r = await fetch('/Crimsys/api/cop/fir_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(payload)
      });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error || 'Submit failed');

      if (overlay && overlayFirId) {
        overlayFirId.textContent = `#${d.FirID}`;
        overlay.classList.add('show');
      } else {
        showMsg(`FIR submitted successfully. FirID #${d.FirID}`, true);
      }
      persons.splice(0); suspects.splice(0);
      renderVW(); renderSus();
    } catch (err) {
      showMsg(err.message || 'Submit failed');
    }
  });

  // Save draft locally
  btnSaveDraft?.addEventListener('click', () => {
    try {
      localStorage.setItem('fir-draft', JSON.stringify({
        incidentDate: incidentDate.value,
        crimeTypeId: crimeTypeSel.value,
        location: locationInp.value,
        description: desc.value,
        suspectedInfo: suspectedInfo.value,
        persons, suspects,
        status: statusSel.value,
        assigned: assignedDisplay.value
      }));
      showMsg('Draft saved locally.', true);
    } catch { showMsg('Could not save draft'); }
  });
})();
