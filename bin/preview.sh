#!/bin/sh
set -eu
if [ -f app/preview.env ]; then . ./app/preview.env; fi
[ ! -f app/preview-bootstrap.sh ] || sh app/preview-bootstrap.sh
preview_port=${PORT:-8080}
while [ "$#" -gt 0 ]; do if [ "$1" = "--port" ]; then shift; preview_port="$1"; fi; shift; done
exec "${GV_PHP:-php}" ${GV_PHP_CONFIG:+-c "$GV_PHP_CONFIG"} -S "0.0.0.0:${preview_port}" -t . bin/router.php
