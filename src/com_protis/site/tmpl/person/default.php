<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Person\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Cnr\Component\Protis\Administrator\Helper\Time;

$this->partial('header');
$p = $this->item;
$v = static fn (string $k) => htmlspecialchars((string) ($p->$k ?? ''));
?>
<div class="pt-page-head">
    <h1><?= $p ? htmlspecialchars($p->cognome . ' ' . $p->nome) : 'Nuova persona (senza account LDAP)' ?></h1>
    <a class="pt-btn pt-btn-ghost" href="<?= $this->link('view=people') ?>">← Persone</a>
</div>

<?php if (!$p) : ?>
    <div class="pt-alert pt-alert-info">Usa questa scheda per collaboratori senza credenziali CNR. Per l'accesso crea anche un utente Joomla con lo stesso <em>username</em> (Utenti → Nuovo); le presenze saranno inserite manualmente nel calendario.</div>
<?php endif; ?>

<div class="pt-grid pt-grid-2">
    <form class="pt-card pt-form" method="post" action="<?= $this->link('task=manage.savePerson') ?>">
        <div class="pt-person-head">
            <?php if ($p) : ?><?= $this->miniAvatar($p) ?><?php endif; ?>
            <h2 class="pt-card-title">Anagrafica</h2>
            <?php if ($p) : ?>
                <label class="pt-switch pt-switch-right <?= $p->active ? 'is-on' : '' ?>" data-switch data-autosave="api.togglePerson" data-id="<?= (int) $p->id ?>" title="Una persona disattivata non può accedere (nemmeno con le credenziali CNR) e non viene sincronizzata da ePAS, finché non viene riattivata">
                    <input type="hidden" name="jform[active]" value="0">
                    <input type="checkbox" name="jform[active]" value="1" <?= $p->active ? 'checked' : '' ?>>
                    <span class="pt-switch-track" aria-hidden="true"><span class="pt-switch-thumb"></span></span>
                    <span class="pt-switch-label" data-on="Attivo" data-off="Disattivato"><?= $p->active ? 'Attivo' : 'Disattivato' ?></span>
                </label>
            <?php endif; ?>
        </div>
        <input type="hidden" name="id" value="<?= (int) ($p->id ?? 0) ?>">
        <input type="hidden" name="<?= $this->token() ?>" value="1">
        <?php if (!$p) : ?>
            <label>Username * <input name="jform[uid]" required pattern="[A-Za-z0-9._\-@]+"></label>
        <?php else : ?>
            <p><span class="pt-chip">Username: <?= $v('uid') ?></span> <span class="pt-chip"><?= ['ldap' => 'LDAP CNR', 'external' => 'LDAP CNR · altro istituto'][$p->source] ?? 'inserita a mano' ?></span></p>
        <?php endif; ?>
        <?php if ($p && $p->source !== 'manual') : ?>
            <dl class="pt-kv pt-ldap-data">
                <dt>Nominativo</dt><dd><?= htmlspecialchars(trim($p->cognome . ' ' . $p->nome)) ?: '–' ?></dd>
                <dt>Email</dt><dd><?= $v('email') ?: '–' ?></dd>
                <dt>Matricola CNR</dt><dd><?= $v('matricola') ?: '<span class="pt-muted">nessuna (presenze manuali)</span>' ?></dd>
            </dl>
            <p class="pt-help">Nominativo, email e matricola provengono da LDAP CNR e si aggiornano a ogni accesso.</p>
        <?php else : ?>
            <div class="pt-row">
                <label>Nome <input name="jform[nome]" value="<?= $v('nome') ?>"></label>
                <label>Cognome <input name="jform[cognome]" value="<?= $v('cognome') ?>"></label>
            </div>
            <div class="pt-row">
                <label>Email <input type="email" name="jform[email]" value="<?= $v('email') ?>"></label>
                <label>Matricola CNR <input name="jform[matricola]" value="<?= $v('matricola') ?>"><small>vuota = presenze manuali</small></label>
            </div>
        <?php endif; ?>
        <label>Codice fiscale <input name="jform[codice_fiscale]" value="<?= $v('codice_fiscale') ?>" maxlength="16" style="text-transform:uppercase"></label>
        <label>Data di assunzione
            <input type="date" name="jform[data_assunzione]" value="<?= $v('data_assunzione') ?>">
            <small>Serve per il "Monte ore lavorativo annuo previsto" nei progetti che lo richiedono: 1520 ore nei primi 3 anni dall'assunzione, poi 1506.</small>
        </label>
        <label>Tipo di contratto
            <input name="jform[tipo_contratto]" list="pt-contracts" value="<?= $v('tipo_contratto') ?>">
            <datalist id="pt-contracts"><?php foreach ($this->contracts as $c) : ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
        </label>
        <?php $isExt = $p && !empty($p->external); ?>
        <div data-ext-fields <?= $isExt ? '' : 'hidden' ?>>
            <div class="pt-row">
                <label>Istituto di appartenenza <input name="jform[ldap_gruppo]" value="<?= $v('ldap_gruppo') ?>" maxlength="255" placeholder="es. CNR - Istituto di Neuroscienze"></label>
                <label>Sede <input name="jform[sede_esterna]" value="<?= $v('sede_esterna') ?>" maxlength="150" placeholder="es. Pisa"></label>
            </div>
            <small class="pt-help">Collega di altro istituto: non appartiene alle sedi dell'istituto.</small>
        </div>
        <div data-ibf-site <?= $isExt ? 'hidden' : '' ?>>
            <?php $this->partial('sitepicker', ['sites' => $this->sites, 'person' => $p ?? (object) ['site_id' => $this->me->site_id ?? 0], 'label' => 'Sede', 'disabled' => !Acl::canManageAllSites()]); ?>
        </div>
        <?php if ($p) : ?>
            <label class="pt-check"><input type="hidden" name="jform[external]" value="0"><input type="checkbox" name="jform[external]" value="1" data-ext-toggle <?= !empty($p->external) ? 'checked' : '' ?>> Collega di altro istituto (accesso per username)</label>
        <?php endif; ?>
        <?php if ($p) :
            $isSelf = (int) $p->id === (int) ($this->me->id ?? 0); ?>
            <div class="pt-epas-box pt-role-box">
                <div class="pt-epas-box-head">
                    <strong>Ruolo in PROTIS IBF</strong>
                    <?php if ($this->role === null) : ?>
                        <select class="pt-input pt-role-select" disabled aria-label="Ruolo in PROTIS IBF"><option selected>Utente</option></select>
                    <?php elseif ($this->role === 'admin') : ?>
                        <span class="pt-chip pt-chip-ok">Amministratore</span>
                    <?php else : ?>
                        <select class="pt-input pt-role-select" data-role-select data-id="<?= (int) $p->id ?>" <?= $isSelf ? 'disabled' : '' ?> aria-label="Ruolo in PROTIS IBF">
                            <option value="user" <?= $this->role === 'user' ? 'selected' : '' ?>>Utente</option>
                            <option value="manager" <?= $this->role === 'manager' ? 'selected' : '' ?>>Responsabile (amministrativo)</option>
                        </select>
                    <?php endif; ?>
                </div>
                <small class="pt-help">
                    <?php if ($this->role === null) : ?>
                        Al primo accesso entra come <strong>Utente</strong> (gruppo Registered). Dopo quel primo accesso potrai renderla <strong>Responsabile</strong> da qui.
                    <?php elseif ($this->role === 'admin') : ?>
                        Gli amministratori si gestiscono dall'amministrazione di Joomla (Utenti).
                    <?php elseif ($isSelf) : ?>
                        Non puoi modificare il tuo ruolo.
                    <?php else : ?>
                        <strong>Utente</strong>: proprio calendario, associazioni e timesheet (gruppo Registered).
                        <strong>Responsabile</strong>: in più progetti, associazioni, persone e calendari di tutti (gruppo PROTIS Responsabili). Il cambio vale dal prossimo accesso della persona.
                    <?php endif; ?>
                </small>
            </div>
        <?php endif; ?>
        <?php if ($p) :
            $hasMatr = !empty($p->matricola);
            $epasOn  = $hasMatr && empty($p->epas_off);
            $reason  = \Cnr\Component\Protis\Administrator\Service\PeopleService::epasReason($p); ?>
            <div class="pt-epas-box" data-has-matr="<?= $hasMatr ? 1 : 0 ?>">
                <div class="pt-epas-box-head">
                    <strong>Presenze</strong>
                    <label class="pt-switch pt-switch-right <?= $epasOn ? 'is-on' : '' ?> <?= $hasMatr ? '' : 'is-disabled' ?>" data-switch data-autosave="api.togglePersonEpas" data-id="<?= (int) $p->id ?>"
                           title="<?= $hasMatr ? 'Acceso: le presenze arrivano da ePAS. Spento: si inseriscono a mano nel calendario.' : 'Senza matricola CNR le presenze si inseriscono sempre a mano' ?>">
                        <input type="checkbox" <?= $epasOn ? 'checked' : '' ?> <?= $hasMatr ? '' : 'disabled' ?>>
                        <span class="pt-switch-track" aria-hidden="true"><span class="pt-switch-thumb"></span></span>
                        <span class="pt-switch-label" data-on="Da ePAS" data-off="Inserimento manuale"><?= $epasOn ? 'Da ePAS' : 'Inserimento manuale' ?></span>
                    </label>
                </div>
                <label data-epas-site <?= $isExt ? '' : 'hidden' ?>>Sede ePAS del suo istituto
                    <select name="jform[epas_site_id]" data-epas-site-select>
                        <option value="">— nessuna: presenze inserite a mano —</option>
                        <?php foreach ($this->epasSites as $s) : ?>
                            <option value="<?= (int) $s->id ?>" <?= (int) ($p->epas_site_id ?? 0) === (int) $s->id ? 'selected' : '' ?> <?= $s->has_epas ? '' : 'disabled' ?>><?= htmlspecialchars(($s->istituto ? $s->istituto . ' – ' : '') . $s->sede . ' (' . $s->codice_sede . ')') ?><?= $s->has_epas ? '' : ' – credenziali mancanti' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Per leggere da ePAS le presenze di un collega esterno serve la sede del <em>suo</em> istituto con codice sede e credenziali ePAS "gestore anagrafica" di quell'istituto
                        (<a href="<?= $this->link('view=settings') ?>#altri-istituti">Impostazioni → ePAS di altri istituti</a>)<?= $this->epasSites ? '' : ': non ne è ancora stata aggiunta nessuna' ?>.</small>
                </label>
                <small class="pt-help" data-epas-reason><?= $reason ? htmlspecialchars(ucfirst($reason)) . '.' : 'Le presenze vengono lette da ePAS (sincronizzazione automatica); il calendario non è modificabile a mano.' ?></small>
            </div>
        <?php endif; ?>
        <div class="pt-form-actions pt-actions-split">
            <?php if ($p && !empty($p->external) && (int) $p->id !== (int) ($this->me->id ?? 0) && $this->role !== 'admin') : ?>
                <?php if ($this->reported === 0) : ?>
                    <button type="submit" form="pt-del-person" class="pt-btn pt-btn-ghost pt-danger">Elimina persona</button>
                <?php else : ?>
                    <span class="pt-del-locked">
                        <button type="button" class="pt-btn pt-btn-ghost pt-danger" disabled aria-describedby="pt-del-why">Elimina persona</button>
                        <small id="pt-del-why" class="pt-help">Non eliminabile: ha ore rendicontate. Per toglierla dall'uso spegni l'interruttore <strong>Attivo</strong> in alto: da disattivata non può più accedere, finché non la riattivi.</small>
                    </span>
                <?php endif; ?>
            <?php else : ?><span></span><?php endif; ?>
            <button class="pt-btn pt-btn-primary">Salva</button>
        </div>
    </form>
    <?php if ($p && !empty($p->external) && $this->reported === 0 && (int) $p->id !== (int) ($this->me->id ?? 0) && $this->role !== 'admin') : ?>
        <form id="pt-del-person" method="post" action="<?= $this->link('task=manage.deletePerson') ?>" hidden
              data-confirm="Eliminare <?= htmlspecialchars(trim($p->cognome . ' ' . $p->nome) ?: $p->uid) ?>? Vengono eliminati anche le sue associazioni, le presenze e l'account. L'operazione non si può annullare.">
            <input type="hidden" name="id" value="<?= (int) $p->id ?>"><input type="hidden" name="<?= $this->token() ?>" value="1">
        </form>
    <?php endif; ?>

    <?php if ($p) : ?>
        <section class="pt-card">
            <h2 class="pt-card-title">Progetti associati</h2>
            <?php if ($this->assignments) : ?>
                <div class="pt-assoc-head" aria-hidden="true"><span>Progetto</span><span>Ore previste<br>da rendicontare</span><span>Monte ore<br>annuo</span><span></span></div>
                <ul class="pt-kv" style="display:block;list-style:none;padding:0">
                    <?php foreach ($this->assignments as $a) : ?>
                        <li class="pt-assoc-row">
                            <a href="<?= $this->link('view=project&id=' . (int) $a->project_id) ?>"><?= htmlspecialchars($a->acronimo) ?></a>
                            <span class="pt-assoc-hours" data-label="Ore previste"><?php $this->partial('hoursedit', ['a' => $a, 'back' => 'person', 'backExtra' => '&id=' . (int) $p->id, 'field' => 'monteore']); ?></span>
                            <span class="pt-assoc-hours" data-label="Monte ore annuo"><?php $this->partial('hoursedit', ['a' => $a, 'back' => 'person', 'backExtra' => '&id=' . (int) $p->id, 'field' => 'monte_annuo']); ?></span>
                            <form class="pt-assoc-del" method="post" action="<?= $this->link('task=manage.deleteAssignment') ?>" data-confirm="Rimuovere l'associazione?">
                                <input type="hidden" name="id" value="<?= (int) $a->id ?>"><input type="hidden" name="back" value="person"><input type="hidden" name="back_extra" value="&id=<?= (int) $p->id ?>">
                                <input type="hidden" name="<?= $this->token() ?>" value="1"><button class="pt-btn pt-btn-sm pt-btn-ghost pt-danger" title="Rimuovi l'associazione" aria-label="Rimuovi l'associazione">×</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="pt-muted">Nessun progetto.</p>
            <?php endif; ?>
            <form class="pt-form pt-assign-new" method="post" action="<?= $this->link('task=manage.saveAssignment') ?>">
                <h3 class="pt-card-title">Aggiungi una nuova associazione</h3>
                <input type="hidden" name="person_id[]" value="<?= (int) $p->id ?>"><input type="hidden" name="back" value="person"><input type="hidden" name="back_extra" value="&id=<?= (int) $p->id ?>">
                <input type="hidden" name="<?= $this->token() ?>" value="1">
                <div class="pt-row pt-row-bottom">
                    <label>Progetto <select name="project_id" required><option value="" disabled selected>Seleziona progetto…</option><?php $assoc = array_flip(array_map(static fn ($a) => (int) $a->project_id, $this->assignments)); foreach ($this->projects as $pr) : $isA = isset($assoc[(int) $pr->id]); ?><option value="<?= (int) $pr->id ?>" <?= $isA ? 'disabled' : '' ?>><?= htmlspecialchars($pr->acronimo) ?><?= $isA ? ' (già associato)' : '' ?></option><?php endforeach; ?></select></label>
                    <label>Ore previste sul progetto da rendicontare <input name="monteore" inputmode="decimal" placeholder="es. 300"></label>
                    <label>Monte ore annuo <small>facoltativo</small><input name="monte_annuo" inputmode="decimal" placeholder="es. 1520"></label>
                </div>
                <div class="pt-form-actions pt-actions-split">
                    <a class="pt-btn pt-btn-primary" href="<?= $this->link('view=calendar&person=' . (int) $p->id) ?>">Apri calendario</a>
                    <button class="pt-btn pt-btn-primary">Associa</button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>
