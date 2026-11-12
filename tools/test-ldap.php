<?php
/**
 * Test LDAP CNR per PROTIS IBF (da riga di comando sul server).
 *
 *   BIND_PW='***' USER_PW='***' php test-ldap.php ldaps://SERVER:636 "BIND_DN" "BASE_DN" nome.cognome [ignora-cert]
 *
 * Mostra: connessione, bind tecnico, attributi dell'utente (senza password), bind con la password dell'utente.
 */
if ($argc < 5) { fwrite(STDERR, "Uso: BIND_PW=... USER_PW=... php test-ldap.php URL BIND_DN BASE_DN uid [ignora-cert]\n"); exit(1); }
[$_, $url, $bindDn, $base, $uid] = $argv;
if (!extension_loaded('ldap')) { exit("ERRORE: estensione PHP ldap NON caricata (apt install php-ldap)\n"); }
echo "PHP " . PHP_VERSION . " - ldap ok\n";
if (!empty($argv[5])) { ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER); echo "certificato: non verificato\n"; }
ldap_set_option(null, LDAP_OPT_NETWORK_TIMEOUT, 10);
$c = ldap_connect($url) or exit("ERRORE: URL non valido\n");
ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($c, LDAP_OPT_REFERRALS, 0);
if (!@ldap_bind($c, $bindDn, getenv('BIND_PW') ?: null)) { exit("ERRORE bind tecnico: " . ldap_error($c) . " (" . ldap_errno($c) . ")\n"); }
echo "bind tecnico: OK\n";
$r = @ldap_search($c, $base, '(uid=' . ldap_escape($uid, '', LDAP_ESCAPE_FILTER) . ')');
$e = $r ? ldap_get_entries($c, $r) : ['count' => 0];
echo "utenti trovati: {$e['count']}\n";
if (!$e['count']) exit;
echo "DN: {$e[0]['dn']}\n";
for ($i = 0; $i < $e[0]['count']; $i++) {
    $a = $e[0][$i];
    if (stripos($a, 'password') !== false) continue;
    echo "  $a: " . implode(' | ', array_slice(array_filter($e[0][$a], 'is_string'), 0, 5)) . "\n";
}
if (getenv('USER_PW')) {
    echo "bind utente: " . (@ldap_bind($c, $e[0]['dn'], getenv('USER_PW')) ? "OK" : "FALLITO - " . ldap_error($c)) . "\n";
}
