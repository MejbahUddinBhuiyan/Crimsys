// /Crimsys/js/admin_login.js
'use strict';

(function () {
  const msgEl  = document.getElementById('msg');
  const emailI = document.getElementById('email');
  const passI  = document.getElementById('password');
  const btn    = document.getElementById('btnLogin');
  const form   = document.getElementById('adminForm');

  if (!msgEl || !emailI || !passI || !btn || !form) {
    // If the script was loaded on a page that doesn’t have the form
    return;
  }

  function showMsg(text){
    msgEl.textContent = text;
    msgEl.classList.remove('d-none');
  }
  function hideMsg(){
    msgEl.classList.add('d-none');
    msgEl.textContent = '';
  }

  async function doLogin(){
    hideMsg();

    const email = (emailI.value || '').trim();
    const password = passI.value || '';

    if (!email || !password){
      showMsg('Please enter both email and password.');
      return;
    }

    try{
      btn.disabled = true;

      const r = await fetch('/Crimsys/api/admin_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ email, password })
      });

      // If API returned non-JSON (e.g. PHP warning), this throws here:
      const data = await r.json();

      if (!data.ok) {
        if (data.error === 'wrong_email')       showMsg('Wrong mail address');
        else if (data.error === 'wrong_password') showMsg('Wrong password');
        else showMsg('Login failed.');
        return;
      }

      location.href = data.redirect || '/Crimsys/html/admin_home.html';
    } catch (err) {
      console.error(err);
      showMsg('Network error. Try again.');
    } finally {
      btn.disabled = false;
    }
  }

  btn.addEventListener('click', doLogin);
  form.addEventListener('submit', (e) => {
    e.preventDefault();   // prevent native submit
    doLogin();
  });
})();
