#!/usr/bin/env bash
# Run interactively after filling app/config.local.php on a PHP/MySQL host.
set -euo pipefail
cd -- "$(dirname -- "$0")/.."
php_bin=${GV_PHP_BIN:-php}
if ! "$php_bin" -r 'exit(PHP_VERSION_ID>=80300?0:1);'; then
 printf 'Choose PHP 8.3+ with GV_PHP_BIN=/path/to/php bash bin/install-host.sh\n' >&2
 exit 1
fi
if [[ ! -f app/config.local.php ]]; then
 printf 'First configure app/config.local.php using config.example.php.\n' >&2
 exit 1
fi
if [[ ! -t 0 ]]; then
 printf 'Run in an interactive hosting terminal.\n' >&2
 exit 1
fi
"$php_bin" -r 'require "app/bootstrap.php"; foreach(["pdo_mysql","xsl","mbstring","curl","dom","simplexml"] as $e)if(!extension_loaded($e)){fwrite(STDERR,"Missing extension: ".$e.PHP_EOL);exit(1);}if(strlen($config["secret"])<32||str_contains($config["secret"],"REPLACE")){fwrite(STDERR,"Set a fresh APP secret of at least 32 characters in config.local.php".PHP_EOL);exit(1);}db()->query("SELECT 1");echo "PHP and database checks passed.".PHP_EOL;'
trap 'unset GV_ADMIN_PASSWORD GV_ADMIN_CONFIRM' EXIT
read -r -s -p 'New GameVerse admin password (14+ characters): ' GV_ADMIN_PASSWORD
printf '\n'
read -r -s -p 'Repeat password: ' GV_ADMIN_CONFIRM
printf '\n'
if [[ "$GV_ADMIN_PASSWORD" != "$GV_ADMIN_CONFIRM" || ${#GV_ADMIN_PASSWORD} -lt 14 ]]; then
 printf 'Passwords must match and contain at least 14 characters.\n' >&2
 exit 1
fi
ADMIN_PASSWORD="$GV_ADMIN_PASSWORD" "$php_bin" bin/install.php
"$php_bin" bin/configure-url.php
chmod 644 robots.txt sitemap.xml sitemap.txt
printf 'Installed. Open the site and /admin.html (username admin).\n'
