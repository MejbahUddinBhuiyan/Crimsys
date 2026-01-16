(function(){
  const $ = s => document.querySelector(s);
  const msg = $('#msg');
  const list = $('#list');
  const filterSel = $('#filterSel');
  const newName = $('#newName');
  const btnAdd = $('#btnAdd');
  const search = $('#crimeSearch');

  const viewModal = $('#viewModal');
  const viewBody = $('#viewBody');
  const btnCloseView = $('#btnCloseView');

  function showMsg(text, ok=false){
    msg.textContent = text;
    msg.className = 'alert-banner ' + (ok ? 'alert-success-banner' : 'alert-danger-banner');
    msg.classList.remove('d-none');
    setTimeout(()=> msg.classList.add('d-none'), 4000);
  }

  // SAFER: always coerce to string before replace()
  function esc(s){
    return String(s ?? '').replace(/[&<>"']/g, m => (
      {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]
    ));
  }

  function row(t){
    const wrap = document.createElement('div');
    wrap.className = 'crime-card';
    wrap.innerHTML = `
      <div>
        <div class="text-white fw-semibold">${esc(t.Name)}</div>
        <div class="text-white-50 small">
          #${esc(t.CrimeTypeID)}${t.Code? ' • '+esc(t.Code):''}${t.CreatedAt? ' • Created: '+esc(t.CreatedAt):''}
        </div>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <span class="status-pill ${String(t.IsActive)=='1'?'pill-green':'pill-red'}">${String(t.IsActive)=='1'?'Active':'Inactive'}</span>
        <button class="btn btn-outline-accent btn-sm btn-view">View</button>
        <button class="btn btn-outline-accent btn-sm btn-toggle">${String(t.IsActive)=='1'?'Deactivate':'Activate'}</button>
        <button class="btn btn-outline-accent btn-sm btn-rename">Rename</button>
      </div>`;
    wrap.querySelector('.btn-rename').onclick = ()=> renameType(t.CrimeTypeID, t.Name);
    wrap.querySelector('.btn-toggle').onclick = ()=> toggleType(t.CrimeTypeID, String(t.IsActive)=='1'?0:1);
    wrap.querySelector('.btn-view').onclick   = ()=> openView(t.CrimeTypeID);
    return wrap;
  }

  async function loadList(){
    list.innerHTML = '';
    let url = '/Crimsys/api/cop/crime_type_list.php';
    if (filterSel.value === 'active')   url += '?active=1';
    if (filterSel.value === 'inactive') url += '?active=0';
    try{
      const r = await fetch(url,{credentials:'include'});
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'Load failed');
      (d.items||[]).forEach(t=> list.appendChild(row(t)));
    }catch(e){ showMsg(e.message||'Failed to load'); }
  }

  async function addType(){
    const name = (newName.value||'').trim();
    if (name.length < 2) return showMsg('Name too short');
    try{
      const r = await fetch('/Crimsys/api/cop/crime_type_create.php',{
        method:'POST',credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({name})
      });
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'Add failed');
      newName.value = '';
      showMsg('Crime type created', true);
      loadList();
    }catch(e){ showMsg(e.message||'Add failed'); }
  }

  async function toggleType(id,isActive){
    try{
      const r = await fetch('/Crimsys/api/cop/crime_type_update.php',{
        method:'POST',credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id, action:'toggle', isActive})
      });
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'Update failed');
      loadList();
    }catch(e){ showMsg(e.message||'Update failed'); }
  }

  async function renameType(id,current){
    const nn = prompt('Enter new name:', current||'');
    if (nn===null) return;
    const name = nn.trim();
    if (!name) return showMsg('Name cannot be empty');
    try{
      const r = await fetch('/Crimsys/api/cop/crime_type_update.php',{
        method:'POST',credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id, action:'rename', name})
      });
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'Rename failed');
      showMsg('Renamed', true);
      loadList();
    }catch(e){ showMsg(e.message||'Rename failed'); }
  }

  // --- View modal ---
  function closeView(){ viewModal.style.display='none'; }
  btnCloseView.onclick = closeView;
  viewModal.addEventListener('click', e=>{ if(e.target===viewModal) closeView(); });

  async function openView(id){
    try{
      const r = await fetch(`/Crimsys/api/cop/crime_type_view.php?id=${encodeURIComponent(id)}`, {credentials:'include'});
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'View failed');
      const t = d.item;
      const yesno = v => (String(v)=='1' ? 'Yes' : 'No');

      viewBody.innerHTML = `
        <div><span class="klabel">Crime Name</span>: <strong>${esc(t.Name)}</strong></div>
        <div><span class="klabel">Category</span>: ${esc(t.Category||'-')}</div>
        <div><span class="klabel">Severity</span>: ${esc(t.Severity ?? '-')}</div>
        <div><span class="klabel">Bailable</span>: ${yesno(t.Bailable)}</div>
        <div><span class="klabel">Law Section</span>: ${esc(t.LawSection||'-')}</div>
        <div><span class="klabel">Description</span>: ${esc(t.Description||'-')}</div>
      `;
      viewModal.style.display='flex';
    }catch(e){ showMsg(e.message||'View failed'); }
  }

  // --- Search (client-side suggest) ---
  let searchTimer=null, cache=[];
  async function ensureCache(){
    if(cache.length) return;
    const r = await fetch('/Crimsys/api/cop/crime_type_list.php', {credentials:'include'});
    const d = await r.json(); if (d.ok) cache = d.items||[];
  }
  async function onSearch(){
    const q = (search.value||'').trim().toLowerCase();
    if(!q){ loadList(); return; }
    await ensureCache();
    list.innerHTML='';
    cache
      .filter(x => (x.Name||'').toLowerCase().includes(q))
      .forEach(t => list.appendChild(row(t)));
  }

  // events
  btnAdd.addEventListener('click', addType);
  filterSel.addEventListener('change', loadList);
  search.addEventListener('input', ()=>{ clearTimeout(searchTimer); searchTimer=setTimeout(onSearch, 200); });

  // init
  loadList();
})();
