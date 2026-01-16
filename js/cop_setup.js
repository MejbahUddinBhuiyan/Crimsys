// /Crimsys/js/cop_setup.js
(function(){
  const banner = document.getElementById('banner');
  const stepPass = document.getElementById('stepPass');
  const stepPhoto= document.getElementById('stepPhoto');

  function showBanner(t, err=false){
    banner.textContent = t;
    banner.className = 'alert-banner ' + (err ? 'alert-danger-banner' : 'alert-success-banner');
    banner.classList.remove('d-none');
  }
  function hideBanner(){ banner.classList.add('d-none'); banner.textContent=''; }

  async function fetchMe(){
    const r = await fetch('/Crimsys/api/cop_me.php', {credentials:'include'});
    const data = await r.json();
    if(!data.ok) throw new Error(data.error || 'Unauthorized');
    return data;
  }

  async function init(){
    hideBanner();
    try{
      const me = await fetchMe();
      // reveal steps as needed
      if(me.mustPass === 1) stepPass.classList.remove('d-none');
      if(me.mustPhoto === 1) stepPhoto.classList.remove('d-none');
      if(me.mustPass !== 1 && me.mustPhoto !== 1){
        // nothing required
        location.href = '/Crimsys/html/cop_home.html';
      }
    }catch(e){
      showBanner(e.message || 'Failed', true);
    }
  }

  // Save password
  const btnSavePass = document.getElementById('btnSavePass');
  if(btnSavePass){
    btnSavePass.addEventListener('click', async ()=>{
      hideBanner();
      const a = document.getElementById('newpass').value;
      const b = document.getElementById('confpass').value;
      if(a.length < 6){ showBanner('Password too short', true); return; }
      if(a !== b){ showBanner('Passwords do not match', true); return; }
      try{
        const r = await fetch('/Crimsys/api/cop_change_password.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          credentials:'include', body: JSON.stringify({ newpass:a })
        });
        const d = await r.json();
        if(!d.ok) throw new Error(d.error || 'Save failed');
        showBanner('Password updated.');
        stepPass.classList.add('d-none');
        // re-check if photo still needed
        const me = await fetchMe();
        if(me.mustPhoto !== 1) location.href='/Crimsys/html/cop_home.html';
      }catch(e){ showBanner(e.message, true); }
    });
  }

  // Photo preview
  const photoFile = document.getElementById('photoFile');
  const prevImg   = document.getElementById('prevImg');
  if(photoFile){
    photoFile.addEventListener('change', ()=>{
      const f = photoFile.files && photoFile.files[0];
      if(!f) return;
      const url = URL.createObjectURL(f);
      prevImg.src = url;
    });
  }

  // Save photo
  const btnSavePhoto = document.getElementById('btnSavePhoto');
  if(btnSavePhoto){
    btnSavePhoto.addEventListener('click', async ()=>{
      hideBanner();
      const f = photoFile.files && photoFile.files[0];
      if(!f){ showBanner('Choose a photo', true); return; }
      const fd = new FormData();
      fd.append('photo', f);
      try{
        const r = await fetch('/Crimsys/api/cop_set_photo.php', {
          method:'POST', credentials:'include', body: fd
        });
        const d = await r.json();
        if(!d.ok) throw new Error(d.error || 'Upload failed');
        showBanner('Photo saved.');
        stepPhoto.classList.add('d-none');
        // done -> go to home
        location.href = '/Crimsys/html/cop_home.html';
      }catch(e){ showBanner(e.message, true); }
    });
  }

  init();
})();
