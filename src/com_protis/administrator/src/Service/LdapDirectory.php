<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Symfony\Component\Ldap\Ldap;

/**
 * Ricerca anagrafica nell'LDAP CNR con i parametri del plugin "Autenticazione - LDAP CNR (PROTIS IBF)".
 */
final class LdapDirectory
{
    public const ATTRS = ['uid', 'mail', 'cnrnome', 'cnrcognome', 'matricola', 'cnrextra4', 'cnrgruppo3', 'departmentnumber', 'codicefiscale'];

    public static function params(): ?Registry
    {
        $plugin = PluginHelper::getPlugin('authentication', 'protisldap');

        return $plugin ? new Registry($plugin->params) : null;
    }

    public static function connect(Registry $p): Ldap
    {
        $host = trim((string) $p->get('host', ''));

        if (!preg_match('#^ldaps?://#i', $host)) {
            $host = 'ldaps://' . $host;
        }

        $port = (int) $p->get('port', 0);

        if ($port && !preg_match('#:\d+/?$#', $host)) {
            $host = rtrim($host, '/') . ':' . $port;
        }

        if ($p->get('ignore_cert')) {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }

        $ldap   = Ldap::create('ext_ldap', ['connection_string' => $host, 'version' => 3, 'referrals' => false, 'options' => ['network_timeout' => 10]]);
        $bindDn = (string) $p->get('bind_dn', '');
        $ldap->bind($bindDn !== '' ? $bindDn : null, $bindDn !== '' ? (string) $p->get('bind_password', '') : null);

        return $ldap;
    }

    /** @return array<string,string>|null attributi CNR della persona, null se non trovata o LDAP non disponibile */
    public static function lookup(string $uid): ?array
    {
        $p = self::params();

        if (!$p || trim((string) $p->get('host', '')) === '' || !\extension_loaded('ldap')) {
            return null;
        }

        try {
            $ldap    = self::connect($p);
            $attr    = preg_replace('/[^a-zA-Z0-9]/', '', (string) $p->get('uid_attr', 'uid')) ?: 'uid';
            $entries = $ldap->query((string) $p->get('base_dn', 'o=cnr,c=it'), '(' . $attr . '=' . $ldap->escape($uid, '', LDAP_ESCAPE_FILTER) . ')', ['filter' => self::ATTRS, 'maxItems' => 2])
                ->execute()->toArray();
        } catch (\Throwable) {
            return null;
        }

        if (\count($entries) !== 1) {
            return null;
        }

        $out = ['dn' => $entries[0]->getDn()];

        foreach (self::ATTRS as $a) {
            $v       = $entries[0]->getAttribute($a, false) ?? [];
            $out[$a] = isset($v[0]) ? trim((string) $v[0]) : '';
        }

        return $out;
    }
}
