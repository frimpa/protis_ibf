<?php

/**
 * Template "app" di PROTIS IBF: solo messaggi di sistema + componente, a tutta larghezza.
 *
 * @copyright   (C) CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 *
 * @var \Joomla\CMS\Document\HtmlDocument $this
 */

\defined('_JEXEC') or die;

$this->setHtml5(true);
$app       = \Joomla\CMS\Factory::getApplication();
$isProtis  = $app->getInput()->getCmd('option') === 'com_protis';
$this->getWebAssetManager()->useScript('messages');

// pagine non PROTIS IBF (es. recupero password di com_users): stile Bootstrap di Joomla in una scheda centrata
if (!$isProtis) {
    $this->getWebAssetManager()->useStyle('bootstrap.css');
}
$this->setMetaData('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover');
$this->getWebAssetManager()->addInlineStyle(<<<'CSS'
html,body{margin:0;min-height:100%;background:#e9edf3}
@media (prefers-color-scheme:dark){html,body{background:#0b0e13}}
.protis-tpl{max-width:1440px;margin:0 auto;padding:12px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.protis-tpl #system-message-container{margin-bottom:8px}
.protis-tpl joomla-alert{display:block;margin:0 0 8px;padding:.75rem 1rem;border-radius:10px;background:#e7f0fb;color:#1d2433;border:1px solid #c9dbf2;font-size:.95rem}
.protis-tpl joomla-alert[type=danger]{background:#fde7e4;border-color:#f3c1ba;color:#8f2418}
.protis-tpl joomla-alert[type=warning]{background:#fff3d6;border-color:#f0d9a2;color:#7a4b00}
.protis-tpl joomla-alert[type=success]{background:#e3f5ec;border-color:#b8e2cb;color:#14653f}
.protis-tpl joomla-alert .joomla-alert--close{float:right;background:none;border:0;font-size:1.3rem;cursor:pointer;color:inherit}
.protis-tpl .alert-heading{display:none}
.pt-other{max-width:640px;margin:6vh auto;background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(10,30,60,.15);overflow:hidden}
.pt-other-head{display:flex;align-items:center;gap:.7rem;padding:1rem 1.4rem;background:#0b4f8a;color:#fff;text-decoration:none}
.pt-other-head img{width:40px;height:40px;border-radius:10px;background:#fff}
.pt-other-head strong{font-size:1.1rem;color:#fff}
.pt-other-body{padding:1.5rem 1.4rem;color:#1d2433}
.pt-other-body .btn-primary{background:#0b4f8a;border-color:#0b4f8a}
@media (max-width:760px){.protis-tpl{padding:0 12px}}
@media (display-mode:standalone){.protis-tpl{padding-top:env(safe-area-inset-top)}}
CSS);
?>
<!DOCTYPE html>
<html lang="<?= $this->language ?>" dir="<?= $this->direction ?>">
<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>
<body class="protis-tpl">
    <jdoc:include type="message" />
<?php if ($isProtis) : ?>
    <jdoc:include type="component" />
<?php else : ?>
    <div class="pt-other">
        <a class="pt-other-head" href="<?= \Joomla\CMS\Uri\Uri::root(true) ?>/index.php?option=com_protis">
            <img src="<?= \Joomla\CMS\Uri\Uri::root(true) ?>/media/com_protis/images/icons/icon-192.png" alt="">
            <strong>PROTIS IBF</strong>
        </a>
        <div class="pt-other-body"><jdoc:include type="component" /></div>
    </div>
<?php endif; ?>
</body>
</html>
