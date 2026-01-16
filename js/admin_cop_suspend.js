/*  /Crimsys/js/admin_cop_suspend.js
    One-button suspend / unsuspend controller
*/
(function () {
  // --- DOM refs ---
  const msg          = document.getElementById('msg');
  const searchForm   = document.getElementById('searchForm');
  const suspendBlock = document.getElementById('suspendBlock');

  const pPhoto   = document.getElementById('p_photo');
  const pName    = document.getElementById('p_name');
  const pId      = document.getElementById('p_id');
  const pRank    = document.getElementById('p_rank');
  const pBadge   = document.getElementById('p_badge');
  const pStation = document.getElementById('p_station');

  const statusPill  = document.getElementById('statusPill');
  const exactUntil  = document.getElementById('exactUntil');
  const btnToggle   = document.getElementById('btnToggleSuspend');

  // --- state ---
  let loadedCopId = null;           // current CopID
  let suspendedUntilISO = null;     // null or ISO string

  // --- helpers ---
  function showBanner(text, isErr = false) {
    msg.className = 'alert-banner' + (isErr ? ' border-danger' : '');
    msg.textContent = text;
    msg.classList.remove('d-none');
  }
  function hideBanner() {
    msg.classList.add('d-none');
    msg.textContent = '';
  }

  function photoURL(cop) {
    if (cop.PhotoURL && cop.PhotoURL.trim()) return cop.PhotoURL;
    if (cop.PhotoPath && cop.PhotoPath.trim()) {
      let p = cop.PhotoPath.trim();
      if (!p.startsWith('/')) p = '/' + p;
      return '/Crimsys' + p;     // turn db path into web path
    }
    return '/Crimsys/img/cops/_placeholder.png';
  }

  function isCurrentlyActive(untilISO) {
    if (!untilISO) return true;
    const u = new Date(untilISO);
    if (isNaN(u.getTime())) return true;
    return u <= new Date(); // date passed -> active again
  }

  function paintStatus(untilISO) {
    if (isCurrentlyActive(untilISO)) {
      statusPill.textContent = 'Active';
      statusPill.classList.remove('pill-red');
      statusPill.classList.add('pill-green');

      btnToggle.textContent = 'Suspend';
      btnToggle.classList.remove('btn-outline-accent');
      btnToggle.classList.add('btn-accent');
    } else {
      const u = new Date(untilISO);
      statusPill.textContent = 'Suspended until ' + u.toLocaleString();
      statusPill.classList.remove('pill-green');
      statusPill.classList.add('pill-red');

      btnToggle.textContent = 'Unsuspend';
      btnToggle.classList.remove('btn-accent');
      btnToggle.classList.add('btn-outline-accent');
    }
  }

  // --- Load profile by CopID ---
  searchForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideBanner();
    suspendBlock.classList.add('d-none');

    const input = document.getElementById('copid');
    if (!input.checkValidity()) { input.reportValidity(); return; }

    const id = input.value.trim();

    try {
      const r = await fetch('/Crimsys/api/admin/cop_lookup.php?copid=' + encodeURIComponent(id), {
        credentials: 'include'
      });
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');

      const data = await r.json();
      if (!data.ok) {
        if (data.error === 'not_found' || data.error === 'invalid_copid') {
          showBanner('Invalid Cop ID.', true);
        } else {
          showBanner(data.error || 'Lookup failed', true);
        }
        return;
      }

      const c = data.cop;
      loadedCopId = c.CopID;

      pPhoto.src = photoURL(c);
      pPhoto.onerror = () => { pPhoto.onerror = null; pPhoto.src = '/Crimsys/img/cops/_placeholder.png'; };

      pName.textContent    = (c.Name || '—').toUpperCase();
      pId.textContent      = c.CopID || '—';
      pRank.textContent    = c.Rank || '—';
      pBadge.textContent   = c.BadgeNo || '—';
      pStation.textContent = c.StationName || '—';

      suspendedUntilISO = c.SuspendedUntil || null;
      paintStatus(suspendedUntilISO);

      suspendBlock.classList.remove('d-none');
    } catch (err) {
      showBanner(err.message || 'Lookup failed', true);
    }
  });

  // --- Toggle button click (Suspend OR Unsuspend) ---
  btnToggle.addEventListener('click', async () => {
    hideBanner();
    if (!loadedCopId) { showBanner('Load a profile first.', true); return; }

    // If currently active -> perform SUSPEND
    if (isCurrentlyActive(suspendedUntilISO)) {
      // Determine end time: exact chosen OR default +1 day
      let until = null;
      if (exactUntil.value) {
        until = new Date(exactUntil.value);
      } else {
        until = new Date();
        until.setDate(until.getDate() + 1);  // default 1 day
      }
      if (isNaN(until.getTime())) { showBanner('Invalid end time.', true); return; }
      const iso = until.toISOString();

      try {
        const r = await fetch('/Crimsys/api/admin/cop_suspend.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ copid: loadedCopId, until: iso })
        });
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Suspend failed');

        suspendedUntilISO = data.until || iso;
        paintStatus(suspendedUntilISO);
        exactUntil.value = ''; // reset field
        showBanner('Cop suspended successfully.');
      } catch (err) {
        showBanner(err.message || 'Suspend failed', true);
      }

    } else {
      // Currently suspended -> perform UNSUSPEND
      try {
        const r = await fetch('/Crimsys/api/admin/cop_suspend.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ copid: loadedCopId, unsuspend: true })
        });
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error('Server returned non-JSON');
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Unsuspend failed');

        suspendedUntilISO = null;
        paintStatus(null);
        showBanner('Cop unsuspended successfully.');
      } catch (err) {
        showBanner(err.message || 'Unsuspend failed', true);
      }
    }
  });

})();
