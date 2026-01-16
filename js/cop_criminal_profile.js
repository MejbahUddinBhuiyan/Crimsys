(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);

  // Convert DB path to correct web path under /Crimsys
  function toWebPath(p) {
    if (!p) return "";
    let s = String(p).replaceAll("\\", "/").trim();

    // If DB stores "/img/criminals/xxx.jpg" just prefix with /Crimsys
    if (s.startsWith("/img/")) return "/Crimsys" + s;
    if (s.startsWith("img/"))  return "/Crimsys/" + s;

    // If DB mistakenly includes /Crimsys already, keep it
    if (s.startsWith("/Crimsys/")) return s;

    // Last resort, return as-is (won't break if it's already correct)
    return s;
  }

  async function loadProfile() {
    const params = new URLSearchParams(location.search);
    const id = params.get("id");
    if (!id) {
      console.error("Missing ?id param");
      return;
    }

    try {
      const resp = await fetch(`/Crimsys/api/cop/get_criminal.php?id=${encodeURIComponent(id)}`, {
        headers: { "Accept": "application/json" }
      });

      // If server sends HTML (error), avoid JSON parse crash
      const txt = await resp.text();
      let data;
      try { data = JSON.parse(txt); } catch {
        console.error("Server returned non-JSON:", txt);
        return;
      }

      if (!data || data.ok !== true || !data.item) {
        console.error("API error:", data?.error || data);
        return;
      }

      const c = data.item;

      // Identity
      $("nameText").textContent = c.fullName || "—";
      $("nidText").textContent  = c.nid || "—";
      $("cityText").textContent = c.city || "—";

      // Overview
      $("fullNameText").textContent = c.fullName || "—";
      $("nidDetail").textContent    = c.nid || "—";
      $("dobText").textContent      = c.dateOfBirth || "—";
      $("addressText").textContent  = [c.street, c.city, c.zip].filter(Boolean).join(", ") || "—";

      // Photo
      const imgPath = toWebPath(c.photo);
      if (imgPath) {
        const img = $("avatar");
        img.src = imgPath;
        img.alt = c.fullName || "Photo";
      }
      // FIRs (kept simple — displays “No FIRs linked.” unless you pass a list)
      const firs = Array.isArray(data.firs) ? data.firs : [];
      const firList = $("firList");
      if (firs.length > 0) {
        firList.innerHTML = "";
        for (const f of firs) {
          const d = document.createElement("div");
          d.textContent = `FIR #${f.firId} — Role: ${f.role || "—"}`;
          firList.appendChild(d);
        }
      } // else leave default message
    } catch (e) {
      console.error(e);
    }
  }

  document.addEventListener("DOMContentLoaded", loadProfile);
})();