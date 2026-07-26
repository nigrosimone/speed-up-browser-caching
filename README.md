# Speed Up - Browser Caching

[![CI](https://github.com/nigrosimone/speed-up-browser-caching/actions/workflows/ci.yml/badge.svg)](https://github.com/nigrosimone/speed-up-browser-caching/actions/workflows/ci.yml)
[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/speed-up-browser-caching.svg)](https://wordpress.org/plugins/speed-up-browser-caching/)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/speed-up-browser-caching.svg)](https://wordpress.org/plugins/speed-up-browser-caching/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Abilita il browser caching su web server Apache: aggiunge al `.htaccess` di WordPress le
direttive che permettono al browser di tenere in cache una copia locale dei file statici,
riducendo i tempi di caricamento.

Non richiede configurazione: si installa, si attiva, e fa tutto da solo.

**Pagina su WordPress.org:** https://wordpress.org/plugins/speed-up-browser-caching/

## Come funziona lo sviluppo

Questo repository e' la **sorgente di verita'**. Il repository SVN su
`plugins.svn.wordpress.org` e' solo il **canale di pubblicazione**: viene scritto
automaticamente dalla CI e non va mai modificato a mano.

```
                    ┌──────────────────────┐
   Pull Request ───▶│  GitHub (main)       │──── tag vX.Y.Z ───┐
                    │  sorgente di verita' │                   │
                    └──────────────────────┘                   ▼
                                                    ┌────────────────────────┐
                                                    │ SVN wordpress.org      │
                                                    │ trunk/ + tags/X.Y.Z    │
                                                    └────────────────────────┘
```

Un workflow settimanale ([`svn-drift.yml`](.github/workflows/svn-drift.yml)) confronta il
trunk pubblicato con `main` e apre una issue se divergono, cosi' eventuali modifiche fatte
direttamente su SVN (per esempio una fix del team review di WordPress.org) non passano
inosservate.

## Rilasciare una nuova versione

Tutto dall'interfaccia di GitHub, senza toccare la macchina locale e senza avere SVN installato:

1. **Actions** → **Prepara rilascio** → **Run workflow**, indicando versione, `Tested up to`
   e le voci di changelog (separate da `;`).
2. Il workflow aggiorna `readme.txt` e l'header del plugin e apre una Pull Request.
3. Si controlla il diff e si fa **merge**.
4. Il merge fa scattare [`release.yml`](.github/workflows/release.yml), che ri-esegue tutti i
   controlli, crea il tag `vX.Y.Z`, pubblica su wordpress.org e crea la Release su GitHub.

Se qualcosa non va prima del punto 3, basta chiudere la PR: non e' stato pubblicato nulla.

## Sviluppo in locale

```bash
composer install

composer test           # PHPUnit
composer lint           # php -l su tutti i file pubblicati
composer compat         # compatibilita PHP 7.0+
composer check-version  # header, Stable tag e changelog coerenti
composer phpcs          # WordPress Coding Standards
composer phpcbf         # correzione automatica dello stile
```

## Cosa controlla la CI

| Controllo | Blocca la PR | Note |
|---|---|---|
| `php -l` su PHP 7.2 → 8.4 | Si | Solo i file effettivamente pubblicati |
| PHPCompatibilityWP 7.0+ | Si | Analisi statica, copre anche 7.0 e 7.1 |
| PHPUnit su PHP 7.4 e 8.3 | Si | |
| Coerenza versioni | Si | `Version:` == `Stable tag` + changelog presente |
| WordPress Coding Standards | No | Debito storico, da smaltire progressivamente |
| WordPress Plugin Check | No | Gli stessi controlli del team review di WordPress.org |

I due controlli non bloccanti diventeranno bloccanti quando il conteggio delle violazioni
arrivera' a zero.

## Struttura del repository

```
speed-up-browser-caching.php   codice del plugin
htaccess.txt                   direttive inserite nel .htaccess dell'utente
readme.txt                     readme in formato WordPress.org (fonte del changelog)
index.html                     file vuoto anti directory-listing
.wordpress-org/                banner e icona della pagina wordpress.org
.distignore                    cosa NON pubblicare su wordpress.org
bin/                           script di supporto usati dalla CI
tests/                         test PHPUnit
```

## Licenza

GPL-2.0-or-later — vedi https://www.gnu.org/licenses/gpl-2.0.html
