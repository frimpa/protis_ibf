<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Profile\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$this->partial('header');
$p = $this->me;
$v = static fn (string $k) => htmlspecialchars((string) ($p->$k ?? ''));
?>
<div class="pt-page-head"><h1>Il mio profilo</h1></div>

<?php if ($this->missing) : ?>
    <div class="pt-alert pt-alert-warning">Compila i campi mancanti per generare i timesheet: <strong><?= htmlspecialchars(implode(', ', $this->missing)) ?></strong>.</div>
<?php endif; ?>

<div class="pt-grid pt-grid-2">
    <section class="pt-card">
        <?php $av = \Cnr\Component\Protis\Administrator\Service\PeopleService::avatarUrl($p); ?>
        <div class="pt-profile-photo">
            <div class="pt-photo<?= $av ? '' : ' is-empty' ?>">
                <?php if ($av) : ?><img src="<?= $av ?>" alt="Foto di <?= $v('nome') ?>"><?php else : ?><span><?= htmlspecialchars(mb_substr($p->nome ?? '?', 0, 1) . mb_substr($p->cognome ?? '', 0, 1)) ?></span><?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" action="<?= $this->link('task=manage.saveAvatar') ?>" class="pt-photo-form" data-photo-form>
                <input type="hidden" name="<?= $this->token() ?>" value="1">
                <label class="pt-btn pt-btn-sm pt-btn-primary pt-file-btn"><?= $av ? 'Cambia foto' : 'Carica una foto' ?>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" hidden data-photo-input>
                </label>
                <?php if ($av) : ?><button class="pt-btn pt-btn-sm pt-btn-ghost pt-danger" name="remove" value="1" formnovalidate>Rimuovi</button><?php endif; ?>
                <small class="pt-help">JPG, PNG o WEBP fino a 10 MB: viene ritagliata e ridotta automaticamente.</small>
            </form>
        </div>
        <h2 class="pt-card-title">Dati istituzionali</h2>
        <dl class="pt-kv">
            <dt>Nome</dt><dd><?= $v('nome') ?> <?= $v('cognome') ?></dd>
            <dt>Username</dt><dd><?= $v('uid') ?></dd>
            <dt>Email</dt><dd><?= $v('email') ?></dd>
            <dt>Matricola</dt><dd><?= $p->matricola ? $v('matricola') : '<span class="pt-chip pt-chip-warn">non presente – presenze manuali</span>' ?></dd>
            <dt>Provenienza dati</dt><dd><?= $p->source === 'ldap' ? 'LDAP CNR (aggiornati a ogni accesso)' : 'inserimento manuale' ?></dd>
        </dl>
    </section>

    <form class="pt-card pt-form" method="post" action="<?= $this->link('task=manage.saveProfile') ?>">
        <h2 class="pt-card-title">Dati per il timesheet</h2>
        <input type="hidden" name="<?= $this->token() ?>" value="1">
        <label>Codice fiscale <input name="jform[codice_fiscale]" value="<?= $v('codice_fiscale') ?>" maxlength="16" required pattern="[A-Za-z0-9]{11,16}" style="text-transform:uppercase"></label>
        <label>Tipo di contratto
            <input name="jform[tipo_contratto]" list="pt-contracts" value="<?= $v('tipo_contratto') ?>" required>
            <datalist id="pt-contracts"><?php foreach ($this->contracts as $c) : ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
        </label>
        <label>Data di assunzione <small>facoltativa</small>
            <input type="date" name="jform[data_assunzione]" value="<?= $v('data_assunzione') ?>">
            <small>Serve solo per i progetti che chiedono il "Monte ore lavorativo annuo previsto" (1520 ore nei primi 3 anni, poi 1506). Se non la indichi vale 1506.</small>
        </label>
        <?php if (!empty($p->external)) : ?>
            <div class="pt-row">
                <label>Istituto di appartenenza <input name="jform[ldap_gruppo]" value="<?= $v('ldap_gruppo') ?>" maxlength="255" placeholder="es. CNR - Istituto di Neuroscienze"></label>
                <label>Sede <input name="jform[sede_esterna]" value="<?= $v('sede_esterna') ?>" maxlength="150" placeholder="es. Pisa"></label>
            </div>
        <?php else : ?>
        <?php $this->partial('sitepicker', ['sites' => $this->sites, 'person' => $p, 'label' => 'Sede di afferenza', 'required' => true]); ?>
        <?php endif; ?>
        <div class="pt-form-actions"><button class="pt-btn pt-btn-primary">Salva</button></div>
    </form>
</div>

<section class="pt-card">
    <h2 class="pt-card-title">Progetti associati</h2>
    <?php if (!$this->assignments) : ?>
        <p class="pt-muted">Nessun progetto associato.</p>
    <?php else : ?>
        <div class="pt-table-wrap"><table class="pt-table pt-table-cards">
            <thead><tr><th>Progetto</th><th>CUP / GA</th><th class="num">Ore previste da rendicontare</th><th class="num">Monte ore annuo</th></tr></thead>
            <tbody><?php foreach ($this->assignments as $a) : ?>
                <tr><td data-label="Progetto"><strong><?= htmlspecialchars($a->acronimo) ?></strong></td><td data-label="CUP"><?= htmlspecialchars($a->cup) ?></td><td class="num" data-label="Ore previste"><?= $a->monteore_min !== null ? Time::format((int) $a->monteore_min) : '–' ?></td><td class="num" data-label="Monte ore annuo"><?= ($a->monte_annuo_min ?? null) !== null ? Time::hoursNumber((int) $a->monte_annuo_min) : '–' ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<script>
// la foto si carica appena scelta (controllo dei 10 MB prima dell'invio)
document.querySelector('[data-photo-input]')?.addEventListener('change', (e) => {
  const f = e.target.files[0];
  if (!f) return;
  if (f.size > 10 * 1024 * 1024) { Protis.toast('La foto supera i 10 MB.', 'error', 6000); e.target.value = ''; return; }
  const img = document.querySelector('.pt-photo');
  img.classList.remove('is-empty');
  img.innerHTML = `<img src="${URL.createObjectURL(f)}" alt="">`;
  e.target.form.submit();
});
</script>
<?php $this->partial('footer'); ?>
