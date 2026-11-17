<?php

/**
 * Riga a piè di pagina: nome dell'app, istituto, crediti.
 *
 * @var \Cnr\Component\Protis\Site\View\BaseHtmlView $this
 */

\defined('_JEXEC') or die;

$ist = trim((string) $this->cparams->get('istituto', ''));
?>
<footer class="pt-footer">
    <?= htmlspecialchars((string) $this->cparams->get('app_name', 'PROTIS IBF')) ?><?= $ist !== '' ? ' · ' . htmlspecialchars($ist) : '' ?>
    · Software PROTIS IBF © Ing. Francesco Impallari, CNR – Istituto di Biofisica (autore e responsabile del software)
    · <a href="https://github.com/frimpa/protis_ibf" target="_blank" rel="noopener">EUPL-1.2</a>
    <?php if (($priv = $this->privacyUrl()) !== '') : ?>· <a href="<?= htmlspecialchars($priv) ?>" target="_blank" rel="noopener">Informativa sul trattamento dei dati</a><?php endif; ?>
</footer>
