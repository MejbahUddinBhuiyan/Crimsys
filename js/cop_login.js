// /Crimsys/js/cop_login.js
(function(){
  const btn  = document.getElementById('btnLogin');
  if(!btn) return;

  const msgEl = document.createElement('div');
  msgEl.className = 'alert-center d-none';
  btn.closest('.glass-card').insertBefore(msgEl, btn.closest('form'));

  function showMsg(t){
    msgEl.textContent = t;
    msgEl.classList.remove('d-none');
  }
  function hideMsg(){ msgEl.classList.add('d-none'); msgEl.textContent=''; }

  btn.addEventListener('click', async ()=>{
    hideMsg();
    const id = document.getElementById('copid').value.trim();
    const pw = document.getElementById('password').value;

    if(id.length !== 8 || !/^\d{8}$/.test(id)){ showMsg('Enter 8-digit Cop ID.'); return; }
    if(!pw){ showMsg('Enter password.'); return; }

    try{
      const r = await fetch('/Crimsys/api/cop_login.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'include',
        body: JSON.stringify({ copid:id, password:pw })
      });
      const data = await r.json();

      if(!data.ok){
        if(data.error==='wrong_id') showMsg('Invalid Cop ID');
        else if(data.error==='wrong_password') showMsg('Wrong password');
        else if(r.status === 403 && data.error){ 
          // suspended
          showMsg('Login denied: ' + data.error);
        }
        else showMsg('Login failed.');
        return;
      }

      location.href = data.redirect || '/Crimsys/html/cop_home.html';
    }catch(e){
      showMsg('Network error.');
    }
  });
})();
