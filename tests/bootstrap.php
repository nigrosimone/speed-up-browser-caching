<?php
/**
 * Bootstrap dei test.
 *
 * Il plugin usa di WordPress solo la costante ABSPATH e le due funzioni
 * register_activation_hook / register_deactivation_hook: non serve caricare
 * l'intero core ne' una libreria di mocking. Qui definiamo una sandbox su
 * filesystem che fa da "root di WordPress" e due stub che registrano le chiamate.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Sandbox usata come root di WordPress per tutta la suite.
define('SPEEDUP_TEST_ROOT', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'speed-up-bc-tests' . DIRECTORY_SEPARATOR);

if (!is_dir(SPEEDUP_TEST_ROOT)) {
    mkdir(SPEEDUP_TEST_ROOT, 0777, true);
}

// ABSPATH in WordPress termina sempre con uno slash.
define('ABSPATH', SPEEDUP_TEST_ROOT);

/**
 * Hook registrati dal plugin, popolati dagli stub qui sotto.
 *
 * @var array<string, array<int, array>>
 */
$GLOBALS['speedup_registered_hooks'] = array(
    'activation'   => array(),
    'deactivation' => array(),
);

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        $GLOBALS['speedup_registered_hooks']['activation'][] = array($file, $callback);
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        $GLOBALS['speedup_registered_hooks']['deactivation'][] = array($file, $callback);
    }
}

// Carica il plugin: in fondo al file c'e' SpeedUp_BrowserCaching::get_instance(),
// che fa scattare la registrazione degli hook sugli stub appena definiti.
require_once dirname(__DIR__) . '/speed-up-browser-caching.php';
