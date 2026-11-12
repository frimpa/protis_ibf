<?php

/**
 * Editor dell'intestazione del timesheet: loghi (fascia composta automaticamente), testo sotto i loghi,
 * righe ordinate (valore automatico a scelta o testo libero) e lingua della tabella.
 *
 * @var \Cnr\Component\Protis\Site\View\Project\HtmlView $this
 * @var object|null $p
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\HeaderTemplate;
use Cnr\Component\Protis\Administrator\Service\ProjectService;
use Joomla\CMS\Uri\Uri;

// i nuovi progetti partono dal modello Standard; quelli esistenti mostrano le loro righe
$layout   = $p->layout ?? 'standard';
$rows     = HeaderTemplate::rowsFor($p ?? (object) [], $p ? $layout : 'standard');
$logos    = $p ? ProjectService::logoList($p) : [];
$logoBase = Uri::root(true) . '/images/protis/loghi/';
$sample   = HeaderTemplate::sampleValues($p ?? (object) []);
$fields   = HeaderTemplate::FIELDS;
$v        = static fn (string $k) => htmlspecialchars((string) ($p->$k ?? ''));
$projVals  = HeaderTemplate::projectValues($p ?? (object) []);
// riga da stampare? (le vecchie righe "?" si spuntano solo se il dato di progetto è compilato)
$printed   = static function (array $r) use ($projVals, $p): bool {
    if (!$p) {
        return true; // nuovo progetto: tutte le righe spuntate
    }

    if (isset($r['print']) && !$r['print']) {
        return false;
    }

    if (str_starts_with($r['label'], '?') && preg_match('/^\{([a-z_]+)\}$/', trim($r['value']), $mm) && \array_key_exists($mm[1], $projVals)) {
        return $projVals[$mm[1]] !== '';
    }

    return true;
};
$pvalFields = ['partita_iva' => ['partita_iva', 'Partita IVA / C.F. dell\'ente'], 'sal' => ['sal_numero', 'Numero del SAL']];
$hasAnnual = (bool) array_filter($rows, static fn ($r) => str_contains($r['value'], '{monte_ore_annuo}'));

$fieldOptions = static function (string $value) use ($fields): string {
    $m   = preg_match('/^\{([a-z_]+)\}$/', trim($value), $mm) && isset($fields[$mm[1]]) ? $mm[1] : '';
    $out = '<option value=""' . ($m === '' ? ' selected' : '') . '>✎ Testo libero</option>';

    foreach ($fields as $k => [$desc]) {
        $out .= '<option value="' . $k . '"' . ($m === $k ? ' selected' : '') . '>{' . $k . '} – ' . htmlspecialchars($desc) . '</option>';
    }

    return $out;
};
?>
<section class="pt-card pt-form pt-hdr" id="pt-hdr">
    <div class="pt-page-head">
        <h2 class="pt-card-title" style="margin:0!important">Intestazione del timesheet</h2>
        <?php if ($p) : ?>
            <a class="pt-btn pt-btn-sm" target="_blank" rel="noopener"
               href="<?= Uri::base(true) ?>/index.php?option=com_protis&task=export.preview&project=<?= (int) $p->id ?>&<?= $this->token() ?>=1">Anteprima PDF (dati salvati)</a>
        <?php endif; ?>
    </div>

    <!-- 1. Loghi -->
    <div class="pt-hdr-block">
        <h3 class="pt-hdr-title">1. Loghi <small>nell'ordine di stampa, da sinistra a destra</small></h3>
        <div class="pt-logo-list" data-bind="logos">
            <?php foreach ($logos as $i => $file) : ?>
                <div class="pt-logo-item" data-logo>
                    <img src="<?= $logoBase . rawurlencode($file) ?>" alt="">
                    <input type="hidden" name="logos[<?= $i ?>][file]" value="<?= htmlspecialchars($file) ?>">
                    <div class="pt-logo-tools">
                        <button type="button" class="pt-btn pt-btn-sm pt-btn-icon" data-move="-1" aria-label="Sposta a sinistra">←</button>
                        <button type="button" class="pt-btn pt-btn-sm pt-btn-icon" data-move="1" aria-label="Sposta a destra">→</button>
                        <label class="pt-check pt-danger"><input type="checkbox" name="logos[<?= $i ?>][remove]" value="1"> rimuovi</label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <label>Aggiungi loghi (PNG, JPG, GIF, WEBP – anche più file insieme)
            <input type="file" name="logo_files[]" multiple accept="image/png,image/jpeg,image/gif,image/webp" data-bind="logoUpload">
        </label>
        <p class="pt-help">Non ci sono limiti di dimensione da rispettare (fino a 10 MB per file): al salvataggio PROTIS IBF riduce le immagini troppo grandi,
            toglie i bordi vuoti, porta tutti i loghi alla stessa altezza e li dispone in una fascia orizzontale uniforme.
            I nuovi loghi vanno in coda; riordinali con le frecce e salva di nuovo. Un solo file viene usato così com'è (utile per una fascia già pronta).</p>
        <?php if (!empty($p->logo)) : ?>
            <div class="pt-banner-preview"><span class="pt-muted">Fascia attuale:</span><img src="<?= $logoBase . rawurlencode($p->logo) ?>" alt="Fascia loghi"></div>
        <?php endif; ?>
    </div>

    <!-- 2. Testo sotto i loghi -->
    <div class="pt-hdr-block">
        <h3 class="pt-hdr-title">2. Testo sotto i loghi <small>facoltativo</small></h3>
        <label>Testo stampato tra la fascia dei loghi e la tabella dell'intestazione
            <textarea name="jform[note]" rows="2" data-bind="note" placeholder="es. Programma Nazionale Ricerca, Innovazione e Competitività per la transizione verde e digitale 2021-2027"><?= $v('note') ?></textarea>
            <small>Da usare solo se il progetto lo richiede (es. riferimento al programma o al CCNL). Lascia vuoto se non serve.</small>
        </label>
        <?php
        $tipi  = ['RI - Ricerca Industriale', 'SS - Sviluppo Sperimentale'];
        $tipo  = (string) ($p->tipo_ricerca ?? '');
        $altro = $tipo !== '' && !\in_array($tipo, $tipi, true);
        ?>
        <div class="pt-row">
            <label>Tipo di Ricerca
                <select name="jform[tipo_ricerca]" data-bind="tipo">
                    <option value="" <?= $tipo === '' ? 'selected' : '' ?>>— nessuno —</option>
                    <?php foreach ($tipi as $t) : ?><option value="<?= htmlspecialchars($t) ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
                    <option value="altro" <?= $altro ? 'selected' : '' ?>>Altro (scrivi)…</option>
                </select>
                <small>Se indicato aggiunge "Tipo di Ricerca:" (<code>{tipo_ricerca}</code>) come prima riga dell'intestazione.</small>
            </label>
            <label data-bind="tipoAltro" <?= $altro ? '' : 'hidden' ?>>Altro tipo di ricerca
                <input name="jform[tipo_ricerca_altro]" value="<?= $altro ? htmlspecialchars($tipo) : '' ?>" maxlength="150" placeholder="es. Ricerca fondamentale">
            </label>
        </div>
    </div>

    <!-- 3. Righe -->
    <div class="pt-hdr-block">
        <h3 class="pt-hdr-title">3. Righe dell'intestazione <small>nell'ordine in cui compaiono</small></h3>
        <p class="pt-help" style="margin-top:-.3rem">Per ogni riga scrivi l'etichetta e scegli il valore: un <strong>dato automatico</strong> (persona, mese, CUP…, compilato per ogni timesheet)
            oppure <strong>✎ Testo libero</strong>.
            Per <code>{partita_iva}</code> e <code>{sal}</code> accanto compare il campo in cui scrivere il valore del progetto.
            La spunta <strong>stampa</strong> decide se la riga compare nel timesheet: togli la spunta alle righe che non vuoi stampare. <?= $p ? '' : 'Il nuovo progetto parte dal modello Standard: modifica, aggiungi o elimina le righe come serve.' ?></p>

        <div class="pt-hdr-extra">
            <label class="pt-check"><input type="checkbox" data-bind="annual" <?= $hasAnnual ? 'checked' : '' ?>>
                Il progetto richiede il <strong>Monte ore lavorativo annuo previsto</strong></label>
            <p class="pt-help">Aggiunge la riga "Monte ore lavorativo annuo previsto:". Il valore è il <strong>Monte ore annuo</strong> indicato nell'associazione della persona al progetto; se non è indicato si usa
                <strong><?= HeaderTemplate::ORE_ANNUE_NUOVI ?></strong> se assunta da meno di 3 anni, altrimenti <strong><?= HeaderTemplate::ORE_ANNUE_STANDARD ?></strong>
                (data di assunzione nella scheda della persona; se manca vale <?= HeaderTemplate::ORE_ANNUE_STANDARD ?>).</p>
            <input type="hidden" name="jform[partita_iva]" value="<?= $v('partita_iva') ?>" data-pval-store="partita_iva">
            <input type="hidden" name="jform[sal_numero]" value="<?= $v('sal_numero') ?>" data-pval-store="sal">
        </div>
        <div class="pt-hdr-rows" data-bind="rows">
            <?php foreach ($rows as $i => $r) :
                $isField = (bool) preg_match('/^\{([a-z_]+)\}$/', trim($r['value']), $mm) && isset($fields[$mm[1]]); ?>
                <div class="pt-hdr-row" data-row>
                    <span class="pt-handle"><?= $i + 1 ?></span>
                    <input class="pt-input" data-k="label" name="header[<?= $i ?>][label]" value="<?= htmlspecialchars(ltrim($r['label'], '?')) ?>" placeholder="Etichetta (es. SAL n.:)" aria-label="Etichetta">
                    <select class="pt-input" data-k="field" aria-label="Valore"><?= $fieldOptions($r['value']) ?></select>
                    <input class="pt-input" data-k="value" name="header[<?= $i ?>][value]" value="<?= htmlspecialchars($r['value']) ?>" placeholder="Testo libero" aria-label="Testo libero" <?= $isField ? 'hidden' : '' ?>>
                    <input class="pt-input" data-k="pval" placeholder="" aria-label="Valore del progetto" hidden>
                    <span class="pt-hdr-tools">
                        <label class="pt-hdr-opt" title="Togli la spunta per non stampare la riga nel timesheet"><input type="checkbox" data-k="print" <?= $printed($r) ? 'checked' : '' ?>> stampa</label>
                        <input type="hidden" data-k="printv" name="header[<?= $i ?>][print]" value="<?= $printed($r) ? 1 : 0 ?>">
                        <button type="button" class="pt-btn pt-btn-sm pt-btn-icon" data-up aria-label="Sposta su">↑</button>
                        <button type="button" class="pt-btn pt-btn-sm pt-btn-icon" data-down aria-label="Sposta giù">↓</button>
                        <button type="button" class="pt-btn pt-btn-sm pt-btn-icon pt-danger" data-del aria-label="Elimina riga">✕</button>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="pt-btn pt-btn-sm" data-add-row>+ Aggiungi riga</button>

    </div>

    <!-- 4. Tabella -->
    <div class="pt-hdr-block">
        <h3 class="pt-hdr-title">4. Tabella delle ore</h3>
        <label style="max-width:420px">Lingua delle righe della tabella
            <?php $lang = ($p->table_lang ?? '') ?: (\in_array($layout, ['horizon', 'mise'], true) ? 'en' : 'it'); ?>
            <select name="jform[table_lang]">
                <option value="it" <?= $lang === 'it' ? 'selected' : '' ?>>Italiano (Attività ordinaria, Malattia…)</option>
                <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>Inglese (Institutional Activity, Sick Leave…)</option>
            </select>
        </label>
        <input type="hidden" name="jform[layout]" value="<?= htmlspecialchars($layout) ?>">
    </div>

    <!-- Anteprima -->
    <div class="pt-hdr-block">
        <h3 class="pt-hdr-title">Anteprima <small>con dati di esempio</small></h3>
        <?php $pal = HeaderTemplate::palette(); ?>
        <div class="pt-hdr-preview" style="--pt-tbl-label:#<?= $pal['label'] ?>;--pt-tbl-value:#<?= $pal['value'] ?>;--pt-tbl-head:#<?= $pal['head'] ?>">
            <div class="pt-hdr-preview-logos" data-bind="previewLogos">
                <?php if (!empty($p->logo)) : ?><img src="<?= $logoBase . rawurlencode($p->logo) ?>" alt=""><?php endif; ?>
            </div>
            <p class="pt-hdr-preview-note" data-bind="previewNote"></p>
            <table data-bind="preview"></table>
            <div class="pt-hdr-preview-days"><table data-bind="previewDays"></table></div>
        </div>
    </div>
</section>

<script>
(() => {
  const box = document.getElementById('pt-hdr');
  const rowsBox = box.querySelector('[data-bind="rows"]');
  const sample = <?= json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const LABELS = <?= json_encode(\Cnr\Component\Protis\Administrator\Service\TimesheetService::LABELS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const PAL = <?= json_encode(HeaderTemplate::palette()) ?>;
  const optionsHtml = <?= json_encode($fieldOptions(''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const ANNUAL_LABEL = 'Monte ore lavorativo annuo previsto:';

  const rowHtml = (r = { label: '', value: '' }) => `<div class="pt-hdr-row" data-row><span class="pt-handle"></span>
    <input class="pt-input" data-k="label" value="${esc(r.label)}" placeholder="Etichetta (es. SAL n.:)" aria-label="Etichetta">
    <select class="pt-input" data-k="field" aria-label="Valore">${optionsHtml}</select>
    <input class="pt-input" data-k="value" value="${esc(r.value)}" placeholder="Testo libero" aria-label="Testo libero">
    <input class="pt-input" data-k="pval" aria-label="Valore del progetto" hidden>
    <span class="pt-hdr-tools"><label class="pt-hdr-opt" title="Togli la spunta per non stampare la riga nel timesheet"><input type="checkbox" data-k="print" checked> stampa</label>
    <input type="hidden" data-k="printv" value="1">
    <button type="button" class="pt-btn pt-btn-sm pt-btn-icon" data-up aria-label="Sposta su">↑</button>
    <button type="button" class="pt-btn pt-btn-sm pt-btn-icon" data-down aria-label="Sposta giù">↓</button>
    <button type="button" class="pt-btn pt-btn-sm pt-btn-icon pt-danger" data-del aria-label="Elimina riga">✕</button></span></div>`;

  const parts = (row) => ({ l: row.querySelector('[data-k=label]'), f: row.querySelector('[data-k=field]'), v: row.querySelector('[data-k=value]'), pv: row.querySelector('[data-k=pval]') });
  const PVAL = <?= json_encode(array_map(static fn ($x) => $x[1], $pvalFields), JSON_UNESCAPED_UNICODE) ?>;
  const store = (k) => box.querySelector(`[data-pval-store="${k}"]`);

  function syncRow(row) {
    const { f, v, pv } = parts(row);
    const m = v.value.trim().match(/^\{([a-z_]+)\}$/);
    if (m && [...f.options].some((o) => o.value === m[1])) f.value = m[1];
    v.hidden = f.value !== '';
    // Partita IVA e SAL: accanto il campo con il valore del progetto
    const k = f.value in PVAL ? f.value : '';
    pv.hidden = !k;
    pv.dataset.field = k;
    if (k) { pv.value = store(k).value; pv.placeholder = PVAL[k]; }
  }

  function renumber() {
    rowsBox.querySelectorAll('[data-row]').forEach((row, i) => {
      row.querySelector('.pt-handle').textContent = i + 1;
      const { l, v } = parts(row);
      l.name = `header[${i}][label]`;
      v.name = `header[${i}][value]`;
      row.querySelector('[data-k=printv]').name = `header[${i}][print]`;
    });
    box.querySelectorAll('[data-logo]').forEach((it, i) => {
      it.querySelectorAll('input').forEach((inp) => { inp.name = inp.name.replace(/logos\[\d+\]/, `logos[${i}]`); });
    });
    syncAnnual();
    preview();
  }

  function preview() {
    const extra = {};
    Object.keys(PVAL).forEach((k) => { if (store(k).value.trim()) extra[k] = store(k).value.trim(); });
    const tsel = box.querySelector('[data-bind="tipo"]');
    const tval = tsel.value === 'altro' ? box.querySelector('[data-bind="tipoAltro"] input').value.trim() : tsel.value;
    if (tval) extra.tipo_ricerca = tval;
    const vals = { ...sample, ...extra };
    const html = [...rowsBox.querySelectorAll('[data-row]')].map((row) => {
      const { l, v } = parts(row);
      const label = l.value.replace(/^\?/, '');
      const val = v.value.replace(/\{([a-z_]+)\}/g, (m, k) => (k in vals ? vals[k] : m));
      if (!row.querySelector('[data-k=print]').checked) return '';
      return `<tr><th>${esc(label)}</th><td>${esc(val)}</td></tr>`;
    }).join('');
    box.querySelector('[data-bind="preview"]').innerHTML = html;
    previewDays();
    const note = box.querySelector('[data-bind="note"]').value.trim();
    const pn = box.querySelector('[data-bind="previewNote"]');
    pn.textContent = note;
    pn.hidden = !note;
  }

  // tabella delle ore (solo le scritte): WP del progetto + righe fisse nella lingua scelta
  function previewDays() {
    const lang = box.querySelector('[name="jform[table_lang]"]').value === 'en' ? 'en' : 'it';
    const L = LABELS[lang];
    const wps = [...document.querySelectorAll('#pt-wp-editor [data-wp-row] .pt-input')].map((i, n) => i.value.trim() || `WP${n + 1}`);
    const days = Array.from({ length: 31 }, (_, i) => i + 1);
    const empty = days.map(() => '<td></td>').join('');
    const cols = '<colgroup><col style="width:20%">' + days.map(() => '<col>').join('') + '<col style="width:6.5%"></colgroup>';
    const head = `<tr class="h"><th>${esc(L.h_desc)} ${esc(L.h_desc2)}</th>${days.map((d) => `<td>${d}</td>`).join('')}<td>${esc(L.h_tot)} ${esc(L.h_tot2)}</td></tr>`;
    const row = (t, cls = '') => `<tr class="${cls}"><th>${esc(t)}</th>${empty}<td></td></tr>`;
    box.querySelector('[data-bind="previewDays"]').innerHTML = cols + head
      + wps.map((w) => row(w)).join('')
      + ['ordinary', 'sick', 'holiday', 'permit', 'other'].map((k) => row(L[k])).join('')
      + row(L.total, 'tot');
  }

  // la spunta segue la presenza della riga {monte_ore_annuo}
  function syncAnnual() {
    box.querySelector('[data-bind="annual"]').checked = [...rowsBox.querySelectorAll('[data-k=value]')].some((i) => i.value.includes('{monte_ore_annuo}'));
  }

  document.getElementById('pt-wp-editor')?.addEventListener('input', () => previewDays());
  document.getElementById('pt-wp-editor')?.addEventListener('click', () => setTimeout(previewDays));
  box.querySelector('[name="jform[table_lang]"]').addEventListener('change', () => previewDays());
  box.addEventListener('input', (e) => {
    if (e.target.matches('[data-k=pval]') && e.target.dataset.field) {
      const k = e.target.dataset.field;
      store(k).value = e.target.value;
      box.querySelectorAll(`[data-k=pval][data-field="${k}"]`).forEach((o) => { if (o !== e.target) o.value = e.target.value; });
    }
    preview();
  });
  box.addEventListener('change', (e) => {
    const row = e.target.closest('[data-row]');
    if (row && e.target.matches('[data-k=print]')) { row.querySelector('[data-k=printv]').value = e.target.checked ? 1 : 0; row.classList.toggle('is-off', !e.target.checked); preview(); return; }
    if (row && e.target.matches('[data-k=field]')) {
      const { f, v } = parts(row);
      if (f.value) {
        v.value = `{${f.value}}`;
        syncRow(row);
        if (!parts(row).pv.hidden) parts(row).pv.focus();
      } else {
        parts(row).pv.hidden = true;
        if (/^\{[a-z_]+\}$/.test(v.value.trim())) v.value = '';
        v.hidden = false;
        v.focus();
      }
      syncAnnual();
      preview();
    }
  });

  // Tipo di ricerca: mostra il campo "altro" e aggiunge/toglie la riga {tipo_ricerca}
  box.querySelector('[data-bind="tipo"]').addEventListener('change', (e) => {
    const other = box.querySelector('[data-bind="tipoAltro"]');
    other.hidden = e.target.value !== 'altro';
    if (!other.hidden) other.querySelector('input').focus();
    const rows = [...rowsBox.querySelectorAll('[data-row]')].filter((r) => parts(r).v.value.includes('{tipo_ricerca}'));
    if (e.target.value && !rows.length) {
      // prima riga dell'intestazione (prima di "Avviso Progetto")
      rowsBox.insertAdjacentHTML('afterbegin', rowHtml({ label: 'Tipo di Ricerca:', value: '{tipo_ricerca}' }));
      rowsBox.querySelectorAll('[data-row]').forEach(syncRow);
    } else if (!e.target.value) {
      rows.forEach((r) => r.remove());
    }
    renumber();
  });

  box.querySelector('[data-bind="annual"]').addEventListener('change', (e) => {
    const rows = [...rowsBox.querySelectorAll('[data-row]')].filter((r) => parts(r).v.value.includes('{monte_ore_annuo}'));
    if (e.target.checked && !rows.length) {
      // prima della riga del totale ore, se c'è
      const tot = [...rowsBox.querySelectorAll('[data-row]')].find((r) => parts(r).v.value.includes('{ore_progetto}'));
      const html = rowHtml({ label: ANNUAL_LABEL, value: '{monte_ore_annuo}' });
      tot ? tot.insertAdjacentHTML('beforebegin', html) : rowsBox.insertAdjacentHTML('beforeend', html);
      rowsBox.querySelectorAll('[data-row]').forEach(syncRow);
    } else if (!e.target.checked) {
      rows.forEach((r) => r.remove());
    }
    renumber();
  });

  box.addEventListener('click', (e) => {
    const row = e.target.closest('[data-row]');
    if (row && e.target.closest('[data-up]') && row.previousElementSibling) { row.parentNode.insertBefore(row, row.previousElementSibling); renumber(); }
    if (row && e.target.closest('[data-down]') && row.nextElementSibling) { row.parentNode.insertBefore(row.nextElementSibling, row); renumber(); }
    if (row && e.target.closest('[data-del]')) { row.remove(); renumber(); }
    if (e.target.closest('[data-add-row]')) {
      rowsBox.insertAdjacentHTML('beforeend', rowHtml());
      syncRow(rowsBox.lastElementChild);
      renumber();
      rowsBox.lastElementChild.querySelector('input').focus();
    }

    const logo = e.target.closest('[data-logo]');
    const mv = e.target.closest('[data-move]');
    if (logo && mv) {
      const sib = mv.dataset.move === '-1' ? logo.previousElementSibling : logo.nextElementSibling;
      if (sib) { mv.dataset.move === '-1' ? logo.parentNode.insertBefore(logo, sib) : logo.parentNode.insertBefore(sib, logo); renumber(); }
    }
  });

  // anteprima immediata dei loghi selezionati (prima del salvataggio)
  box.querySelector('[data-bind="logoUpload"]').addEventListener('change', (e) => {
    const strip = box.querySelector('[data-bind="previewLogos"]');
    const files = [...e.target.files];
    const big = files.filter((f) => f.size > 10 * 1024 * 1024);
    if (big.length) { Protis.toast(`File troppo grande (oltre 10 MB): ${big.map((f) => f.name).join(', ')}`, 'error', 7000); e.target.value = ''; return; }
    if (!files.length) return;
    const existing = [...box.querySelectorAll('[data-logo]')].filter((it) => !it.querySelector('[type=checkbox]').checked).map((it) => it.querySelector('img').src);
    strip.innerHTML = '<div class="pt-strip">' + existing.concat(files.map((f) => URL.createObjectURL(f))).map((src) => `<img src="${src}" alt="">`).join('') + '</div>';
  });

  // "solo se compilata" → prefisso ? nell'etichetta salvata
  box.closest('form').addEventListener('submit', () => {
    rowsBox.querySelectorAll('[data-row]').forEach((row) => {
      const { l } = parts(row);
      l.value = l.value.replace(/^\?/, '');
    });
  });

  rowsBox.querySelectorAll('[data-row]').forEach((r) => { syncRow(r); r.classList.toggle('is-off', !r.querySelector('[data-k=print]').checked); });
  renumber();
})();
</script>
