(function(){
  const $ = s => document.querySelector(s);
  const list = $('#list');
  const search = $('#crimeSearch');

  const viewModal = $('#viewModal');
  const viewBody = $('#viewBody');
  const btnCloseView = $('#btnCloseView');

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
        <div class="text-white-50 small">#${esc(t.CrimeTypeID)}</div>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <span class="status-pill ${String(t.IsActive)=='1'?'pill-green':'pill-red'}">${String(t.IsActive)=='1'?'Active':'Inactive'}</span>
        <button class="btn btn-outline-accent btn-sm btn-view">View</button>
      </div>`;
    wrap.querySelector('.btn-view').onclick = ()=> openView(t.CrimeTypeID);
    return wrap;
  }

  async function loadList(){
    list.innerHTML = '';
    try{
      const r = await fetch('/Crimsys/api/cop/crime_type_list.php');
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'Load failed');
      (d.items||[]).forEach(t=> list.appendChild(row(t)));
    }catch(e){
      list.innerHTML = `<div class="text-danger">Failed to load crime types</div>`;
    }
  }

  // --- View modal ---
  function closeView(){ viewModal.style.display='none'; }
  btnCloseView.onclick = closeView;
  viewModal.addEventListener('click', e=>{ if(e.target===viewModal) closeView(); });

  async function openView(id){
    try{
      const r = await fetch(`/Crimsys/api/cop/crime_type_view.php?id=${encodeURIComponent(id)}`);
      const d = await r.json();
      if(!d.ok) throw new Error(d.error||'View failed');
      const t = d.item;

      viewBody.innerHTML = `
        <div><span class="klabel">Crime Name</span>: <strong>${esc(t.Name)}</strong></div>
        <div><span class="klabel">Category</span>: ${esc(t.Category||'-')}</div>
        <div><span class="klabel">Severity</span>: ${esc(t.Severity ?? '-')}</div>
        <div><span class="klabel">Bailable</span>: ${String(t.Bailable)=='1'?'Yes':'No'}</div>
        <div><span class="klabel">Law Section</span>: ${esc(t.LawSection||'-')}</div>
        <div><span class="klabel">Description</span>: ${esc(t.Description||'-')}</div>
      `;
      viewModal.style.display='flex';
    }catch(e){
      viewBody.innerHTML = `<div class="text-danger">Failed to load details</div>`;
      viewModal.style.display='flex';
    }
  }

  // --- Search ---
  let cache=[], searchTimer=null;
  async function ensureCache(){
    if(cache.length) return;
    const r = await fetch('/Crimsys/api/cop/crime_type_list.php');
    const d = await r.json(); if (d.ok) cache = d.items||[];
  }
  async function onSearch(){
    const q = (search.value||'').trim().toLowerCase();
    if(!q){ loadList(); return; }
    await ensureCache();
    list.innerHTML='';
    cache.filter(x => (x.Name||'').toLowerCase().includes(q))
         .forEach(t => list.appendChild(row(t)));
  }

  search.addEventListener('input', ()=>{ clearTimeout(searchTimer); searchTimer=setTimeout(onSearch, 200); });

  // init
  loadList();
})();
