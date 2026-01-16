/* Admin – Delete Cop
   Requires: /Crimsys/api/admin/cop_lookup.php  (GET ?copid=)
             /Crimsys/api/admin/cop_delete.php  (POST {copid})
*/

(function () {
  const msg  = document.getElementById('msg');
  const form = document.getElementById('searchForm');
  const block = document.getElementById('profileBlock');

  const pPhoto   = document.getElementById('p_photo');
  const pName    = document.getElementById('p_name');
  const pId      = document.getElementById('p_id');
  const pRank    = document.getElementById('p_rank');
  const pMisc    = document.getElementById('p_misc');
  const pEmail   = document.getElementById('p_email');
  const pContact = document.getElementById('p_contact');
  const pStation = document.getElementById('p_station');
  const pPresent = document.getElementById('p_present');
  const pPerm    = document.getElementById('p_perm');

  const btnDelete = document.getElementById('btnDelete');

  let loadedId = null;

  function showBanner(text, isErr=false){
    msg.className = 'alert-banner' + (isErr ? ' border-danger' : '');
    msg.textContent = text;
    msg.classList.remove('d-none');
  }
  function hideBanner(){ msg.classList.add('d-none'); msg.textContent = ''; }

  function photoURL(cop){
    if (cop.PhotoURL && cop.PhotoURL.trim() !== '') return cop.PhotoURL;
    if (cop.PhotoPath && cop.PhotoPath.trim() !== '') {
      let p = cop.PhotoPath.trim();
      if (!p.startsWith('/')) p = '/'+p;
      return '/Crimsys' + p;
    }
    return '/Crimsys/img/cops/_placeholder.png';
  }

  // ---- glass confirm ----
  function confirmGlass(message){
    return new Promise(resolve=>{
      const modal = document.getElementById('glassConfirm');
      modal.querySelector('.msg').textContent = message || 'Are you sure?';
      const okBtn = modal.querySelector('.btn-ok');
      const cancelBtn = modal.querySelector('.btn-cancel');

      const close = (val)=>{
        modal.style.display = 'none';
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        resolve(val);
      };
      const onOk = ()=> close(true);
      const onCancel = ()=> close(false);

      modal.style.display = 'flex';
      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);
    });
  }

  // ---- Load profile by id ----
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    hideBanner();
    block.classList.add('d-none');
    loadedId = null;

    const input = document.getElementById('copid');
    if (!input.checkValidity()){
      input.reportValidity();
      return;
    }
    const id = input.value.trim();

    try{
      const r = await fetch('/Crimsys/api/admin/cop_lookup.php?copid='+encodeURIComponent(id), {credentials:'include'});
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');

      const data = await r.json();
      if(!data.ok) throw new Error(data.error || 'Lookup failed');

      const c = data.cop || {};
      loadedId = c.CopID;

      pPhoto.src = photoURL(c);
      pPhoto.onerror = ()=>{ pPhoto.onerror=null; pPhoto.src='/Crimsys/img/cops/_placeholder.png'; };

      pName.textContent = (c.Name || '—').toUpperCase();
      pId.textContent   = c.CopID || '—';
      pRank.textContent = c.Rank  || '—';
      pMisc.textContent = 'Badge ' + (c.BadgeNo || '—');

      pEmail.textContent   = c.Email     || '—';
      pContact.textContent = c.ContactNo || '—';
      pStation.textContent = c.StationName || c.Station || '—';
      pPresent.textContent = c.PresentAddress   || c.PresentAddr   || '—';
      pPerm.textContent    = c.PermanentAddress || c.PermanentAddr || '—';

      block.classList.remove('d-none');
    }catch(err){
      showBanner(err.message || 'Lookup failed', true);
    }
  });

  // ---- Delete profile ----
  btnDelete.addEventListener('click', async ()=>{
    if (!loadedId) return;

    const sure = await confirmGlass('Are you sure you want to delete this cop profile?');
    if (!sure) return;

    try{
      const r = await fetch('/Crimsys/api/admin/cop_delete.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'include',
        body: JSON.stringify({copid: loadedId})
      });
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');

      const data = await r.json();
      if(!data.ok) throw new Error(data.error || 'Delete failed');

      showBanner('Profile deleted successfully.');
      block.classList.add('d-none');
      loadedId = null;
    }catch(err){
      showBanner(err.message || 'Delete failed', true);
    }
  });

})();
