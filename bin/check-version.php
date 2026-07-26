<?php
/**
 * Verifica la coerenza delle versioni del plugin.
 *
 * Controlla che:
 *   - l'header "Version:" del file principale
 *   - il "Stable tag:" di readme.txt
 *   - (opzionale) la versione attesa passata come argomento, es. dal tag git
 * coincidano, e che readme.txt contenga il blocco di changelog per quella versione.
 *
 * Uso:
 *   php bin/check-version.php              # coerenza interna
 *   php bin/check-version.php 1.0.13       # coerenza + confronto con la versione attesa
 *
 * Exit code 0 = tutto coerente, 1 = incoerenza.
 */

$root     = dirname(__DIR__);
$expected = isset($argv[1]) ? ltrim(trim($argv[1]), 'vV') : null;
$errors   = array();
$notes    = array();

/**
 * Trova il file principale del plugin (quello con l'header "Plugin Name:").
 */
function find_plugin_file($root) {
    foreach (glob($root . '/*.php') as $file) {
        $head = (string) file_get_contents($file, false, null, 0, 8192);
        if (strpos($head, 'Plugin Name:') !== false) {
            return $file;
        }
    }
    return null;
}

/**
 * Estrae un campo dell'header del plugin, gestendo sia " Version:" che " * Version:".
 */
function header_field($contents, $field) {
    $pattern = '/^[ \t\/*#@]*' . preg_quote($field, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $contents, $m)) {
        return trim($m[1]);
    }
    return null;
}

$plugin_file = find_plugin_file($root);
if ($plugin_file === null) {
    fwrite(STDERR, "ERRORE: nessun file con header 'Plugin Name:' trovato nella radice del repo.\n");
    exit(1);
}

$readme_file = $root . '/readme.txt';
if (!is_file($readme_file)) {
    fwrite(STDERR, "ERRORE: readme.txt non trovato.\n");
    exit(1);
}

$plugin_src = (string) file_get_contents($plugin_file);
$readme_src = (string) file_get_contents($readme_file);

$header_version = header_field($plugin_src, 'Version');
$stable_tag     = header_field($readme_src, 'Stable tag');
$requires_php   = header_field($readme_src, 'Requires PHP');
$tested_up_to   = header_field($readme_src, 'Tested up to');

if ($header_version === null) {
    $errors[] = "header 'Version:' non trovato in " . basename($plugin_file);
}
if ($stable_tag === null) {
    $errors[] = "'Stable tag:' non trovato in readme.txt";
}

if ($header_version !== null && $stable_tag !== null && $header_version !== $stable_tag) {
    $errors[] = sprintf(
        "disallineamento: %s ha Version: %s ma readme.txt ha Stable tag: %s",
        basename($plugin_file),
        $header_version,
        $stable_tag
    );
}

if ($expected !== null && $header_version !== null && $expected !== $header_version) {
    $errors[] = sprintf(
        "disallineamento: versione attesa %s ma %s dichiara Version: %s",
        $expected,
        basename($plugin_file),
        $header_version
    );
}

// Il changelog deve contenere un blocco per la versione che si sta rilasciando.
$version_to_check = $expected !== null ? $expected : $header_version;
if ($version_to_check !== null) {
    $changelog_pattern = '/^\s*=\s*v?' . preg_quote($version_to_check, '/') . '\s*=\s*$/m';
    if (!preg_match($changelog_pattern, $readme_src)) {
        $errors[] = sprintf("readme.txt non contiene il blocco di changelog '= %s ='", $version_to_check);
    }
}

if ($requires_php === null) {
    $notes[] = "readme.txt non dichiara 'Requires PHP': WordPress permettera' l'installazione su qualsiasi versione di PHP.";
}

echo "File principale : " . basename($plugin_file) . "\n";
echo "Version (php)   : " . ($header_version === null ? '(assente)' : $header_version) . "\n";
echo "Stable tag      : " . ($stable_tag === null ? '(assente)' : $stable_tag) . "\n";
echo "Tested up to    : " . ($tested_up_to === null ? '(assente)' : $tested_up_to) . "\n";
echo "Requires PHP    : " . ($requires_php === null ? '(assente)' : $requires_php) . "\n";
if ($expected !== null) {
    echo "Versione attesa : " . $expected . "\n";
}
echo "\n";

foreach ($notes as $note) {
    echo "AVVISO: " . $note . "\n";
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERRORE: " . $error . "\n");
    }
    exit(1);
}

echo "OK: versioni coerenti.\n";
exit(0);
