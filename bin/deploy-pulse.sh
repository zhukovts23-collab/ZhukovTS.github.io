#!/bin/bash
set -euo pipefail
umask 077
source_dir="$HOME/gameverse-release"
site_dir="$HOME/zhukovop.beget.tech/public_html"
revision=9164a8cb6a22ddf570a3ed69180e5b466766626c
php_bin=/usr/local/bin/php8.3
paths='api/pulse.php css/pulse.css js/pulse.js services/pulse.html database/pulse.sql bin/update-pulse.php services.html search-index.json database/schema.sql bin/configure-url.php'
test -d "$source_dir/.git"
test -f "$site_dir/app/bootstrap.php"
test -x "$php_bin"
git -C "$source_dir" fetch --quiet origin feature/php-mysql-labs
stage_dir=$(mktemp -d "$HOME/gameverse-pulse-stage.XXXXXX")
trap 'rm -rf "$stage_dir"' EXIT
for path in $paths; do
 mkdir -p "$stage_dir/$(dirname "$path")"
 git -C "$source_dir" show "$revision:$path" > "$stage_dir/$path"
done
for path in api/pulse.php bin/update-pulse.php bin/configure-url.php; do
 "$php_bin" -l "$stage_dir/$path"
done
mkdir -p "$HOME/gameverse-backups"
backup_dir=$(mktemp -d "$HOME/gameverse-backups/pulse-$(date +%Y%m%d-%H%M%S).XXXXXX")
cd "$site_dir"
for path in $paths *.html services/*.html robots.txt sitemap.xml sitemap.txt; do
 if [ -f "$path" ]; then
  mkdir -p "$backup_dir/$(dirname "$path")"
  cp -p "$path" "$backup_dir/$path"
 fi
done
# Only add missing tables. Existing publications and passwords are preserved.
"$php_bin" -r 'require "app/bootstrap.php"; foreach(explode(";",file_get_contents($argv[1])) as $sql)if(trim($sql))db()->exec($sql); echo "Steam Pulse tables ready.\n";' "$stage_dir/database/pulse.sql"
for path in $paths; do
 mkdir -p "$(dirname "$path")"
 cp "$stage_dir/$path" "$path"
 chmod 644 "$path"
done
"$php_bin" bin/configure-url.php
chmod 644 robots.txt sitemap.xml sitemap.txt
for path in / /robots.txt /sitemap.xml /sitemap.txt /services/pulse.html; do
 status=$(curl -sS --connect-timeout 5 --max-time 15 -o /dev/null -w '%{http_code}' "http://zhukovop.beget.tech$path")
 printf '%s HTTP %s\n' "$path" "$status"
 if [ "$status" != 200 ]; then
  printf 'Public access check failed. Backup: %s\n' "$backup_dir"
  exit 4
 fi
done
curl -fsS --max-time 25 'http://zhukovop.beget.tech/api/pulse.php' > "$stage_dir/pulse.json"
"$php_bin" -r '$d=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);if(count($d["games"]??[])!==2)exit(2);foreach($d["games"] as $g){echo $g["name"].": ".($g["players"]??"Steam unavailable")." | ".($g["sampled_at"]??"no sample").PHP_EOL;if($g["players"]===null)exit(3);}' "$stage_dir/pulse.json"
printf 'DEPLOYED: http://zhukovop.beget.tech/services/pulse.html\nBackup: %s\n' "$backup_dir"
