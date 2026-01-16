/*  FIR VIEW (phase-2 evidence actions fixed)
    - Glass actions (Open/Download/Delete) rendered inside each card
    - Upload modal wired
    - Custom confirm modal (no native alert)
    - Delete now works reliably
*/

(function(){
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const q = new URLSearchParams(location.search);
  const firId = parseInt(q.get('firId'), 10) || 0;

  const apiBase = '/Crimsys/api/cop';
  const API = {
    view:      `${apiBase}/fir_view.php?firId=${firId}`,
    evList:    `${apiBase}/fir_evidence_list.php?firId=${firId}`,
    evUpload:  `${apiBase}/fir_evidence_upload.php`,
    evDelete:  `${apiBase}/fir_evidence_delete.php`
  };

  // ---------- el cache ----------
  const el = {
    banner:      $('#msg'),
    evGrid:      $('#evGrid'),
    btnUpload:   $('#btnUpload'),
    overlayUp:   $('#overlayUpload'),
    upCaption:   $('#upCaption'),
    upFile:      $('#upFile'),
    upDo:        $('#upDo'),
    upCancel:    $('#upCancel'),

    // confirm
    overlayConfirm: $('#overlayConfirm'),
    confirmText:    $('#confirmText'),
    confirmOk:      $('#confirmOk'),
    confirmCancel:  $('#confirmCancel'),

    // overview targets
    firIdHdr:   $('#firIdHdr'),
    ovDate:     $('#ovDate'),
    ovCrime:    $('#ovCrime'),
    ovAssigned: $('#ovAssigned'),
    ovStatus:   $('#ovStatus'),
    ovLoc:      $('#ovLoc'),
    ovFiledBy:  $('#ovFiledBy'),
    ovDesc:     $('#ovDesc'),
    ovSus:      $('#ovSus'),
    vwList:     $('#vwList'),
    susList:    $('#susList'),
    asOfficerView: $('#asOfficerView'),
    asStatus:   $('#asStatus')
  };

  // ---------- helpers ----------
  const show = el => el?.classList.add('show');
  const hide = el => el?.classList.remove('show');

  function showMsg(text, ok=true){
    if(!el.banner) return;
    el.banner.textContent = text;
    el.banner.classList.remove('d-none');
    el.banner.style.color = '#fff';
    el.banner.style.border = `1px solid ${ ok ? 'rgba(16,185,129,.5)' : 'rgba(239,68,68,.5)' }`;
    setTimeout(()=> el.banner.classList.add('d-none'), 3000);
  }

  async function getJSON(url){
    const r = await fetch(url, {credentials:'same-origin'});
    // try JSON first, fall back to text for diagnostics
    try { return await r.json(); }
    catch(_){
      const t = await r.text();
      throw new Error(`Bad JSON from ${url}: ${t.slice(0,140)}`);
    }
  }

  async function postJSON(url, body){
    const r = await fetch(url, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(body || {}),
      credentials:'same-origin'
    });
    try { return await r.json(); }
    catch(_){
      const t = await r.text();
      throw new Error(`Bad JSON from ${url}: ${t.slice(0,140)}`);
    }
  }

  // ---------- renderers ----------
  function renderPeople(list, empty='—'){
    if(!list || !list.length) return empty;
    return list.map(p => {
      const role = p.Role || '';
      const nm = p.PersonName || '';
      const ct = p.ContactInfo || '';
      return `<span class="pill">${role}</span> ${nm} — ${ct}`;
    }).join('<br>');
  }

  function cardActionBtns(row){
    const id = row.EvidenceID;
    const path = row.FilePath;
    const caption = row.Caption || '—';
    const created = row.CreatedAt || '';

    return `
      <div class="col-md-4">
        <div class="glass ev-card">
          <img class="thumb" src="${path}" alt="">
          <div class="mt-2 v">${caption}</div>
          <div class="muted small">${created}</div>
          <div class="ev-actions mt-2">
            <button class="btn-glass btn-sm" data-open="${id}">Open</button>
            <a class="btn-glass btn-glass-accent btn-sm" href="${path}" download>Download</a>
            <button class="btn-glass btn-glass-danger btn-sm" data-del="${id}">Delete</button>
          </div>
        </div>
      </div>`;
  }

  function wireCardButtons(root){
    $$('[data-open]', root).forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-open');
        // simply open the image/pdf in a new tab
        const item = btn.closest('.ev-card');
        // find the img src inside this card
        const src = item?.querySelector('img.thumb')?.getAttribute('src');
        if(src) window.open(src, '_blank');
      });
    });

    $$('[data-del]', root).forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const evidenceId = Number(btn.getAttribute('data-del'));
        const ok = await confirmGlass(`Delete this evidence permanently?`);
        if(!ok) return;
        try{
          const resp = await postJSON(API.evDelete, { evidenceId, firId });
          if(resp.ok){
            showMsg('Evidence deleted.');
            await loadEvidence();
          }else{
            showMsg(resp.error || 'Delete failed', false);
          }
        }catch(err){
          console.error(err);
          showMsg('Network error while deleting', false);
        }
      });
    });
  }

  // ---------- confirm glass ----------
  function confirmGlass(text){
    return new Promise(resolve=>{
      el.confirmText.textContent = text || 'Are you sure?';
      show(el.overlayConfirm);
      const onOk = ()=> { cleanup(); resolve(true); };
      const onCancel = ()=> { cleanup(); resolve(false); };
      function cleanup(){
        hide(el.overlayConfirm);
        el.confirmOk.removeEventListener('click', onOk);
        el.confirmCancel.removeEventListener('click', onCancel);
      }
      el.confirmOk.addEventListener('click', onOk);
      el.confirmCancel.addEventListener('click', onCancel);
    });
  }

  // ---------- data loaders ----------
  async function loadFir(){
    try{
      const j = await getJSON(API.view);
      if(!j.ok) throw new Error(j.error || 'failed');

      const f = j.fir || {};
      el.firIdHdr.textContent = f.FirID || firId;
      el.ovDate.textContent = f.IncidentDate ? new Date(f.IncidentDate).toLocaleString() : '—';
      el.ovCrime.textContent = j.crimeName || f.CrimeTypeName || '—';
      el.ovAssigned.textContent = j.assignedName || (f.AssignedCopID ? `Cop #${f.AssignedCopID}` : '—');
      el.ovStatus.textContent = f.Status || 'Open';
      el.ovLoc.textContent = f.Location || '—';
      el.ovFiledBy.textContent = j.filedByName || (f.CopID ? `Cop #${f.CopID}` : '—');
      el.ovDesc.textContent = f.Description || '—';
      el.ovSus.textContent  = f.SuspectedInfo || '—';

      el.vwList.innerHTML  = renderPeople(j.vw);
      el.susList.innerHTML = renderPeople(j.suspects);
      el.asOfficerView.textContent = j.assignedName || (f.AssignedCopID ? `Cop #${f.AssignedCopID}` : '—');
      el.asStatus.textContent = f.Status || 'Open';

      await loadEvidence();
    }catch(err){
      console.error(err);
      showMsg('Network error: could not load FIR', false);
    }
  }

  async function loadEvidence(){
    try{
      const j = await getJSON(API.evList);
      const rows = (j && j.items) || [];
      el.evGrid.innerHTML = rows.map(cardActionBtns).join('') || `<div class="text-light">No evidence uploaded yet.</div>`;
      wireCardButtons(el.evGrid);
    }catch(err){
      console.error(err);
      el.evGrid.innerHTML = `<div class="text-danger">Failed to load evidence.</div>`;
    }
  }

  // ---------- Upload modal ----------
  el.btnUpload?.addEventListener('click', ()=> show(el.overlayUp));
  el.upCancel?.addEventListener('click', ()=> hide(el.overlayUp));

  el.upDo?.addEventListener('click', async ()=>{
    const file = el.upFile?.files?.[0];
    if(!file){ showMsg('Please choose a file', false); return; }

    const fd = new FormData();
    fd.append('firId', String(firId));
    fd.append('caption', el.upCaption.value || '');
    fd.append('file', file);

    try{
      const r = await fetch(API.evUpload, { method:'POST', body: fd, credentials:'same-origin' });
      const j = await r.json();
      if(j.ok){
        hide(el.overlayUp);
        el.upCaption.value=''; el.upFile.value='';
        showMsg('Evidence uploaded.');
        await loadEvidence();
      }else{
        showMsg(j.error || 'Upload failed', false);
      }
    }catch(err){
      console.error(err);
      showMsg('Network error while uploading', false);
    }
  });

  // ---------- boot ----------
  document.addEventListener('DOMContentLoaded', loadFir);
})();