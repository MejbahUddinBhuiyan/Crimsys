/* Smart Suspect Identifier — UI glue + API calls
 * Paths assumed:
 *   /Crimsys/api/common/zones.php
 *   /Crimsys/api/suspect/criminal.php
 *   /Crimsys/api/suspect/fir.php
 * Required elements (already in your HTML):
 *   Inputs:  #ssName #ssNid #ssDob #ssDistrict #ssThana #ssPhoto
 *   Toggles: #ssStrict #ssAuto
 *   Buttons: #btnFind #btnReset
 *   Tabs:    [data-tab="all"|"cdb"|"fir"]
 *   Panels:  #panelAll #panelCdb #panelFir
 *   Lists:   #listAll  #listCdb  #listFir
 *   Wrapper: .smart-page (for loading overlay)
 */

(() => {
  // ----- BASES & UTILS -------------------------------------------------------
  const PROJECT_BASE = '/' + (location.pathname.split('/')[1] || ''); // -> "/Crimsys"
  const API_ROOT     = `${PROJECT_BASE}/api`;
  const ZONES_API    = `${API_ROOT}/common/zones.php`;
  const CRIM_API     = `${API_ROOT}/suspect/criminal.php`;
  const FIR_API      = `${API_ROOT}/suspect/fir.php`;

  const $  = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));

  const ui = {
    name:     $('#ssName'),
    nid:      $('#ssNid'),
    dob:      $('#ssDob'),
    district: $('#ssDistrict'),
    thana:    $('#ssThana'),
    photo:    $('#ssPhoto'),
    strict:   $('#ssStrict'),
    auto:     $('#ssAuto'),
    btnFind:  $('#btnFind'),
    btnReset: $('#btnReset'),

    tabBtns:  $$('[data-tab]'),
    panelAll: $('#panelAll'),
    panelCdb: $('#panelCdb'),
    panelFir: $('#panelFir'),

    listAll:  $('#listAll'),
    listCdb:  $('#listCdb'),
    listFir:  $('#listFir'),

    page:     $('.smart-page')
  };

  function setLoading(on) {
    ui.page?.classList.toggle('is-loading', !!on);
  }

  function addOption(sel, value, text = value) {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = text;
    sel.appendChild(opt);
  }

  // Normalize photo: strip any leading "/" then prefix PROJECT_BASE.
  // Also provide a safe fallback to avoid broken images.
  const FALLBACK_PHOTO = `${PROJECT_BASE}/img/placeholder-user.svg`;
  function photoUrl(p) {
    if (!p) return FALLBACK_PHOTO;
    p = String(p).trim();
    if (!p) return FALLBACK_PHOTO;
    if (/^https?:\/\//i.test(p)) return p;      // already absolute
    p = p.replace(/^\/+/, '');                  // remove leading slashes
    return `${PROJECT_BASE}/${p}`;
  }

  function clearLists() {
    ui.listAll.innerHTML = '';
    ui.listCdb.innerHTML = '';
    ui.listFir.innerHTML = '';
  }

  // Simple card renderer (kept minimal)
  function renderCriminal(c) {
    const li = document.createElement('li');
    li.className = 'result-item';

    const img = document.createElement('img');
    img.className = 'avatar';
    img.src = photoUrl(c.Photo);
    img.alt = c.FullName || 'Photo';
    img.onerror = () => { img.src = FALLBACK_PHOTO; };

    const body = document.createElement('div');
    body.className = 'result-body';

    const title = document.createElement('div');
    title.className = 'result-title';
    title.textContent = c.FullName || '(unknown)';

    const sub = document.createElement('div');
    sub.className = 'result-sub';
    const city = c.City ? `, ${c.City}` : '';
    const street = c.Street ? ` , ${c.Street}` : '';
    sub.textContent = (c.City || c.Street) ? `${c.City ? c.City : ''}${street}` : '';

    const age = document.createElement('span');
    age.className = 'pill';
    if (Number.isFinite(+c.Age)) age.textContent = `${c.Age}y`;

    body.appendChild(title);
    body.appendChild(sub);
    li.appendChild(img);
    li.appendChild(body);
    if (age.textContent) li.appendChild(age);
    return li;
  }

  function renderFirPerson(p) {
    const li = document.createElement('li');
    li.className = 'result-item';

    const body = document.createElement('div');
    body.className = 'result-body';

    const title = document.createElement('div');
    title.className = 'result-title';
    title.textContent = p.PersonName || '(unknown)';

    const sub = document.createElement('div');
    sub.className = 'result-sub';
    sub.textContent = p.Zone || p.Location || '';

    body.appendChild(title);
    body.appendChild(sub);
    li.appendChild(body);
    return li;
  }

  // ----- ZONES (districts / thanas) ------------------------------------------
  async function loadDistricts() {
    ui.district.innerHTML = '';
    addOption(ui.district, '', 'All');

    try {
      const res = await fetch(`${ZONES_API}?action=districts`, { cache: 'no-store' });
      const j = await res.json();
      if (j.ok && Array.isArray(j.data)) {
        j.data.forEach(d => addOption(ui.district, d, d));
      }
    } catch (e) {
      console.warn('District load failed:', e);
    }
  }

  async function loadThanas(district) {
    ui.thana.innerHTML = '';
    addOption(ui.thana, '', 'All');

    // If no district selected, just keep "All" and enable (user may search by All)
    if (!district) {
      ui.thana.disabled = false;
      return;
    }

    try {
      const url = `${ZONES_API}?action=thanas&district=${encodeURIComponent(district)}`;
      const res = await fetch(url, { cache: 'no-store' });
      const j = await res.json();
      if (j.ok && Array.isArray(j.data)) {
        j.data.forEach(t => addOption(ui.thana, t, t));
      }
      ui.thana.disabled = false;
    } catch (e) {
      console.warn('Thana load failed:', e);
      ui.thana.disabled = false;
    }
  }

  // ----- SEARCH ---------------------------------------------------------------
  function readFilters() {
    return {
      name:    ui.name.value.trim(),
      nid:     ui.nid.value.trim(),
      dob:     ui.dob.value.trim(),
      district:ui.district.value.trim(),
      thana:   ui.thana.value.trim(),
      strict:  ui.strict.checked ? 1 : 0,
      limit:   10
    };
  }

  async function fetchCriminals(q) {
    const params = new URLSearchParams({
      name: q.name, nid: q.nid, dob: q.dob,
      district: q.district, thana: q.thana,
      photo: 0, strict: q.strict, limit: q.limit
    });
    const res = await fetch(`${CRIM_API}?${params.toString()}`, { cache: 'no-store' });
    return res.json();
  }

  async function fetchFir(q) {
    const params = new URLSearchParams({
      name: q.name, district: q.district, thana: q.thana, limit: q.limit
    });
    const res = await fetch(`${FIR_API}?${params.toString()}`, { cache: 'no-store' });
    return res.json();
  }

  async function runSearch() {
    setLoading(true);
    clearLists();
    try {
      const q = readFilters();
      const [cj, fj] = await Promise.all([fetchCriminals(q), fetchFir(q)]);

      if (cj?.ok && Array.isArray(cj.data)) {
        cj.data.forEach(c => {
          const node = renderCriminal(c);
          ui.listCdb.appendChild(node.cloneNode(true));
          ui.listAll.appendChild(node);
        });
      }

      if (fj?.ok && Array.isArray(fj.data)) {
        fj.data.forEach(p => {
          const node = renderFirPerson(p);
          ui.listFir.appendChild(node.cloneNode(true));
          ui.listAll.appendChild(node);
        });
      }

      // If nothing found, show a tiny hint
      if (!ui.listAll.children.length) {
        const li = document.createElement('li');
        li.className = 'empty-hint';
        li.textContent = 'No suggestions found. Try relaxing filters.';
        ui.listAll.appendChild(li);
      }
    } catch (e) {
      console.error(e);
      const li = document.createElement('li');
      li.className = 'empty-hint';
      li.textContent = 'Something went wrong while searching.';
      ui.listAll.appendChild(li);
    } finally {
      setLoading(false);
    }
  }

  // ----- TABS -----------------------------------------------------------------
  function setupTabs() {
    const panels = {
      all: ui.panelAll, cdb: ui.panelCdb, fir: ui.panelFir
    };
    ui.tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        ui.tabBtns.forEach(b => b.classList.toggle('active', b === btn));
        Object.entries(panels).forEach(([k, panel]) => {
          panel.classList.toggle('d-none', k !== tab);
        });
      });
    });
  }

  // ----- INIT / EVENTS --------------------------------------------------------
  function hookEvents() {
    // Never auto-run on first load
    ui.auto.checked = false;

    ui.btnFind.addEventListener('click', e => {
      e.preventDefault();
      runSearch();
    });

    ui.btnReset.addEventListener('click', e => {
      e.preventDefault();
      ui.name.value = '';
      ui.nid.value = '';
      ui.dob.value = '';
      ui.district.value = '';
      ui.thana.innerHTML = '';
      addOption(ui.thana, '', 'All');
      ui.thana.disabled = false;
      ui.strict.checked = false;
      // Do not auto run on reset
      clearLists();
    });

    // If Auto-apply is ON, react to changes
    const maybeAuto = () => { if (ui.auto.checked) runSearch(); };

    ['input', 'change'].forEach(evt => {
      ui.name.addEventListener(evt, maybeAuto);
      ui.nid.addEventListener(evt, maybeAuto);
      ui.dob.addEventListener(evt, maybeAuto);
      ui.thana.addEventListener(evt, maybeAuto);
      ui.strict.addEventListener(evt, maybeAuto);
    });

    ui.district.addEventListener('change', async () => {
      await loadThanas(ui.district.value);
      maybeAuto();
    });
  }

  async function init() {
    setupTabs();
    hookEvents();
    await loadDistricts();
    await loadThanas('');    // default: All; don’t run a search here
    clearLists();            // keep results empty until user clicks Find (or turns on Auto-apply)
  }

  document.addEventListener('DOMContentLoaded', init);
})();
