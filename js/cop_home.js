// /Crimsys/js/cop_home.js
(function(){
  async function loadMe(){
    try{
      const r = await fetch('/Crimsys/api/cop_whoami.php', {credentials:'include'});
      const data = await r.json();
      if (!data.ok) {
        // not logged in or not found → keep placeholders, no redirect
        console.warn('[cop_home] whoami:', data.error);
        return;
      }

      const c = data.cop || {};
      const byId = id => document.getElementById(id);

      // Header elements existing in your cop_home.html
      const photo   = byId('copPhoto');
      const name    = byId('copName');
      const idEl    = byId('copId');
      const rank    = byId('copRank');
      const badge   = byId('copBadge');
      const station = byId('copStation');
      const status  = byId('copStatus');

      if (photo && c.PhotoURL){
        photo.src = c.PhotoURL;
        photo.onerror = ()=>{ photo.src='/Crimsys/img/cops/_placeholder.png'; };
      }
      if (name)    name.textContent = (c.Name || '— COP NAME —').toUpperCase();
      if (idEl)    idEl.textContent = c.CopID || '—';
      if (rank)    rank.textContent = c.Rank || '—';
      if (badge)   badge.textContent = c.BadgeNo || '—';
      if (station) station.textContent= c.Station || '—';

      if (status){
        if (c.Suspended){
          status.textContent = 'Suspended';
          status.classList.remove('pill-green');
          status.classList.add('pill-red');
        }else{
          status.textContent = 'Active';
          status.classList.remove('pill-red');
          status.classList.add('pill-green');
        }
      }
    }catch(err){
      console.error('[cop_home] whoami failed:', err);
    }
  }
  loadMe();
})();
