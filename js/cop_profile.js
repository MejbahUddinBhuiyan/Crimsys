// /Crimsys/js/cop_profile.js
(function(){
  const msg = document.getElementById('msg');

  function showMsg(text, ok=false){
    msg.textContent = text;
    msg.className = 'alert-banner ' + (ok ? 'alert-success-banner' : 'alert-danger-banner');
    msg.classList.remove('d-none');
  }
  function hideMsg(){ msg.classList.add('d-none'); msg.textContent=''; }

  // Load profile (whoami)
  async function loadProfile(){
    try{
      const r = await fetch('/Crimsys/api/cop_whoami.php', {credentials:'include'});
      const data = await r.json();
      if(!data.ok){ showMsg(data.error || 'Load failed'); return; }

      const c = data.cop || {};
      const byId = id => document.getElementById(id);

      // Header
      const img  = byId('p_photo');
      img.src = c.PhotoURL || '/Crimsys/img/cops/_placeholder.png';
      img.onerror = ()=>{ img.src='/Crimsys/img/cops/_placeholder.png'; };

      byId('p_name').textContent    = (c.Name || '—').toUpperCase();
      byId('p_id').textContent      = c.CopID || '—';
      byId('p_rank').textContent    = c.Rank || '—';
      byId('p_badge').textContent   = c.BadgeNo || '—';
      byId('p_station').textContent = c.Station || '—';
      byId('p_phone').textContent   = c.ContactNo || '—';
      byId('p_email').textContent   = c.Email || '—';

      // Fill preview with current photo too
      const prev = document.getElementById('prevImg');
      prev.src = img.src;

    }catch(e){
      showMsg('Network error while loading profile.');
    }
  }

  // ---- Photo update ----
  const photoFile = document.getElementById('photoFile');
  const prevImg   = document.getElementById('prevImg');
  const btnSavePhoto = document.getElementById('btnSavePhoto');
  const btnResetPhoto = document.getElementById('btnResetPhoto');

  if(photoFile){
    photoFile.addEventListener('change', ()=>{
      const f = photoFile.files && photoFile.files[0];
      if(!f) return;
      const url = URL.createObjectURL(f);
      prevImg.src = url;
    });
  }

  if(btnSavePhoto){
    btnSavePhoto.addEventListener('click', async ()=>{
      hideMsg();
      const f = (photoFile.files && photoFile.files[0]) || null;
      if(!f){ showMsg('Choose a photo to upload.'); return; }
      const fd = new FormData();
      fd.append('photo', f);
      try{
        const r = await fetch('/Crimsys/api/cop_set_photo.php', {
          method:'POST', credentials:'include', body: fd
        });
        const d = await r.json();
        if(!d.ok) throw new Error(d.error || 'Upload failed');
        document.getElementById('p_photo').src = d.url || d.photo || prevImg.src;
        showMsg('Photo updated successfully.', true);
      }catch(e){
        showMsg(e.message || 'Photo update failed');
      }
    });
  }

  if(btnResetPhoto){
    btnResetPhoto.addEventListener('click', ()=>{
      photoFile.value = '';
      // Reset preview to header photo
      const current = document.getElementById('p_photo').src;
      prevImg.src = current || '/Crimsys/img/cops/_placeholder.png';
      hideMsg();
    });
  }

  // ---- Password update ----
  const btnSavePwd  = document.getElementById('btnSavePwd');
  const btnResetPwd = document.getElementById('btnResetPwd');

  if(btnSavePwd){
    btnSavePwd.addEventListener('click', async ()=>{
      hideMsg();
      const a = document.getElementById('newpass').value.trim();
      const b = document.getElementById('confpass').value.trim();
      if(a.length < 6){ showMsg('Password must be at least 6 characters.'); return; }
      if(a !== b){ showMsg('Passwords do not match.'); return; }

      try{
        const r = await fetch('/Crimsys/api/cop_change_password.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          credentials:'include',
          body: JSON.stringify({ newpass:a })
        });
        const d = await r.json();
        if(!d.ok) throw new Error(d.error || 'Password update failed');
        document.getElementById('newpass').value  = '';
        document.getElementById('confpass').value = '';
        showMsg('Password updated successfully.', true);
      }catch(e){
        showMsg(e.message || 'Password update failed');
      }
    });
  }

  if(btnResetPwd){
    btnResetPwd.addEventListener('click', ()=>{
      document.getElementById('newpass').value  = '';
      document.getElementById('confpass').value = '';
      hideMsg();
    });
  }

  // init
  loadProfile();
})();
