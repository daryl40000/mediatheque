#!/usr/bin/env bash
# Lance le site en local avec les bonnes limites pour les PDF magazines.
cd "$(dirname "$0")" || exit 1
exec ./www/serve.sh "$@"
