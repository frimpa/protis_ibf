<?php

/**
 * Scrivania PDF: firmare, annotare, unire e dividere PDF nel browser (i file non lasciano il dispositivo).
 *
 * @var \Cnr\Component\Protis\Site\View\Scrivania\HtmlView $this
 */

\defined('_JEXEC') or die;

$this->partial('header');
$src = $this->link('task=tools.scrivania');
?>
<div class="pt-page-head">
    <h1>Scrivania PDF</h1>
    <?php if ($this->available) : ?>
        <a class="pt-btn pt-btn-ghost pt-btn-sm" href="<?= $src ?>" target="_blank" rel="noopener">Apri a tutto schermo ↗</a>
    <?php endif; ?>
</div>

<?php if (!$this->available) : ?>
    <div class="pt-empty">La Scrivania PDF non è configurata. L'amministratore indica il file in Opzioni → Scrivania PDF.</div>
<?php else : ?>
    <p class="pt-help pt-scrivania-help">Firma, annota, copri, unisci e dividi PDF. I documenti restano sul tuo dispositivo: non vengono inviati al server.</p>
    <div class="pt-card pt-scrivania">
        <iframe src="<?= $src ?>" title="Scrivania PDF" loading="lazy" allow="clipboard-read; clipboard-write"></iframe>
    </div>
<?php endif; ?>
<?php $this->partial('footer');
