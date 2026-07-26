<?php
/**
 * Punta git alla cartella .githooks/ di questo repository.
 *
 * Viene lanciato da "composer install" e "composer update": chi clona il repo e
 * installa le dipendenze si ritrova l'hook attivo senza doverlo sapere.
 *
 * Scritto in PHP e non in shell perche' deve funzionare uguale su Windows, dove
 * lo script di composer non gira necessariamente in un ambiente POSIX.
 *
 * Non fallisce mai: se non siamo in un repository git (per esempio nel pacchetto
 * pubblicato) esce silenziosamente, per non rompere l'installazione.
 */

$root = dirname(__DIR__);

if (!is_dir($root . '/.git') && !is_file($root . '/.git')) {
    // Non e' un working tree git: niente da fare.
    exit(0);
}

if (!is_dir($root . '/.githooks')) {
    exit(0);
}

/**
 * Imposta una configurazione git locale, senza mai far fallire composer install.
 */
function git_config($root, $key, $value) {
    $output = array();
    $code   = 0;
    exec(
        'git -C ' . escapeshellarg($root) . ' config ' . escapeshellarg($key) . ' ' . escapeshellarg($value) . ' 2>&1',
        $output,
        $code
    );
    if ($code !== 0) {
        echo "Nota: non e' stato possibile impostare $key: " . implode(' ', $output) . "\n";
        return false;
    }
    return true;
}

if (git_config($root, 'core.hooksPath', '.githooks')) {
    echo "Hook git attivi (core.hooksPath = .githooks). Per saltarli: git commit --no-verify\n";
}

// Fa saltare a "git blame" i commit puramente meccanici. GitHub lo fa gia' da solo
// leggendo il file; questo serve a ottenere lo stesso risultato in locale.
if (is_file($root . '/.git-blame-ignore-revs')) {
    git_config($root, 'blame.ignoreRevsFile', '.git-blame-ignore-revs');
}

exit(0);
