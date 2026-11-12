<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Plugin\Authentication\ProtisLdap\Extension;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\PeopleService;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Event\User\AuthenticationEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Ldap;

/**
 * Autenticazione con LDAP CNR, stessa logica della V.2 (functions.php del tema WordPress):
 *  1. bind con l'utente tecnico
 *  2. ricerca per uid e lettura attributi CNR (matricola, cnrextra4, cnrgruppo3, departmentnumber…)
 *  3. verifica che il departmentnumber sia una sede PROTIS IBF attiva
 *  4. bind con le credenziali dell'utente
 *  5. aggiornamento anagrafica PROTIS IBF
 */
final class ProtisLdap extends CMSPlugin implements SubscriberInterface
{
    private const ATTRS = ['uid', 'mail', 'cnrnome', 'cnrcognome', 'matricola', 'cnrextra4', 'cnrgruppo3', 'departmentnumber', 'codicefiscale'];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onUserAuthenticate' => 'onUserAuthenticate'];
    }

    public function onUserAuthenticate(AuthenticationEvent $event): void
    {
        $credentials = $event->getCredentials();
        $response    = $event->getAuthenticationResponse();
        $response->type = 'ProtisLdap';

        $host     = trim((string) $this->params->get('host', ''));
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = str_replace(\chr(0), '', (string) ($credentials['password'] ?? ''));

        if ($host === '' || $username === '' || $password === '') {
            return;
        }

        // log dedicato: administrator/logs/protis-ldap.php
        Log::addLogger(['text_file' => 'protis-ldap.php'], $this->params->get('debug') ? Log::ALL : (Log::WARNING | Log::ERROR | Log::CRITICAL), ['protis-ldap']);

        if (!\extension_loaded('ldap')) {
            Log::add('Estensione PHP ldap non installata (apt install php-ldap e riavviare Apache)', Log::ERROR, 'protis-ldap');
            $response->status        = Authentication::STATUS_FAILURE;
            $response->error_message = 'Accesso CNR non disponibile: estensione PHP ldap mancante sul server.';

            return;
        }

        // "server" senza schema → ldaps://; porta separata opzionale
        if (!preg_match('#^ldaps?://#i', $host)) {
            $host = 'ldaps://' . $host;
        }

        $port = (int) $this->params->get('port', 0);

        if ($port && !preg_match('#:\d+/?$#', $host)) {
            $host = rtrim($host, '/') . ':' . $port;
        }

        try {
            $options = ['connection_string' => $host, 'version' => 3, 'referrals' => false, 'options' => ['network_timeout' => 10]];

            if ($this->params->get('ignore_cert')) {
                // l'opzione TLS va impostata a livello globale prima della connessione
                ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            }

            Log::add('Connessione a ' . $host . ' per ' . $username, Log::DEBUG, 'protis-ldap');

            $ldap = Ldap::create('ext_ldap', $options);
            $bindDn = (string) $this->params->get('bind_dn', '');
            $ldap->bind($bindDn !== '' ? $bindDn : null, $bindDn !== '' ? (string) $this->params->get('bind_password', '') : null);

            $uidAttr = preg_replace('/[^a-zA-Z0-9]/', '', (string) $this->params->get('uid_attr', 'uid')) ?: 'uid';
            $filter  = '(' . $uidAttr . '=' . $ldap->escape($username, '', LDAP_ESCAPE_FILTER) . ')';
            $entries = $ldap->query((string) $this->params->get('base_dn', 'o=cnr,c=it'), $filter, ['filter' => self::ATTRS, 'maxItems' => 2])->execute()->toArray();
        } catch (ConnectionException | LdapException $e) {
            Log::add('Errore LDAP (' . $host . '): ' . $e->getMessage(), Log::WARNING, 'protis-ldap');
            $response->status        = Authentication::STATUS_FAILURE;
            $response->error_message = $this->getApplication()->getLanguage()->_('PLG_PROTISLDAP_ERR_CONNECT');

            return;
        }

        if (\count($entries) !== 1) {
            Log::add('Utente ' . $username . ' non trovato in LDAP (risultati: ' . \count($entries) . ')', Log::DEBUG, 'protis-ldap');

            // utente non presente in LDAP: lascia lavorare gli altri plugin (account locali Joomla)
            return;
        }

        $entry = $entries[0];
        $attr  = [];

        foreach (self::ATTRS as $a) {
            $v        = $entry->getAttribute($a, false) ?? [];
            $attr[$a] = isset($v[0]) ? trim((string) $v[0]) : '';
        }

        Log::add('Trovato ' . $entry->getDn() . ' sede=' . $attr['departmentnumber'] . ' matricola=' . $attr['matricola'], Log::DEBUG, 'protis-ldap');

        $externalOk = class_exists(PeopleService::class) && (new PeopleService())->isEnabledExternal($attr['uid'] !== '' ? $attr['uid'] : $username);

        if ($externalOk) {
            Log::add($username . ': collega di altro istituto abilitato per UID', Log::DEBUG, 'protis-ldap');
        }

        if ($this->params->get('check_site', 1) && !$externalOk && !$this->siteAllowed($attr['departmentnumber'])) {
            Log::add('Accesso negato a ' . $username . ': sede "' . $attr['departmentnumber'] . '" non abilitata in PROTIS IBF → Impostazioni', Log::WARNING, 'protis-ldap');
            $response->status        = Authentication::STATUS_FAILURE;
            $response->error_message = $this->getApplication()->getLanguage()->_('PLG_PROTISLDAP_ERR_SITE');

            return;
        }

        try {
            $ldap->bind($entry->getDn(), $password);
        } catch (ConnectionException | LdapException) {
            Log::add('Password errata per ' . $username, Log::INFO, 'protis-ldap');
            $response->status        = Authentication::STATUS_FAILURE;
            $response->error_message = $this->getApplication()->getLanguage()->_('PLG_PROTISLDAP_ERR_CREDENTIALS');

            return;
        }

        $attr['uid'] = $attr['uid'] !== '' ? $attr['uid'] : $username;

        $response->status        = Authentication::STATUS_SUCCESS;
        $response->error_message = '';
        $response->username      = $attr['uid'];
        $response->email         = $attr['mail'] !== '' ? $attr['mail'] : $attr['uid'] . '@cnr.it';
        $response->fullname      = trim(ucwords(mb_strtolower($attr['cnrnome'] . ' ' . $attr['cnrcognome']))) ?: $attr['uid'];
        Log::add('Accesso riuscito: ' . $attr['uid'], Log::INFO, 'protis-ldap');
        $event->stopPropagation();

        try {
            if (class_exists(PeopleService::class)) {
                (new PeopleService())->upsertFromLdap($attr);
            }
        } catch (\Throwable $e) {
            Log::add('PROTIS IBF: aggiornamento anagrafica fallito: ' . $e->getMessage(), Log::WARNING, 'protis');
        }
    }

    private function siteAllowed(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return (int) $db->setQuery(
            $db->createQuery()->select('COUNT(*)')->from('#__protis_sites')
                ->where('published = 1')->where('epas_only = 0')->where('codice_sede = ' . $db->quote($code))
        )->loadResult() > 0;
    }
}
