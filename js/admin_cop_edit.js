/* Admin – Edit Cop profile
   Uses:
     GET  /Crimsys/api/admin/cop_lookup.php?copid=12345678
     POST /Crimsys/api/admin/cop_update.php
*/

(function(){
  const msg        = document.getElementById('msg');
  const searchForm = document.getElementById('searchForm');
  const editBlock  = document.getElementById('editBlock');

  const pPhoto   = document.getElementById('p_photo');
  const pName    = document.getElementById('p_name');
  const pId      = document.getElementById('p_id');
  const pBadge   = document.getElementById('p_badge');
  const pRank    = document.getElementById('p_rank');

  const fEmail   = document.getElementById('email');
  const fContact = document.getElementById('contact');
  const fRank    = document.getElementById('rank');
  const fStation = document.getElementById('station');
  const fPresent = document.getElementById('present');

  const updateForm = document.getElementById('updateForm');

  let loadedCopId = null;

  function showBanner(text, isErr=false){
    msg.className = 'alert-banner' + (isErr ? ' border-danger' : '');
    msg.textContent = text;
    msg.classList.remove('d-none');
  }
  function hideBanner(){ msg.classList.add('d-none'); msg.textContent=''; }

  function photoURL(cop){
    if (cop.PhotoURL && cop.PhotoURL.trim()) return cop.PhotoURL;
    if (cop.PhotoPath && cop.PhotoPath.trim()){
      let p = cop.PhotoPath.trim();
      if (!p.startsWith('/')) p = '/' + p;
      return '/Crimsys' + p;
    }
    return '/Crimsys/img/cops/_placeholder.png';
  }

  // ---- Load by CopID ----
  searchForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    hideBanner();
    editBlock.classList.add('d-none');

    const input = document.getElementById('copid');
    if (!input.checkValidity()){ input.reportValidity(); return; }

    const id = input.value.trim();

    try{
      const r = await fetch('/Crimsys/api/admin/cop_lookup.php?copid='+encodeURIComponent(id), {credentials:'include'});
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');

      const data = await r.json();
      if (!data.ok){
        if (data.error === 'not_found' || data.error === 'invalid_copid')
          showBanner('Invalid Cop ID.', true);
        else
          showBanner(data.error || 'Lookup failed', true);
        return;
      }

      const c = data.cop;
      loadedCopId = c.CopID;

      // header preview
      pPhoto.src = photoURL(c);
      pPhoto.onerror = ()=>{ pPhoto.onerror=null; pPhoto.src='/Crimsys/img/cops/_placeholder.png'; };
      pName.textContent  = (c.Name || '—').toUpperCase();
      pId.textContent    = c.CopID || '—';
      pBadge.textContent = c.BadgeNo || '—';
      pRank.textContent  = c.Rank || '—';

      // editable fields
      fEmail.value   = c.Email || '';
      fContact.value = c.ContactNo || '';
      fRank.value    = c.Rank || '';
      fStation.value = c.StationName || '';
      fPresent.value = c.PresentAddress || '';

      editBlock.classList.remove('d-none');

    }catch(err){
      showBanner(err.message || 'Lookup failed', true);
    }
  });

  // ---- Save changes ----
  updateForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    hideBanner();

    if (!loadedCopId){ showBanner('Load a profile first.', true); return; }

    try{
      const payload = {
        copid: loadedCopId,
        email: (fEmail.value || '').trim(),
        contact: (fContact.value || '').trim(),
        rank: (fRank.value || '').trim(),
        station: (fStation.value || '').trim(),
        present: (fPresent.value || '').trim()
      };

      const r = await fetch('/Crimsys/api/admin/cop_update.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'include',
        body: JSON.stringify(payload)
      });
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');

      const data = await r.json();
      if (!data.ok) throw new Error(data.error || 'Update failed');

      showBanner('Profile updated successfully.');
      // update the preview
      pRank.textContent = payload.rank;
    }catch(err){
      showBanner(err.message || 'Update failed', true);
    }
  });
})();
