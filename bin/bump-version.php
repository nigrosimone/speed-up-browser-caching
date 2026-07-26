<?php
/**
 * Prepara una nuova versione del plugin.
 *
 * Porting in PHP della logica di release-plugin.ps1, cosi' da poter girare
 * sul runner GitHub. Aggiorna:
 *   - readme.txt : "Stable tag", "Tested up to" e nuovo blocco di changelog
 *   - {slug}.php : header "Version:" (gestisce sia " Version:" che " * Version:")
 *   - {slug}.php : eventuale costante VERSION, solo se il valore coincide con la versione precedente
 *
 * I file vengono riscritti in UTF-8 senza BOM e con fine riga LF.
 *
 * Uso:
 *   php bin/bump-version.php --version=1.0.13 --changelog="Fix X;Fix Y" [--tested-up-to=7.0] [--dry-run]
 *
 * Exit code 0 = ok, 1 = errore.
 */

$root = dirname(__DIR__);

// ---------------------------------------------------------------------------
// Argomenti
// ---------------------------------------------------------------------------
$options = getopt('', array('version:', 'tested-up-to::', 'changelog::', 'dry-run'));

if (!isset($options['version'])) {
    fwrite(STDERR, "ERRORE: --version e' obbligatorio.\n");
    fwrite(STDERR, "Uso: php bin/bump-version.php --version=1.0.13 --changelog=\"voce 1;voce 2\"\n");
    exit(1);
}

$new_version = ltrim(trim($options['version']), 'vV');
$dry_run     = isset($options['dry-run']);
$tested_up_to = isset($options['tested-up-to']) ? trim($options['tested-up-to']) : '';
$changelog_in = isset($options['changelog']) ? trim($options['changelog']) : '';

