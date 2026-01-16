// /Crimsys/js/admin_cop_create.js
(function () {
  const form   = document.getElementById('formCreate');
  const msgEl  = document.getElementById('msg');
  const btn    = document.getElementById('btnCreate') || form.querySelector('button[type=submit]');

  function showMessage(text, isError = false) {
    msgEl.textContent = '';
    msgEl.className = 'alert-banner' + (isError ? ' alert-danger' : ' alert-success');
    msgEl.innerHTML = text;
    msgEl.classList.remove('d-none');
    setTimeout(() => msgEl.classList.add('d-none'), 9990000);
  }

  async function submitHandler(e) {
    e.preventDefault();

    const payload = {
      name:      (document.getElementById('name')?.value || '').trim(),
      email:     (document.getElementById('email')?.value || '').trim(),
      badge:     (document.getElementById('badge')?.value || '').trim(),
      contact:   (document.getElementById('contact')?.value || '').trim(),
      rank:      (document.getElementById('rank')?.value || '').trim(),
      station:   (document.getElementById('station')?.value || '').trim(),
      present:   (document.getElementById('present')?.value || '').trim(),
      permanent: (document.getElementById('permanent')?.value || '').trim()
    };

    try {
      btn.disabled = true;

      const res  = await fetch('/Crimsys/api/admin/cop_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if (!data.ok) {
        const m =
          data.error === 'missing_fields'
            ? 'Please fill name, email, badge, rank and station.'
            : (data.error || 'Network or server error.');
        showMessage(m, true);
        return;
      }

      // Read the EXACT field names returned by the API:
      const id   = data.cop_id;      // <= returned by PHP
      const pass = data.password;    // <= returned by PHP

      showMessage(
        `Cop created successfully.<br><strong>Cop ID:</strong> ${id} &nbsp; <strong>Temporary Password:</strong> ${pass}`,
        false
      );

      form.reset();
    } catch (err) {
      showMessage('Network or server error.', true);
    } finally {
      btn.disabled = false;
    }
  }

  form.addEventListener('submit', submitHandler);
})();
