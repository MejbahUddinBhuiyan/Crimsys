/*  FIR VIEW (phase-2 evidence actions fixed)
    - Glass actions (Open/Download/Delete) rendered inside each card
    - Upload modal wired
    - Custom confirm modal (no native alert)
    - Delete now works reliably
*/

(function(){
  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const q = new URLSearchParams(location.search);
  const firId = parseInt(q.get('firId'), 10) || 0;

  const apiBase = '/Crimsys/api/cop';
  const API = {
    // Phase 1/2
    view:      `${apiBase}/fir_view.php?firId=${firId}`,
    evList:    `${apiBase}/fir_evidence_list.php?firId=${firId}`,
    evUpload:  `${apiBase}/fir_evidence_upload.php`,
    evDelete:  `${apiBase}/fir_evidence_delete.php`,

    // Phase 3
    statusGet: `${apiBase}/fir_status_get.php?firId=${firId}`,
    statusSet: `${apiBase}/fir_status_update.php`,
    assign:    `${apiBase}/fir_assign.php`,
    notesList: `${apiBase}/fir_notes_list.php?firId=${firId}`,
    noteAdd:   `${apiBase}/fir_note_add.php`,
    timeline:  `${apiBase}/fir_timeline_list.php?firId=${firId}`,

    // Phase 4
    closeMark: `${apiBase}/fir_close.php`
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
    asStatus:   $('#asStatus'),

    // Phase 3 UI
    cmStatus:     $('#cmStatus'),
    cmSaveStatus: $('#cmSaveStatus'),
    asOfficer:    $('#asOfficer'),
    btnReassign:  $('#btnReassign'),
    overlayAssign:$('#overlayAssign'),
    asgCopId:     $('#asgCopId'),
    asgReason:    $('#asgReason'),
    asgSave:      $('#asgSave'),
    asgCancel:    $('#asgCancel'),
    btnAddNote:   $('#btnAddNote'),
    overlayNote:  $('#overlayNote'),
    noteText:     $('#noteText'),
    noteSave:     $('#noteSave'),
    noteCancel:   $('#noteCancel'),
    noteList:     $('#noteList'),
    tl:           $('#tl'),

    // Phase 4 UI
    closeRemarks:  $('#closeRemarks'),
    closeType:     $('#closeType'),
    btnClose:      $('#btnClose'),
    closeHelp:     $('#closeHelp')
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
    try { return await r.json(); }
    catch(_){
      const t = await r.text();
      throw new Error(`Bad JSON from ${url}: ${t.slice(0,140)}`);
    }
  }

  async function postForm(url, formObj){
    const body = new URLSearchParams(formObj || {});
    const r = await fetch(url, {
      method:'POST',
      body,
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
        const item = btn.closest('.ev-card');
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
          const resp = await postForm(API.evDelete, { evidenceId, firId });
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

  // ---------- data loaders (Phase 1/2) ----------
  async function loadFir(){
    try{
      const j = await getJSON(API.view);
      if(!j.ok) throw new Error(j.error || 'failed');

      const f = j.fir || {};
      el.firIdHdr.textContent = f.FirID || firId;
      el.ovDate.textContent = f.IncidentDate ? new Date(f.IncidentDate).toLocaleString() : '—';
      el.ovCrime.textContent = j.crimeName || f.CrimeTypeName || '—';
      const assignedStr = j.assignedName || (f.AssignedCopID ? `Cop #${f.AssignedCopID}` : '—');
      el.ovAssigned.textContent = assignedStr;
      el.ovStatus.textContent = f.Status || 'Open';
      el.ovLoc.textContent = f.Location || '—';
      el.ovFiledBy.textContent = j.filedByName || (f.CopID ? `Cop #${f.CopID}` : '—');
      el.ovDesc.textContent = f.Description || '—';
      el.ovSus.textContent  = f.SuspectedInfo || '—';

      el.vwList.innerHTML  = renderPeople(j.vw);
      el.susList.innerHTML = renderPeople(j.suspects);

      // Phase-1 quick view
      el.asOfficerView.textContent = assignedStr;
      el.asStatus.textContent = f.Status || 'Open';

      // Phase-3 mirror
      if (el.asOfficer) el.asOfficer.textContent = assignedStr;

      await loadEvidence();

      // Phase-3: preselect current status
      if (el.cmStatus) {
        try{
          const sg = await getJSON(API.statusGet);
          if (sg && sg.ok && sg.status) el.cmStatus.value = sg.status;
        }catch(_){}
      }
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

  // ---------- Upload modal (Phase 2) ----------
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

  // ================= PHASE 3 ONLY (existing) =================

  // Status save
  el.cmSaveStatus?.addEventListener('click', async ()=>{
    const val = el.cmStatus?.value || 'Open';
    try{
      const d = await postForm(API.statusSet, { firId, status: val });
      if(!d.ok) throw new Error(d.error||'update_failed');
      el.asStatus.textContent = val;
      el.ovStatus.textContent = val;
      showMsg('Status updated.');
      loadTimeline();
    }catch(e){ showMsg(e.message||'Status update failed', false); }
  });

  // Reassign
  el.btnReassign?.addEventListener('click', ()=> show(el.overlayAssign));
  el.asgCancel?.addEventListener('click', ()=> hide(el.overlayAssign));
  el.asgSave?.addEventListener('click', async ()=>{
    const newCopId = (el.asgCopId?.value || '').trim();
    const reason   = (el.asgReason?.value || '').trim();
    if(!newCopId){ showMsg('Enter a valid Cop ID', false); return; }
    try{
      const d = await postForm(API.assign, { firId, newCopId, reason, byCopId: 1 });
      if(!d.ok) throw new Error(d.error||'reassign_failed');
      hide(el.overlayAssign);
      showMsg('Assignment updated.');
      await loadFir();
      await loadTimeline();
    }catch(e){ showMsg(e.message||'Reassign failed', false); }
  });

  // Notes (P3): show text + time
  el.btnAddNote?.addEventListener('click', ()=> show(el.overlayNote));
  el.noteCancel?.addEventListener('click', ()=> hide(el.overlayNote));
  el.noteSave?.addEventListener('click', async ()=>{
    const txt = (el.noteText?.value || '').trim();
    if(!txt){ showMsg('Type a note first', false); return; }
    try{
      const d = await postForm(API.noteAdd, { firId, text: txt, byCopId: 1 });
      if(!d.ok) throw new Error(d.error||'note_failed');
      hide(el.overlayNote);
      el.noteText.value = '';
      await loadNotes();
      await loadTimeline();
      showMsg('Note added.');
    }catch(e){ showMsg(e.message||'Note add failed', false); }
  });

  async function loadNotes(){
    if(!el.noteList) return;
    try{
      const d = await getJSON(API.notesList);
      if(!d.ok) throw new Error(d.error||'load_failed');
      const arr = d.items||[];
      if(arr.length===0){
        el.noteList.innerHTML = '<div class="muted">No notes yet.</div>';
        return;
      }
      el.noteList.innerHTML = arr.map(n=>{
        const when  = n.CreatedAt || '';
        const txt   = String(n.NoteText||'').replaceAll('<','&lt;');
        return `<div class="note-item">
                  <div class="note-text" style="color:#fff">${txt}</div>
                  <div class="note-meta">${when}</div>
                </div>`;
      }).join('');
    }catch(e){ showMsg(e.message||'Notes load failed', false); }
  }

  // Timeline
  async function loadTimeline(){
    if(!el.tl) return;
    try{
      const d = await getJSON(API.timeline);
      if(!d.ok) throw new Error(d.error||'load_failed');
      const arr = d.items||[];
      if(arr.length===0){
        el.tl.innerHTML = '<div class="muted">No timeline entries yet.</div>';
        return;
      }
      el.tl.innerHTML = arr.map(t=>
        `<div class="tl-item">
           <div class="tl-dot"></div>
           <div class="tl-body">
             <b>${t.Type}</b>: ${t.Message}
             <div class="note-meta">by ${t.ByCopID ? `Cop #${t.ByCopID}` : 'Cop #'} • ${t.CreatedAt || ''}</div>
           </div>
         </div>`
      ).join('');
    }catch(e){ showMsg(e.message||'Timeline load failed', false); }
  }

  // ================= PHASE 4 ONLY (fixed binding) =================

// ================= PHASE 4: robust click binding =================
let _closingInFlight = false;

function onCloseClick(e){
  if (e) { e.preventDefault(); e.stopPropagation(); }
  if (_closingInFlight) return;

  const remarksEl = document.getElementById('closeRemarks');
  const typeEl    = document.getElementById('closeType');
  const btn       = document.getElementById('btnClose');

  const remarks = (remarksEl?.value || '').trim();
  const type    = typeEl?.value || 'Resolved';

  if (type !== 'Resolved') { 
    showMsg('Only "Resolved" is supported now.', false); 
    return; 
  }

  // If you have the logged-in cop id stored somewhere, use it:
  // e.g. window.currentCopId || localStorage.getItem('copId')
  const byCopId = (window.currentCopId && Number(window.currentCopId)) || 1;

  // lock UI
  _closingInFlight = true;
  if (btn) {
    btn.setAttribute('type', 'button'); // never submit a form
    btn.disabled = true;
  }

  postForm('/Crimsys/api/cop/fir_close.php', {
    firId, type, remarks, byCopId
  })
  .then(d => {
    if (!d || d.ok !== true) throw new Error(d?.error || 'close_failed');

    // Update UI
    const asStatus = document.getElementById('asStatus');
    const ovStatus = document.getElementById('ovStatus');
    if (asStatus) asStatus.textContent = 'Resolved';
    if (ovStatus) ovStatus.textContent = 'Resolved';
    if (remarksEl) remarksEl.value = '';

    showMsg('Case marked as Resolved.');
    if (typeof loadTimeline === 'function') return loadTimeline();
  })
  .catch(err => {
    showMsg(err.message || 'Close failed', false);
  })
  .finally(() => {
    _closingInFlight = false;
    if (btn) btn.disabled = false;
  });
}

// Bind once DOM is ready AND keep a delegated fallback
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnClose');
  if (btn) {
    btn.setAttribute('type', 'button');        // never submit a form
    btn.addEventListener('click', onCloseClick);
  }
});

// Delegation guard (covers dynamic re-rendering or bubbling from inner spans)
document.addEventListener('click', (ev) => {
  const targetBtn = ev.target.closest('#btnClose');
  if (!targetBtn) return;
  targetBtn.setAttribute('type', 'button');
  onCloseClick(ev);
});


  // ---------- boot ----------
  document.addEventListener('DOMContentLoaded', async ()=>{
    // Ensure the close button behaves like a normal button (no form submit)
    const btn = el.btnClose || document.getElementById('btnClose');
    if (btn) {
      if (!btn.getAttribute('type')) btn.setAttribute('type','button');
      btn.addEventListener('click', onCloseClick);
    }

    await loadFir();         // Phase 1/2
    await loadNotes();       // Phase 3
    await loadTimeline();    // Phase 3
  });
})();
