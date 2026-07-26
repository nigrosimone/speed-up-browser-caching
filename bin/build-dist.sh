#!/usr/bin/env bash
#
# Ricostruisce il pacchetto che verrebbe pubblicato su wordpress.org, cioe' il
# repository meno tutto quanto elencato in .distignore.
#
# E' la stessa selezione che applica 10up/action-wordpress-plugin-deploy: averla
# in uno script solo evita che CI e controllo divergenza usino criteri diversi.
#
# Uso: bash bin/build-dist.sh [destinazione]   (default: build)

set -euo pipefail

DEST="${1:-build}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$ROOT"

if [ ! -f .distignore ]; then
    echo "ERRORE: .distignore non trovato in $ROOT" >&2
    exit 1
fi

rm -rf "$DEST"
mkdir -p "$DEST"

rsync -a \
    --exclude='.git' \
    --exclude='/build' \
    --exclude-from='.distignore' \
    ./ "$DEST/"

echo "Pacchetto ricostruito in: $DEST"
find "$DEST" -type f | sort | sed 's|^|  |'

# Rete di sicurezza: ogni file di sviluppo aggiunto nella radice del repo va
# escluso a mano in .distignore, ed e' facile dimenticarsene. Un file nascosto
# nel pacchetto e' sempre un errore: nessuno di essi serve a chi installa il
# plugin, e finirebbe nello zip scaricato dagli utenti.
LEAKED="$(find "$DEST" -name '.*' -not -name '.' -not -name '..' | sort)"

if [ -n "$LEAKED" ]; then
    echo "" >&2
    echo "ERRORE: file di sviluppo finiti nel pacchetto pubblicato:" >&2
    echo "$LEAKED" | sed 's|^|  |' >&2
    echo "" >&2
    echo "Aggiungili a .distignore." >&2
    exit 1
fi
