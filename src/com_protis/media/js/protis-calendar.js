/**
 * PROTIS IBF V.3 – calendario mensile di rendicontazione.
 * Desktop: griglia mensile. Smartphone: elenco giorni (agenda). Tutto via API JSON.
 */
(() => {
  'use strict';

  const root = document.getElementById('pt-calendar');
  if (!root || !window.Protis) return;

  const P = window.Protis;
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const bind = (name, ctx = document) => ctx.querySelector(`[data-bind="${name}"]`);
  const DOW = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
  const DOW_LONG = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
  const MONTHS = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
  // categorie che sono lavoro (rendicontabili)
  const WORK = ['smartworking', 'telelavoro', 'missione'];
  const CAT = { smartworking: 'Lavoro Agile', telelavoro: 'Telelavoro', missione: 'Missione', malattia: 'Malattia', ferie: 'Ferie', permessi: 'Permessi', altro: 'Altro' };

  const state = {
    person: parseInt(root.dataset.person, 10),
    year: parseInt(root.dataset.year, 10),
    month: parseInt(root.dataset.month, 10),
    data: null,
    day: null,
    busy: false,
  };

  const params = () => ({ person: state.person, year: state.year, month: state.month });

  function setBusy(on) {
    state.busy = on;
    root.classList.toggle('is-busy', on);
    root.querySelectorAll('[data-act]').forEach((b) => { if (b.tagName === 'BUTTON') b.disabled = on; });
  }

  async function load() {
    setBusy(true);
    try {
      const res = await P.api('api.month', params());
      state.data = res.data;
      render();
      if (res.autosync && res.autosync.error) P.toast('ePAS: ' + res.autosync.error, 'warn');
    } catch (e) {
      bind('grid').innerHTML = `<div class="pt-alert pt-alert-danger">${P.esc(e.message)}</div>`;
    } finally {
      setBusy(false);
    }
    root.querySelectorAll('[data-jump]').forEach((sel) => {
      const v = String(sel.dataset.jump === 'm' ? state.month : state.year);
      if (![...sel.options].some((o) => o.value === v)) sel.add(new Option(v, v));
      sel.value = v;
    });
    const url = new URL(window.location.href);
    url.searchParams.set('year', state.year);
    url.searchParams.set('month', state.month);
    if (state.person) url.searchParams.set('person', state.person);
    window.history.replaceState(null, '', url);
  }

  async function post(task, fd, extra = {}) {
    setBusy(true);
    try {
      const res = await P.api(task, { ...params(), ...extra }, 'POST', fd);
      state.data = res.data;
      render();
      P.toast(res.message || 'Operazione completata');
      return true;
    } catch (e) {
      P.toast(e.message, 'error', 7000);
      return false;
    } finally {
      setBusy(false);
    }
  }

  /* ------------------------------------------------------------------ */
  /* Rendering                                                          */
  /* ------------------------------------------------------------------ */

  /**
   * Il giorno si può aprire?
   *  - con ePAS: solo se ci sono ore rendicontabili (presenza, lavoro agile, telelavoro, missione)
   *  - con inserimento manuale: ogni giorno fino a oggi, anche sabato, domenica e festivi
   *    (es. missione nel fine settimana) e i giorni di assenza, per poterli correggere
   */
  function dayOpen(d) {
    if (d.capacity > 0 || d.reported > 0) return true;
    if (!state.data.canEditManual) return false;
    return d.date <= todayIso();
  }

  function todayIso() {
    const n = new Date();
    return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`;
  }

  function dayStatus(d) {
    if (d.reported > d.capacity) return 'over';
    if (d.capacity > 0 && d.reported >= d.capacity) return 'ok';
    if (d.reported > 0) return 'partial';
    const absent = Object.entries(d.cat).some(([k, v]) => v > 0 && !WORK.includes(k)) || d.codes.length;
    if (absent && d.capacity === 0) return 'abs';
    return d.capacity > 0 ? 'todo' : 'none';
  }

  function render() {
    const D = state.data;
    bind('title').textContent = `${MONTHS[D.month - 1]} ${D.year}`;
    const ym = D.year * 12 + D.month;
    const now = new Date();
    const nowYm = now.getFullYear() * 12 + now.getMonth() + 1;
    bind('kicker').textContent = ym === nowYm ? 'Mese in corso' : (ym > nowYm ? 'Mese futuro' : (ym === nowYm - 1 ? 'Mese da rendicontare' : 'Mese passato'));
    const note = bind('note');
    note.hidden = ym < nowYm;
    note.textContent = ym === nowYm
      ? 'Il mese in corso non è ancora concluso: si rendiconta dal primo giorno del mese successivo. Usa ‹ per tornare al mese precedente.'
      : 'Mese futuro: non ci sono ancora presenze da rendicontare.';

    // Banner informativi
    const info = [];
    info.push(D.person.epas
      ? `<span class="pt-chip">Matricola ${P.esc(D.person.matricola)} · presenze da ePAS</span>`
      : (D.person.external
        ? `<span class="pt-chip pt-chip-warn">Altro istituto${D.person.istituto ? ' (' + P.esc(D.person.istituto) + ')' : ''}: presenze inserite manualmente</span>`
        : '<span class="pt-chip pt-chip-warn">Senza matricola: presenze inserite manualmente</span>'));
    if (!D.projects.length) info.push('<span class="pt-chip pt-chip-warn">Nessun progetto associato</span>');
    bind('info').innerHTML = info.join(' ');

    const syncBtn = root.querySelector('[data-act="sync"]');
    syncBtn.hidden = !D.person.epas;

    renderGrid(D);
    renderSummary(D);
    renderExport(D);
  }

  function renderGrid(D) {
    const grid = bind('grid');
    const first = D.days[0].dow; // 1 = lunedì
    let html = DOW.map((d, i) => `<div class="pt-dow${i >= 5 ? ' is-weekend' : ''}">${d}</div>`).join('');
    for (let i = 1; i < first; i++) html += '<div class="pt-day is-blank" aria-hidden="true"></div>';

    const today = todayIso();

    D.days.forEach((d) => {
      const st = dayStatus(d);
      const open = dayOpen(d);
      const cls = ['pt-day', `st-${st}`];
      if (!open) cls.push('is-off');
      if (d.weekend) cls.push('is-weekend');
      if (d.holiday) cls.push('is-holiday');
      if (d.date === today) cls.push('is-today');
      if (d.blocked) cls.push('is-blocked');

      const cats = Object.entries(d.cat).filter(([, v]) => v > 0).map(([k, v]) => `<span class="pt-tag pt-tag-${k}">${CAT[k]} ${P.fmt(v)}</span>`);
      const codes = d.codes.filter((c) => !d.cat[c.category]).map((c) => `<span class="pt-tag" title="${P.esc(c.desc || c.label)}">${P.esc(c.code)}</span>`);
      const hasData = d.capacity > 0 || d.reported > 0 || cats.length || d.codes.length;

      const tag = open ? 'button' : 'div';
      const repCls = d.reported > d.capacity ? 'is-over' : (d.capacity > 0 && d.reported >= d.capacity ? 'is-ok' : (d.reported > 0 ? 'is-partial' : ''));
      html += `<${tag} ${open ? 'type="button"' : 'aria-disabled="true"'} class="${cls.join(' ')}" data-date="${d.date}" aria-label="${DOW_LONG[d.dow - 1]} ${d.day}${open ? '' : ' – non rendicontabile'}">
        <span class="pt-day-head"><span class="pt-day-num">${d.day}</span><span class="pt-day-dow">${DOW_LONG[d.dow - 1]}</span>${d.holiday ? `<span class="pt-day-hol" title="${P.esc(d.holiday)}">${P.esc(d.holiday)}</span>` : ''}</span>
        ${hasData ? `<span class="pt-day-body">
          ${d.worked > 0 ? `<span class="pt-day-line"><small>Presenza</small> ${P.fmt(d.worked)}</span>` : ''}
          ${cats.join('')}${codes.join('')}
          ${d.capacity > 0 || d.reported > 0 ? `<span class="pt-day-rep ${repCls}"><b>${P.fmt(d.reported)}</b> / ${P.fmt(d.capacity)}</span>` : ''}
        </span>` : ''}
      </${tag}>`;
    });

    grid.innerHTML = html;
  }

  function renderSummary(D) {
    const T = D.totals;
    const pct = T.capacity ? Math.min(100, Math.round((T.reported * 100) / T.capacity)) : 0;
    const cats = T.categories;
    bind('summary').innerHTML = `
      <dt>Ore rendicontabili</dt><dd>${P.fmt(T.capacity)}</dd>
      <dt>Ore rendicontate su progetti</dt><dd><strong>${P.fmt(T.reported)}</strong></dd>
      <dt>Attività Ordinaria residua</dt><dd>${P.fmt(T.ordinary)}</dd>
      <dd class="pt-kv-full"><div class="pt-progress"><span style="width:${pct}%"></span></div></dd>
      <dt>Presenza</dt><dd>${P.fmt(cats.tempo_lavorato)}</dd>
      ${Object.keys(CAT).filter((k) => cats[k] > 0).map((k) => `<dt>${CAT[k]}</dt><dd>${P.fmt(cats[k])}</dd>`).join('')}`;

    bind('projects').innerHTML = T.projects.length
      ? T.projects.map((p) => `<details class="pt-proj-sum" open><summary><span>${P.esc(p.acronimo)}</span><strong>${P.fmt(p.total)}</strong></summary>
          <ul>${p.wps.map((w) => `<li><span>${P.esc(w.label)}</span><span>${P.fmt(w.total)}</span></li>`).join('')}</ul></details>`).join('')
        + `<div class="pt-proj-total"><span>Totale su tutti i progetti</span><strong>${P.fmt(T.projects.reduce((s, p) => s + p.total, 0))}</strong></div>`
      : '<p class="pt-muted">Nessuna ora rendicontata nel mese.</p>';
  }

  function renderExport(D) {
    const sel = bind('exportProjects');
    const prev = sel.value;
    sel.innerHTML = '<option value="" disabled selected>Scegli progetto…</option>'
      + D.projects.map((p) => `<option value="${p.id}">${P.esc(p.acronimo)}${p.cup ? ' – ' + P.esc(p.cup) : ''}</option>`).join('');
    if (prev && D.projects.some((p) => String(p.id) === prev)) sel.value = prev;
    const ym = `${D.year}-${String(D.month).padStart(2, '0')}`;
    P.setMonth(bind('exportFrom'), D.year, D.month);
    P.setMonth(bind('exportTo'), D.year, D.month);
    bind('export').querySelector('[name="person"]').value = D.person.id;
    bind('export').querySelector('button[type="submit"]').disabled = !D.projects.length;
  }

  /* ------------------------------------------------------------------ */
  /* Dialog giorno                                                      */
  /* ------------------------------------------------------------------ */

  function openDay(date) {
    const D = state.data;
    const d = D.days.find((x) => x.date === date);
    if (!d) return;
    state.day = d;
    const dlg = $('#pt-day');

    bind('dayTitle', dlg).textContent = `${DOW_LONG[d.dow - 1]} ${d.day} ${MONTHS[D.month - 1]} ${D.year}`;
    const sub = [];
    if (d.holiday) sub.push(d.holiday);
    d.codes.forEach((c) => sub.push(`${c.code}: ${c.desc || c.label}`));
    bind('daySub', dlg).textContent = sub.join(' · ');

    // presenze manuali
    const manual = bind('manual', dlg);
    manual.hidden = !D.canEditManual;
    if (D.canEditManual) {
      manual.querySelector('[name="manual[worked]"]').value = d.worked ? P.fmt(d.worked) : '';
      Object.keys(CAT).forEach((k) => { manual.querySelector(`[name="manual[${k}]"]`).value = d.cat[k] ? P.fmt(d.cat[k]) : ''; });
    }

    // WP
    const box = bind('dayWps', dlg);
    if (!D.projects.length) {
      box.innerHTML = '<div class="pt-empty">Nessun progetto associato: vai su <a href="' + (window.location.pathname) + '?option=com_protis&view=assignments">Associazioni</a>.</div>';
    } else {
      box.innerHTML = D.projects.map((p) => `
        <fieldset class="pt-wp-group">
          <legend>${P.esc(p.acronimo)} <small>${P.esc(p.cup)}</small></legend>
          ${p.wps.map((w) => {
            const v = d.hours[w.id] || 0;
            return `<div class="pt-wp-row">
              <label for="wp-${w.id}">${P.esc(w.label)}</label>
              <div class="pt-wp-input">
                <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" id="wp-${w.id}" name="hours[${w.id}]" value="${v ? P.fmt(v) : ''}" data-wp="${w.id}">
                <button type="button" class="pt-chip-btn" data-fill-rest="${w.id}" title="Assegna le ore residue">Residuo</button>
              </div>
            </div>`;
          }).join('')}
        </fieldset>`).join('');
    }

    // ore su WP di progetti non più associati (sola lettura, conteggiate nel totale)
    const known = new Set(D.projects.flatMap((p) => p.wps.map((w) => w.id)));
    const orphan = Object.entries(d.hours).filter(([id]) => !known.has(parseInt(id, 10)));
    if (orphan.length) {
      box.insertAdjacentHTML('beforeend', `<div class="pt-alert pt-alert-info">Ore su progetti non più associati: ${orphan.map(([id, m]) => `${P.esc((D.wpIndex[id] || {}).acronimo || 'WP ' + id)} ${P.fmt(m)}`).join(', ')}</div>`);
    }

    updateDayTotals();
    dlg.showModal();
    const firstInput = dlg.querySelector('input:not([type=hidden])');
    if (firstInput && window.matchMedia('(min-width: 700px)').matches) firstInput.focus();
  }

  function dayCapacityFromForm() {
    const D = state.data;
    const dlg = $('#pt-day');
    if (!D.canEditManual) return state.day.capacity;
    const v = (k) => P.parse(dlg.querySelector(`[name="manual[${k}]"]`).value);
    return v('worked') + WORK.reduce((s, k) => s + v(k), 0);
  }

  function updateDayTotals() {
    const dlg = $('#pt-day');
    const D = state.data;
    const cap = dayCapacityFromForm();
    const known = new Set(D.projects.flatMap((p) => p.wps.map((w) => w.id)));
    const orphan = Object.entries(state.day.hours).filter(([id]) => !known.has(parseInt(id, 10))).reduce((s, [, m]) => s + m, 0);
    let tot = orphan;
    dlg.querySelectorAll('[data-wp]').forEach((i) => { tot += P.parse(i.value); });
    const over = tot > cap;
    bind('dayCap', dlg).innerHTML = `Ore rendicontabili del giorno: <strong>${P.fmt(cap)}</strong>${cap === 0 ? ' — nessuna presenza registrata' : ''}`;
    const t = bind('dayTotal', dlg);
    t.textContent = `Totale ${P.fmt(tot)} / ${P.fmt(cap)}`;
    t.classList.toggle('is-over', over);
    dlg.querySelector('button[type="submit"]').disabled = over;
    return { tot, cap };
  }

  /* ------------------------------------------------------------------ */
  /* Strumenti mese                                                     */
  /* ------------------------------------------------------------------ */

  function wpOptions(D) {
    return D.projects.map((p) => `<optgroup label="${P.esc(p.acronimo)} – ${P.esc(p.cup)}">${p.wps.map((w) => `<option value="${w.id}">${P.esc(w.label)}</option>`).join('')}</optgroup>`).join('');
  }

  function openFill() {
    const D = state.data;
    if (!D.projects.length) { P.toast('Nessun progetto associato.', 'warn'); return; }
    bind('wpSelect', $('#pt-fill')).innerHTML = wpOptions(D);
    P.open('pt-fill');
  }

  function openDistribute() {
    const D = state.data;
    if (!D.projects.length) { P.toast('Nessun progetto associato.', 'warn'); return; }
    const dlg = $('#pt-distribute');
    const cur = {};
    D.totals.projects.forEach((p) => p.wps.forEach((w) => { cur[w.id] = w.total; }));
    bind('distCap', dlg).innerHTML = `Ore rendicontabili nel mese: <strong>${P.fmt(D.totals.capacity)}</strong> · già su progetti: ${P.fmt(D.totals.reported)}`;
    bind('distWps', dlg).innerHTML = D.projects.map((p) => `
      <fieldset class="pt-wp-group"><legend>${P.esc(p.acronimo)} <small>${P.esc(p.cup)}</small></legend>
        ${p.wps.map((w) => `<div class="pt-wp-row"><label for="dist-${w.id}">${P.esc(w.label)} <small class="pt-muted">attuali ${P.fmt(cur[w.id] || 0)}</small></label>
          <input type="text" inputmode="decimal" autocomplete="off" class="pt-dur" id="dist-${w.id}" name="targets[${w.id}]" placeholder="hh:mm" data-dist></div>`).join('')}
      </fieldset>`).join('');
    updateDistTotal();
    dlg.showModal();
  }

  function updateDistTotal() {
    const dlg = $('#pt-distribute');
    let tot = 0;
    dlg.querySelectorAll('[data-dist]').forEach((i) => { tot += P.parse(i.value); });
    bind('distTotal', dlg).textContent = `Totale richiesto ${P.fmt(tot)}`;
  }

  function openClear() {
    const D = state.data;
    // ore del mese per WP (per mostrare cosa c'è da svuotare)
    const tot = {};
    D.totals.projects.forEach((p) => { tot['p' + p.id] = p.total; p.wps.forEach((w) => { tot['w' + w.id] = w.total; }); });
    const h = (k) => (tot[k] ? ` – ${P.fmt(tot[k])}` : '');
    bind('clearProjects', $('#pt-clear')).innerHTML = `<option value="">Tutti i progetti${D.totals.reported ? ' – ' + P.fmt(D.totals.reported) : ''}</option>`
      + D.projects.map((p) => `<optgroup label="${P.esc(p.acronimo)}">
          <option value="p:${p.id}">${P.esc(p.acronimo)} – tutti i WP${h('p' + p.id)}</option>
          ${p.wps.map((w) => `<option value="w:${w.id}">${P.esc(w.label)}${h('w' + w.id)}</option>`).join('')}
        </optgroup>`).join('');
    P.open('pt-clear');
  }

  /* ------------------------------------------------------------------ */
  /* Eventi                                                             */
  /* ------------------------------------------------------------------ */

  function go(deltaMonths) {
    let m = state.month + deltaMonths;
    let y = state.year;
    while (m < 1) { m += 12; y--; }
    while (m > 12) { m -= 12; y++; }
    state.month = m;
    state.year = y;
    load();
  }

  // menu mese/anno: salto diretto (es. Aprile 2025)
  root.addEventListener('change', (e) => {
    const sel = e.target.closest('[data-jump]');
    if (!sel) return;
    if (state.busy) { sel.value = String(sel.dataset.jump === 'm' ? state.month : state.year); return; }
    if (sel.dataset.jump === 'm') state.month = parseInt(sel.value, 10); else state.year = parseInt(sel.value, 10);
    load();
  });

  root.addEventListener('click', (e) => {
    const cell = e.target.closest('button.pt-day[data-date]');
    if (cell) { openDay(cell.dataset.date); return; }

    const btn = e.target.closest('[data-act]');
    if (!btn || state.busy) return;
    const act = btn.dataset.act;

    if (act === 'prev') go(-1);
    else if (act === 'next') go(1);
    else if (act === 'today') { const n = new Date(); state.year = n.getFullYear(); state.month = n.getMonth() + 1; load(); }
    else if (act === 'sync') post('api.sync', new FormData());
    else if (act === 'open-fill') { btn.closest('.pt-dropdown').classList.remove('is-open'); openFill(); }
    else if (act === 'open-distribute') { btn.closest('.pt-dropdown').classList.remove('is-open'); openDistribute(); }
    else if (act === 'open-clear') { btn.closest('.pt-dropdown').classList.remove('is-open'); openClear(); }
    else if (act === 'open-export') {
      btn.closest('.pt-dropdown').classList.remove('is-open');
      document.getElementById('pt-export-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
      bind('exportProjects').focus();
    }
  });

  root.addEventListener('change', (e) => {
    if (e.target.matches('[data-act="person"]')) {
      state.person = parseInt(e.target.value, 10);
      load();
    }
  });

  // Dialog giorno
  const dayDlg = $('#pt-day');
  dayDlg.addEventListener('input', updateDayTotals);
  dayDlg.addEventListener('click', (e) => {
    const rest = e.target.closest('[data-fill-rest]');
    if (rest) {
      const input = dayDlg.querySelector(`[data-wp="${rest.dataset.fillRest}"]`);
      input.value = '';
      const { tot, cap } = updateDayTotals();
      const free = Math.max(0, cap - tot);
      input.value = free ? P.fmt(free) : '';
      updateDayTotals();
      return;
    }
    const q = e.target.closest('[data-quick]');
    if (q) {
      const f = bind('manual', dayDlg);
      f.querySelectorAll('input').forEach((i) => { i.value = ''; });
      if (q.dataset.quick !== 'clear') {
        const [k, v] = q.dataset.quick.split('=');
        f.querySelector(`[name="manual[${k}]"]`).value = v;
      }
      updateDayTotals();
    }
  });
  dayDlg.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('date', state.day.date);
    // i campi vuoti valgono 0 (cancellazione)
    dayDlg.querySelectorAll('[data-wp]').forEach((i) => { if (!i.value) fd.set(i.name, '00:00'); });
    if (!state.data.canEditManual) [...fd.keys()].filter((k) => k.startsWith('manual[')).forEach((k) => fd.delete(k));
    if (await post('api.saveDay', fd)) dayDlg.close();
  });

  $('#pt-fill form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (await post('api.fill', fd)) $('#pt-fill').close();
  });

  const distDlg = $('#pt-distribute');
  distDlg.addEventListener('input', updateDistTotal);
  distDlg.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (await post('api.distribute', new FormData(e.target))) distDlg.close();
  });

  $('#pt-clear form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!window.confirm('Confermi la cancellazione delle ore del mese?')) return;
    if (await post('api.clear', new FormData(e.target))) $('#pt-clear').close();
  });

  // durate: "7" → 07:00, "7,5" → 07:30, "730" → 07:30, "7:5" → 07:05
  document.addEventListener('focusout', (e) => {
    const i = e.target.closest('.pt-dur');
    if (!i || !i.value.trim()) return;
    let v = i.value.trim();
    if (/^\d{3,4}$/.test(v)) v = `${v.slice(0, -2)}:${v.slice(-2)}`;
    i.value = P.fmt(P.parse(v));
    i.dispatchEvent(new Event('input', { bubbles: true }));
  });

  // tastiera: frecce sinistra/destra per cambiare mese (fuori dai campi)
  document.addEventListener('keydown', (e) => {
    if (document.querySelector('dialog[open]') || /INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) return;
    if (e.key === 'ArrowLeft') go(-1);
    if (e.key === 'ArrowRight') go(1);
  });

  load().then(() => {
    if (root.dataset.panel === 'export') document.getElementById('pt-export-card').scrollIntoView({ block: 'start' });
  });
})();