<script>
// testo sotto l'interruttore Presenze, aggiornato mentre si modifica la scheda
function epasReason() {
  const box = document.querySelector('.pt-epas-box:not(.pt-role-box)');
  if (!box) return;
  const why = box.querySelector('[data-epas-reason]');
  const on = box.querySelector('.pt-switch input').checked;
  const ext = document.querySelector('[data-ext-toggle]')?.checked;
  const site = box.querySelector('[data-epas-site-select]')?.value;
  why.textContent = box.dataset.hasMatr !== '1' ? 'Senza matricola CNR: presenze sempre inserite a mano.'
    : !on ? 'Inserimento manuale delle presenze nel calendario.'
    : ext && !site ? 'Altro istituto: scegli la sede ePAS del suo istituto per leggere le presenze (poi Salva); fino ad allora le inserisce a mano.'
    : ext ? 'Le presenze vengono lette dall\'ePAS del suo istituto dopo il salvataggio.'
    : 'Le presenze vengono lette da ePAS (sincronizzazione automatica); il calendario non è modificabile a mano.';
}
document.addEventListener('change', (e) => { if (e.target.closest('.pt-epas-box:not(.pt-role-box)')) setTimeout(epasReason, 0); });
// ruolo: salvataggio immediato
document.querySelector('[data-role-select]')?.addEventListener('change', (e) => {
  const s = e.target, prev = s.dataset.prev ?? (s.value === 'user' ? 'manager' : 'user');
  s.disabled = true;
  Protis.api('api.setPersonRole', {}, 'POST', { id: s.dataset.id, role: s.value })
    .then((r) => { Protis.toast(r.message || 'Salvato'); s.dataset.prev = s.value; })
    .catch((err) => { Protis.toast(err.message, 'error', 7000); s.value = prev; })
    .finally(() => { s.disabled = false; });
});
document.querySelectorAll('[data-role-select]').forEach((s) => { s.dataset.prev = s.value; });
(() => {
  // altro istituto: istituto e sede al posto della sede dell'istituto (che viene azzerata)
  const t = document.querySelector('[data-ext-toggle]');
  if (!t) return;
  const ext = document.querySelector('[data-ext-fields]');
  const ibf = document.querySelector('[data-ibf-site]');
  const sync = () => {
    ext.hidden = !t.checked;
    ibf.hidden = t.checked;
    // i campi nascosti non vengono inviati (stesso nome jform[sede_esterna] nei due blocchi)
    ext.querySelectorAll('input, select').forEach((i) => { i.disabled = !t.checked; });
    const es = document.querySelector('[data-epas-site]');
    if (es) { es.hidden = !t.checked; es.querySelector('select').disabled = !t.checked; }
    epasReason();
    ibf.querySelectorAll('select, input').forEach((i) => { if (!i.dataset.lock) i.disabled = t.checked; });
  };
  ibf.querySelectorAll('select:disabled, input:disabled').forEach((i) => { i.dataset.lock = '1'; });
  const sel = ibf.querySelector('select');
  t.addEventListener('change', () => {
    if (t.checked) {
      // ricorda la sede dell'istituto per poterla ripristinare se la spunta viene tolta per errore
      sel.dataset.prev = sel.value;
      sel.value = '';
    } else if (sel.dataset.prev !== undefined) {
      sel.value = sel.dataset.prev;
    }
    sel.dispatchEvent(new Event('change', { bubbles: true }));
    sync();
    if (t.checked) ext.querySelector('input').focus();
  });
  sync();
})();
</script>
<?php $this->partial('footer'); ?>
