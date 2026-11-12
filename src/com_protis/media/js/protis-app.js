/**
 * PROTIS IBF V.3 – funzioni comuni dell'interfaccia (dialog, menu, toast, PWA).
 * Nessuna dipendenza esterna.
 */
(() => {
  'use strict';

  const opts = (window.Joomla && Joomla.getOptions && Joomla.getOptions('com_protis')) || {};

  const Protis = {
    opts,

    /** Chiamata a un endpoint JSON del componente (task=api.*) */
    async api(task, params = {}, method = 'GET', body = null) {
      const url = new URL(opts.base || 'index.php?option=com_protis', window.location.origin);
      url.searchParams.set('task', task);
      url.searchParams.set('format', 'json');
      Object.entries(params).forEach(([k, v]) => v !== undefined && v !== null && url.searchParams.set(k, v));

      const init = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
      if (method === 'POST') {
        const fd = body instanceof FormData ? body : new FormData();
        if (body && !(body instanceof FormData)) Object.entries(body).forEach(([k, v]) => fd.append(k, v));
        fd.append(opts.token, '1');
        init.body = fd;
      }

      let res;
      try {
        res = await fetch(url, init);
      } catch (e) {
        throw new Error('Connessione non disponibile. Riprova.');
      }
      let json = null;
      try { json = await res.json(); } catch (e) { /* risposta non JSON */ }
      if (!res.ok || !json || json.success === false) {
        if (res.status === 401 || res.status === 403) {
          throw new Error((json && json.message) || 'Sessione scaduta: ricarica la pagina ed esegui di nuovo l\'accesso.');
        }
        throw new Error((json && json.message) || `Errore ${res.status}`);
      }
      return json;
    },

    toast(message, type = 'ok', ms = 4200) {
      const el = document.querySelector('.pt-toast');
      if (!el) { window.alert(message); return; }
      el.textContent = message;
      el.dataset.type = type;
      el.hidden = false;
      clearTimeout(el._t);
      el._t = setTimeout(() => { el.hidden = true; }, ms);
    },

    open(id) {
      const d = document.getElementById(id);
      if (d && typeof d.showModal === 'function') d.showModal();
      return d;
    },

    close(el) {
      const d = el.closest('dialog');
      if (d) d.close();
    },

    /** minuti → "HH:MM" */
    fmt(min, emptyIfZero = false) {
      min = Math.round(min || 0);
      if (!min && emptyIfZero) return '';
      const s = min < 0 ? '-' : '';
      min = Math.abs(min);
      return `${s}${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
    },

    /** "HH:MM" | "7,5" → minuti */
    parse(v) {
      v = String(v || '').trim();
      if (!v) return 0;
      const m = v.match(/^(\d{1,4}):(\d{1,2})$/);
      if (m) return parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
      const n = parseFloat(v.replace(',', '.'));
      return Number.isFinite(n) ? Math.round(n * 60) : 0;
    },

    /** Imposta un selettore mese/anno (fieldset data-pt-month) */
    setMonth(fs, y, m) {
      if (!fs) return;
      fs.querySelector('[data-m]').value = String(m);
      const ys = fs.querySelector('[data-y]');
      if (![...ys.options].some((o) => o.value === String(y))) ys.insertAdjacentHTML('beforeend', `<option value="${y}">${y}</option>`);
      ys.value = String(y);
      fs.querySelector('input[type=hidden]').value = `${y}-${String(m).padStart(2, '0')}`;
    },

    esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },
  };

  window.Protis = Protis;

  // Dialog, menu a tendina, conferme
  document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-pt-open]');
    if (openBtn) { Protis.open(openBtn.dataset.ptOpen); return; }

    const closeBtn = e.target.closest('[data-pt-close]');
    if (closeBtn) { Protis.close(closeBtn); return; }

    const toggle = e.target.closest('[data-pt-toggle]');
    document.querySelectorAll('.pt-dropdown.is-open').forEach((d) => { if (!toggle || d !== toggle.parentElement) d.classList.remove('is-open'); });
    if (toggle) { toggle.parentElement.classList.toggle('is-open'); return; }

    // click fuori dal contenuto del dialog = chiudi
    if (e.target.tagName === 'DIALOG' && e.target.classList.contains('pt-sheet')) e.target.close();
  });

  document.addEventListener('submit', (e) => {
    const f = e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.dataset.confirm)) e.preventDefault();
  });

  // Selettori mese/anno → campo nascosto YYYY-MM
  document.addEventListener('change', (e) => {
    const fs = e.target.closest('[data-pt-month]');
    if (!fs) return;
    Protis.setMonth(fs, fs.querySelector('[data-y]').value, fs.querySelector('[data-m]').value);
  });

  // Selettore persone con monte ore (associazioni)
  const pickerSync = (box) => {
    const rows = box.querySelectorAll('[data-picker-row]');
    let n = 0;
    rows.forEach((r) => {
      const cb = r.querySelector('input[type=checkbox]');
      r.querySelectorAll('.pt-picker-hours').forEach((h) => { h.disabled = !cb.checked; });
      r.classList.toggle('is-checked', cb.checked);
      if (cb.checked) n++;
    });
    const c = box.querySelector('[data-picker-count]');
    if (c) c.textContent = n === 1 ? '1 selezionata' : `${n} selezionate`;
  };
  document.querySelectorAll('[data-pt-picker]').forEach(pickerSync);
  document.addEventListener('change', (e) => {
    const box = e.target.closest('[data-pt-picker]');
    if (box && e.target.type === 'checkbox') {
      pickerSync(box);
      if (e.target.checked) e.target.closest('[data-picker-row]').querySelector('.pt-picker-hours').focus();
    }
    // cambio progetto: evidenzia chi è già associato e propone il monte ore attuale
    if (e.target.matches('[data-assign-project]') && window.PROTIS_ASSIGNED) {
      const map = window.PROTIS_ASSIGNED[e.target.value] || {};
      e.target.form.querySelectorAll('[data-picker-row]').forEach((r) => {
        const id = r.dataset.person;
        const has = Object.prototype.hasOwnProperty.call(map, id);
        const chip = r.querySelector('[data-picker-assigned]');
        chip.hidden = !has;
        chip.textContent = has && map[id] !== null ? `già associata · ${Protis.fmt(map[id])}` : 'già associata';
        const cb = r.querySelector('input[type=checkbox]');
        cb.disabled = has;
        if (has) cb.checked = false;
        r.classList.toggle('is-assigned', has);
        r.querySelector('.pt-picker-hours').value = has && map[id] !== null ? String(Math.round(map[id] / 6) / 10).replace(/\.0$/, '').replace('.', ',') : '';
      });
      pickerSync(e.target.form.querySelector('[data-pt-picker]'));
    }
  });
  // Interruttori Attivo / Disattivato
  document.addEventListener('change', (e) => {
    const sw = e.target.closest('[data-switch]');
    if (!sw || e.target.type !== 'checkbox') return;
    const l = sw.querySelector('.pt-switch-label[data-on]');
    sw.classList.toggle('is-on', e.target.checked);
    if (l && !sw.classList.contains('pt-switch-view')) l.textContent = e.target.checked ? l.dataset.on : l.dataset.off;
    // salvataggio immediato (scheda progetto / scheda persona)
    if (sw.dataset.autosave) {
      const on = e.target.checked;
      sw.classList.add('is-busy');
      Protis.api(sw.dataset.autosave, {}, 'POST', { id: sw.dataset.id, active: on ? 1 : 0 })
        .then((r) => {
          Protis.toast(r.message || 'Salvato');
          const why = sw.closest('.pt-epas-box')?.querySelector('[data-epas-reason]');
          if (why && 'reason' in r) why.textContent = r.reason ? r.reason.charAt(0).toUpperCase() + r.reason.slice(1) + '.' : 'Le presenze vengono lette da ePAS (sincronizzazione automatica); il calendario non è modificabile a mano.';
        })
        .catch((err) => {
          Protis.toast(err.message, 'error', 7000);
          e.target.checked = !on;
          sw.classList.toggle('is-on', !on);
          if (l) l.textContent = !on ? l.dataset.on : l.dataset.off;
        })
        .finally(() => sw.classList.remove('is-busy'));
    }
    // interruttore di filtro (es. Progetti attivi / archiviati): invia subito il modulo
    if (sw.dataset.submit !== undefined) {
      const f = sw.querySelector('input')?.form || sw.closest('form');
      f && (f.requestSubmit ? f.requestSubmit() : f.submit());
    }
  });

  // Righe di tabella cliccabili (es. elenco progetti)
  document.addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-href]');
    if (!tr || e.target.closest('a, button, input, select, label, form')) return;
    if (e.ctrlKey || e.metaKey) { window.open(tr.dataset.href, '_blank'); return; }
    window.location.href = tr.dataset.href;
  });
  document.addEventListener('keydown', (e) => {
    const tr = e.target.closest?.('tr[data-href]');
    if (tr && e.key === 'Enter' && e.target === tr) window.location.href = tr.dataset.href;
  });

  // Sede di afferenza: "Altra sede (scrivi)…" mostra il campo libero
  document.addEventListener('change', (e) => {
    const sel = e.target.closest('[data-sitepicker-select]');
    if (!sel) return;
    const other = sel.closest('[data-sitepicker]').querySelector('[data-sitepicker-other]');
    other.hidden = sel.value !== 'altro';
    const inp = other.querySelector('input');
    inp.required = sel.value === 'altro';
    if (!other.hidden) inp.focus();
  });

  // Monte ore modificabile in linea: il pulsante Salva compare solo se il valore cambia
  document.addEventListener('input', (e) => {
    const i = e.target.closest('.pt-hours-edit input[name=monteore], .pt-hours-edit input[name=monte_annuo]');
    if (i) i.form.querySelector('button').hidden = i.value.trim() === i.dataset.orig;
  });

  // Azzera: toglie le spunte, svuota la ricerca e rimette il progetto su "Seleziona progetto…"
  document.addEventListener('reset', (e) => {
    const box = e.target.querySelector('[data-pt-picker]');
    if (!box) return;
    setTimeout(() => {
      const sel = e.target.querySelector('[data-assign-project]');
      if (sel) { sel.selectedIndex = 0; sel.dispatchEvent(new Event('change', { bubbles: true })); }
      box.querySelectorAll('[data-picker-row]').forEach((r) => { r.hidden = false; });
      pickerSync(box);
    });
  });
  document.addEventListener('input', (e) => {
    const s = e.target.closest('[data-picker-search]');
    if (!s) return;
    const q = s.value.trim().toLowerCase();
    s.closest('[data-pt-picker]').querySelectorAll('[data-picker-row]').forEach((r) => { r.hidden = q !== '' && !r.dataset.text.includes(q); });
  });
  document.addEventListener('submit', (e) => {
    const box = e.target.querySelector('[data-pt-picker]');
    if (!box) return;
    if (!box.querySelector('[data-picker-row] input[type=checkbox]:checked')) {
      e.preventDefault();
      Protis.toast('Seleziona almeno una persona.', 'warn');
    }
  });

  // Filtro rapido tabelle: <input data-pt-filter="#tabella">
  document.addEventListener('input', (e) => {
    const inp = e.target.closest('[data-pt-filter]');
    if (!inp) return;
    const q = inp.value.trim().toLowerCase();
    document.querySelectorAll(`${inp.dataset.ptFilter} [data-row]`).forEach((row) => {
      const t = (row.dataset.text ?? row.textContent).toLowerCase();
      row.hidden = q !== '' && !q.split(/\s+/).every((w) => t.includes(w)); // tutte le parole, in qualsiasi ordine
    });
  });

  // PWA: service worker + pulsante "Installa"
  if (opts.pwa && 'serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(opts.sw, { scope: opts.root || '/' }).catch(() => { /* facoltativo */ });
    });
  }

  let deferred = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferred = e;
    document.querySelectorAll('[data-pt-install]').forEach((b) => { b.hidden = false; });
  });
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-pt-install]');
    if (!b || !deferred) return;
    deferred.prompt();
    await deferred.userChoice;
    deferred = null;
    b.hidden = true;
  });
})();
