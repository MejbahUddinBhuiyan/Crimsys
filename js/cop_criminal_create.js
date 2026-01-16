// /Crimsys/js/cop_criminal_create.js
(function(){
  console.log("[Add Criminal] JS loaded");

  const form = document.getElementById("criminalForm");
  const msg  = document.getElementById("msg");
  const photo= document.getElementById("photoFile");
  const prev = document.getElementById("prevImg");

  function showMsg(t, ok=false){
    msg.textContent=t;
    msg.className="alert-banner "+(ok?"alert-success-banner":"alert-danger-banner");
    msg.classList.remove("d-none");
  }
  function hideMsg(){ msg.classList.add("d-none"); msg.textContent=""; }

  if(photo && prev){
    photo.addEventListener("change",()=>{
      const f=photo.files && photo.files[0];
      if(!f) return;
      prev.src=URL.createObjectURL(f);
    });
  }

  if(form){
    form.addEventListener("submit", async (e)=>{
      e.preventDefault();
      hideMsg();

      // quick client validation
      const fn = document.getElementById('fullName').value.trim();
      const nd = document.getElementById('nid').value.trim();
      if(!fn || !nd){ showMsg("Please enter Full Name and NID."); return; }

      const fd=new FormData(form);
      try{
        const r=await fetch("/Crimsys/api/criminal_create.php",{
          method:"POST",credentials:"include",body:fd
        });
        const d=await r.json();
        if(!d.ok){
          if(d.error==='nid_exists') showMsg("This NID already exists.");
          else showMsg(d.error||"Create failed");
          return;
        }
        showMsg("Criminal profile added successfully!",true);
        form.reset();
        prev.src="/Crimsys/img/cops/_placeholder.png";
      }catch(err){
        showMsg(err.message||"Network error");
      }
    });
  }
})();
