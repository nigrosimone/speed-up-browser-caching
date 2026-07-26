<?php

use PHPUnit\Framework\TestCase;

/**
 * Test della logica di manipolazione del .htaccess.
 *
 * Ogni test lavora su una sandbox reale su filesystem (SPEEDUP_TEST_ROOT):
 * il plugin scrive davvero i file, quindi verifichiamo il comportamento
 * effettivo e non un mock.
 */
class BrowserCachingTest extends TestCase {

    /** Contenuto .htaccess tipico di una installazione WordPress. */
    private const WP_HTACCESS = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\n</IfModule>\n# END WordPress\n";

    private const SECTION_START = '# BEGIN SpeedUp_BrowserCaching';
    private const SECTION_END   = '# END SpeedUp_BrowserCaching';

    protected function setUp(): void {
        parent::setUp();
        $this->cleanSandbox();
    }

    protected function tearDown(): void {
        $this->cleanSandbox();
        parent::tearDown();
    }

    /** Svuota la sandbox tra un test e l'altro. */
    private function cleanSandbox() {
        foreach (glob(SPEEDUP_TEST_ROOT . '*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob(SPEEDUP_TEST_ROOT . '.htaccess') as $file) {
            unlink($file);
        }
    }

    /** Crea un .htaccess nella sandbox e ne restituisce il percorso. */
    private function givenWordPressHtaccess($contents = self::WP_HTACCESS) {
        $path = SPEEDUP_TEST_ROOT . '.htaccess';
        file_put_contents($path, $contents);
        return $path;
    }

    private function htaccessContents() {
        return (string) file_get_contents(SPEEDUP_TEST_ROOT . '.htaccess');
    }

    // -----------------------------------------------------------------------
    // Struttura del plugin
    // -----------------------------------------------------------------------

    public function test_get_instance_restituisce_sempre_la_stessa_istanza() {
        $first  = SpeedUp_BrowserCaching::get_instance();
        $second = SpeedUp_BrowserCaching::get_instance();

        $this->assertInstanceOf('SpeedUp_BrowserCaching', $first);
        $this->assertSame($first, $second, 'get_instance() deve implementare il singleton.');
    }

    public function test_registra_gli_hook_di_attivazione_e_disattivazione() {
        $hooks = $GLOBALS['speedup_registered_hooks'];

        $this->assertCount(1, $hooks['activation'], 'Deve registrare un hook di attivazione.');
        $this->assertCount(1, $hooks['deactivation'], 'Deve registrare un hook di disattivazione.');

        $this->assertSame(
            array('SpeedUp_BrowserCaching', 'install'),
            $hooks['activation'][0][1]
        );
        $this->assertSame(
            array('SpeedUp_BrowserCaching', 'uninstall'),
            $hooks['deactivation'][0][1]
        );
    }

    public function test_il_template_htaccess_del_plugin_esiste_ed_e_leggibile() {
        $template = dirname(__DIR__) . '/htaccess.txt';

        $this->assertFileExists($template);
        $this->assertNotEmpty(trim((string) file_get_contents($template)));
    }

    // -----------------------------------------------------------------------
    // Attivazione: aggiunta della sezione
    // -----------------------------------------------------------------------

    public function test_add_htaccess_rule_inserisce_la_sezione_del_plugin() {
        $this->givenWordPressHtaccess();

        $this->assertTrue(SpeedUp_BrowserCaching::add_htaccess_rule());

        $contents = $this->htaccessContents();
        $this->assertStringContainsString(self::SECTION_START, $contents);
        $this->assertStringContainsString(self::SECTION_END, $contents);
    }

    public function test_add_htaccess_rule_preserva_le_direttive_wordpress_esistenti() {
        $this->givenWordPressHtaccess();

        SpeedUp_BrowserCaching::add_htaccess_rule();

        $contents = $this->htaccessContents();
        $this->assertStringContainsString('# BEGIN WordPress', $contents);
        $this->assertStringContainsString('RewriteRule ^index\\.php$ - [L]', $contents);
        $this->assertStringContainsString('# END WordPress', $contents);
    }

    public function test_add_htaccess_rule_mette_la_sezione_prima_di_quella_wordpress() {
        $this->givenWordPressHtaccess();

        SpeedUp_BrowserCaching::add_htaccess_rule();

        $contents = $this->htaccessContents();
        $this->assertLessThan(
            strpos($contents, '# BEGIN WordPress'),
            strpos($contents, self::SECTION_START),
            'Le direttive di caching devono precedere il blocco WordPress.'
        );
    }

    public function test_add_htaccess_rule_copia_il_contenuto_del_template() {
        $this->givenWordPressHtaccess();

        SpeedUp_BrowserCaching::add_htaccess_rule();

        $template  = (string) file_get_contents(dirname(__DIR__) . '/htaccess.txt');
        $contents  = $this->htaccessContents();
        $first_row = trim(strtok($template, "\n"));

        $this->assertNotEmpty($first_row);
        $this->assertStringContainsString($first_row, $contents);
    }

    public function test_add_htaccess_rule_crea_un_backup() {
        $this->givenWordPressHtaccess();

        SpeedUp_BrowserCaching::add_htaccess_rule();

        $backups = glob(SPEEDUP_TEST_ROOT . 'speed-up-backup-*.htaccess');
        $this->assertNotEmpty($backups, 'Deve essere creato un backup del .htaccess originale.');
        $this->assertSame(self::WP_HTACCESS, (string) file_get_contents($backups[0]));
    }

    public function test_add_htaccess_rule_fallisce_senza_htaccess() {
        // Nessun .htaccess nella sandbox.
        $this->assertFalse(SpeedUp_BrowserCaching::add_htaccess_rule());
    }

    // -----------------------------------------------------------------------
    // Disattivazione: rimozione della sezione
    // -----------------------------------------------------------------------

    public function test_remove_htaccess_rule_elimina_la_sezione_del_plugin() {
        $this->givenWordPressHtaccess();
        SpeedUp_BrowserCaching::add_htaccess_rule();

        $this->assertTrue(SpeedUp_BrowserCaching::remove_htaccess_rule());

        $contents = $this->htaccessContents();
        $this->assertStringNotContainsString(self::SECTION_START, $contents);
        $this->assertStringNotContainsString(self::SECTION_END, $contents);
    }

    public function test_remove_htaccess_rule_preserva_le_direttive_wordpress() {
        $this->givenWordPressHtaccess();
        SpeedUp_BrowserCaching::add_htaccess_rule();

        SpeedUp_BrowserCaching::remove_htaccess_rule();

        $contents = $this->htaccessContents();
        $this->assertStringContainsString('# BEGIN WordPress', $contents);
        $this->assertStringContainsString('RewriteRule ^index\\.php$ - [L]', $contents);
        $this->assertStringContainsString('# END WordPress', $contents);
    }

    /**
     * Percorso di uscita anticipata: la sezione del plugin non c'e'.
     *
     * E' il ramo in cui mancava la chiusura del file handle: si usciva con
     * "return false" saltando la fclose() che stava dopo il blocco if.
     */
    public function test_remove_htaccess_rule_non_danneggia_un_htaccess_senza_la_sezione() {
        $this->givenWordPressHtaccess();

        $this->assertFalse(
            SpeedUp_BrowserCaching::remove_htaccess_rule(),
            'Senza la sezione del plugin non c\'e\' nulla da rimuovere.'
        );

        $this->assertSame(
            self::WP_HTACCESS,
            $this->htaccessContents(),
            'Il .htaccess dell\'utente deve restare intatto.'
        );
    }

    /**
     * Il nome del file di backup deve basarsi su UTC e non sul fuso del server.
     *
     * Il test forza un fuso a UTC+14: con date() lo scarto sarebbe di 14 ore,
     * quindi la differenza rispetto a time() rende il difetto inequivocabile
     * anche su un runner che gira gia' in UTC.
     */
    public function test_il_nome_del_backup_usa_un_timestamp_utc() {
        $original_timezone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Kiritimati');

        try {
            $this->givenWordPressHtaccess();
            SpeedUp_BrowserCaching::add_htaccess_rule();

            $backups = glob(SPEEDUP_TEST_ROOT . 'speed-up-backup-*.htaccess');
            $this->assertNotEmpty($backups);

            $matched = preg_match(
                '/speed-up-backup-(\d{4}-\d{2}-\d{2}_\d{6})\.htaccess$/',
                basename($backups[0]),
                $parts
            );
            $this->assertSame(1, $matched, 'Nome del backup nel formato atteso.');

            $stamp = DateTime::createFromFormat('Y-m-d_His', $parts[1], new DateTimeZone('UTC'));
            $this->assertInstanceOf('DateTime', $stamp);

            $this->assertLessThan(
                120,
                abs($stamp->getTimestamp() - time()),
                'Il timestamp del backup deve essere UTC, non l\'ora locale del server.'
            );
        } finally {
            date_default_timezone_set($original_timezone);
        }
    }

    public function test_ciclo_attiva_disattiva_non_lascia_direttive_di_caching() {
        $this->givenWordPressHtaccess();

        SpeedUp_BrowserCaching::add_htaccess_rule();
        SpeedUp_BrowserCaching::remove_htaccess_rule();

        $template   = (string) file_get_contents(dirname(__DIR__) . '/htaccess.txt');
        $first_row  = trim(strtok($template, "\n"));
        $contents   = $this->htaccessContents();

        $this->assertStringNotContainsString($first_row, $contents);
        // Le direttive originali devono restare, a meno di righe vuote.
        $this->assertSame(
            preg_replace('/\n{2,}/', "\n", trim(self::WP_HTACCESS)),
            preg_replace('/\n{2,}/', "\n", trim($contents))
        );
    }
}
