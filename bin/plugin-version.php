<?php
/**
 * Stampa la versione dichiarata nell'header del plugin, senza altro output.
 *
 * Serve alla CI per sapere quale versione sta per essere rilasciata:
 *   VERSION=$(php bin/plugin-version.php)
 *
 * Exit code 0 = versione trovata, 1 = non trovata.
 */

$root = dirname(__DIR__);

foreach (glob($root . '/*.php') as $file) {
    $head = (string) file_get_contents($file, false, null, 0, 8192);
    if (strpos($head, 'Plugin Name:') === false) {
        continue;
    }
    // Gestisce sia " Version:" che " * Version:".
    if (preg_match('/^[ \t]*(?:\*[ \t]*)?Version:[ \t]*(\d+(?:\.\d+)+)[ \t]*$/mi', $head, $m)) {
        echo $m[1];
        exit(0);
    }
}

fwrite(STDERR, "ERRORE: versione del plugin non trovata.\n");
exit(1);
