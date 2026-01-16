/* admin_common.js
 *  - Injects topbar into a page slot
 *  - Wires back + logout
 *  - Provides small helpers
 */

async function mountAdminTopbar(slotId) {
  const slot = document.getElementById(slotId);
  if (!slot) return;

  try {
    const r = await fetch('/Crimsys/html/partials/admin_topbar.html', { cache: 'no-store' });
    const html = await r.text();
    slot.innerHTML = html;

    // Wire up back button
    const btnBack = document.getElementById('btnBack');
    btnBack?.addEventListener('click', (e) => {
      e.preventDefault();
      // Safe back: only if referrer is same-origin; otherwise go to landing
      try {
        const ref = document.referrer;
        if (ref && new URL(ref).origin === location.origin) {
          history.back();
          return;
        }
      } catch (_) { /* ignore */ }
      location.href = '/Crimsys/'; // landing page
    });

    // Wire up logout
    const btnLogout = document.getElementById('btnLogout');
    btnLogout?.addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        await fetch('/Crimsys/api/admin/logout.php', { credentials: 'include' });
      } catch (_) {}
      location.href = '/Crimsys/html/admin_login.html';
    });
  } catch (err) {
    console.error('Failed to mount admin topbar:', err);
  }
}

// export for inline usage
window.mountAdminTopbar = mountAdminTopbar;