if (!preg_match('/^\d+\.\d+\.\d+(\.\d+)?$/', $new_version)) {
    fwrite(STDERR, "ERRORE: versione non valida '$new_version' (attesa X.Y.Z).\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------
function read_lf($path) {
    $raw = (string) file_get_contents($path);
    // Rimuove un eventuale BOM e normalizza le fine riga.
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    return str_replace(array("\r\n", "\r"), "\n", $raw);
}

function write_lf($path, $contents, $dry_run) {
    if ($dry_run) {
        return;
    }
    file_put_contents($path, $contents);
}

function find_plugin_file($root) {
    foreach (glob($root . '/*.php') as $file) {
        $head = (string) file_get_contents($file, false, null, 0, 8192);
        if (strpos($head, 'Plugin Name:') !== false) {
            return $file;
        }
    }
    return null;
}

$plugin_file = find_plugin_file($root);
if ($plugin_file === null) {
    fwrite(STDERR, "ERRORE: file principale del plugin non trovato.\n");
    exit(1);
}
$readme_file = $root . '/readme.txt';
if (!is_file($readme_file)) {
    fwrite(STDERR, "ERRORE: readme.txt non trovato.\n");
    exit(1);
}

$plugin_src = read_lf($plugin_file);
$readme_src = read_lf($readme_file);

// ---------------------------------------------------------------------------
// Versione corrente e validazioni
// ---------------------------------------------------------------------------
// Cattura separatamente il prefisso della riga per preservarne lo stile
// (" Version:" oppure " * Version:").
if (!preg_match('/^([ \t]*(?:\*[ \t]*)?Version:[ \t]*)(\d+(?:\.\d+)+)[ \t]*$/mi', $plugin_src, $m)) {
    fwrite(STDERR, "ERRORE: header 'Version:' non trovato in " . basename($plugin_file) . ".\n");
    exit(1);
}
$old_version = $m[2];

if (version_compare($new_version, $old_version, '<=')) {
    fwrite(STDERR, "ERRORE: la nuova versione ($new_version) deve essere maggiore di quella attuale ($old_version).\n");
    exit(1);
}

if (preg_match('/^\s*=\s*v?' . preg_quote($new_version, '/') . '\s*=\s*$/m', $readme_src)) {
    fwrite(STDERR, "ERRORE: readme.txt contiene gia' un blocco di changelog per $new_version.\n");
    exit(1);
}

// "Tested up to" corrente: se non passato, resta invariato.
$current_tested = '';
if (preg_match('/^Tested up to:\s*(.+?)\s*$/mi', $readme_src, $tm)) {
    $current_tested = trim($tm[1]);
}
if ($tested_up_to === '') {
    $tested_up_to = $current_tested;
}

// Voci di changelog.
$bullets = array();
foreach (explode(';', $changelog_in) as $bullet) {
    $bullet = trim(preg_replace('/^\s*\*\s*/', '', $bullet));
    if ($bullet !== '') {
        $bullets[] = $bullet;
    }
}
if (empty($bullets)) {
    $bullets[] = 'Tested up to Wordpress ' . $tested_up_to;
}

$changes = array();

// ---------------------------------------------------------------------------
// readme.txt : Stable tag / Tested up to
// ---------------------------------------------------------------------------
$readme_new = preg_replace_callback(
    '/^(Stable tag:\s*)(.*)$/mi',
    function ($matches) use ($new_version, &$changes) {
        $changes[] = 'readme.txt : Stable tag ' . trim($matches[2]) . ' -> ' . $new_version;
        return $matches[1] . $new_version;
    },
    $readme_src,
    1
);

if ($tested_up_to !== '' && $tested_up_to !== $current_tested) {
    $readme_new = preg_replace_callback(
        '/^(Tested up to:\s*)(.*)$/mi',
        function ($matches) use ($tested_up_to, &$changes) {
            $changes[] = 'readme.txt : Tested up to ' . trim($matches[2]) . ' -> ' . $tested_up_to;
            return $matches[1] . $tested_up_to;
        },
        $readme_new,
        1
    );
}

// ---------------------------------------------------------------------------
// readme.txt : nuovo blocco di changelog subito dopo "== Changelog =="
// ---------------------------------------------------------------------------
if (!preg_match('/^==\s*Changelog\s*==\s*$/mi', $readme_new)) {
    fwrite(STDERR, "ERRORE: sezione '== Changelog ==' non trovata in readme.txt.\n");
    exit(1);
}

$block = "= $new_version =\n";
foreach ($bullets as $bullet) {
    $block .= "* $bullet\n";
}

$readme_new = preg_replace_callback(
    '/^(==\s*Changelog\s*==[ \t]*\n)(\n*)/mi',
    function ($matches) use ($block) {
        // Una riga vuota tra l'intestazione e il nuovo blocco, e una dopo il blocco.
        return $matches[1] . "\n" . $block . "\n";
    },
    $readme_new,
    1
);
$changes[] = 'readme.txt : nuovo blocco changelog "= ' . $new_version . ' =" (' . count($bullets) . ' voce/i)';

// ---------------------------------------------------------------------------
// {slug}.php : header Version
// ---------------------------------------------------------------------------
$plugin_new = preg_replace_callback(
    '/^([ \t]*(?:\*[ \t]*)?Version:[ \t]*)(\d+(?:\.\d+)+)[ \t]*$/mi',
    function ($matches) use ($new_version, &$changes) {
        $changes[] = 'php : Version: ' . $matches[2] . ' -> ' . $new_version;
        return $matches[1] . $new_version;
    },
    $plugin_src,
    1
);

// ---------------------------------------------------------------------------
// {slug}.php : costante VERSION, solo se vale esattamente la versione precedente
// ---------------------------------------------------------------------------
$plugin_new = preg_replace_callback(
    '/^(.*(?:VERSION|define\s*\().*)$/m',
    function ($matches) use ($old_version, $new_version, &$changes) {
        $line    = $matches[1];
        $updated = preg_replace(
            '/([\'"])' . preg_quote($old_version, '/') . '([\'"])/',
            '${1}' . $new_version . '${2}',
            $line
        );
        if ($updated !== $line) {
            $changes[] = 'php : ' . trim($line) . ' -> ' . trim($updated);
        }
        return $updated;
    },
    $plugin_new
);

// ---------------------------------------------------------------------------
// Riepilogo e scrittura
// ---------------------------------------------------------------------------
echo "Plugin       : " . basename($plugin_file) . "\n";
echo "Versione     : $old_version -> $new_version\n";
echo "Tested up to : " . ($current_tested === '' ? '(assente)' : $current_tested) . " -> $tested_up_to\n";
echo "Changelog    :\n";
foreach ($bullets as $bullet) {
    echo "               * $bullet\n";
}
echo "\nModifiche:\n";
foreach ($changes as $change) {
    echo "  - $change\n";
}

if ($dry_run) {
    echo "\n[DRY-RUN] Nessun file scritto.\n";
    exit(0);
}

write_lf($readme_file, $readme_new, $dry_run);
write_lf($plugin_file, $plugin_new, $dry_run);

echo "\nFile aggiornati.\n";
exit(0);
