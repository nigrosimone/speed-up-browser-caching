<?php
/**
 * Esegue "php -l" su tutti i .php del plugin (esclusi vendor, node_modules e bin).
 *
 * Uso: php bin/lint.php
 *
 * Exit code 0 = nessun errore di sintassi, 1 = almeno un file non compila.
 */

$root = dirname(__DIR__);

// Vengono controllati solo i file che finiscono nel pacchetto pubblicato:
// tests/ e bin/ girano sul runner, non sull'installazione dell'utente, e possono
// usare sintassi piu' recente della versione minima supportata dal plugin.
$skip = array('vendor', 'node_modules', '.git', '.github', 'tests', 'bin');
$errors  = 0;
$checked = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        function ($current) use ($skip) {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $skip, true);
            }
            return substr($current->getFilename(), -4) === '.php';
        }
    )
);

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $out  = array();
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    $checked++;
    if ($code !== 0) {
        $errors++;
        echo implode("\n", $out) . "\n";
    }
}

printf("%d file controllati, %d con errori di sintassi (PHP %s)\n", $checked, $errors, PHP_VERSION);
exit($errors === 0 ? 0 : 1);
