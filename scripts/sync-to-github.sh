#!/bin/bash
set -e
cd /home/kwkrbcce/public_html
TOKEN=$(grep '^GITHUB_TOKEN=' .env | cut -d'=' -f2-)
if [ -z "$TOKEN" ]; then
  echo 'ERROR: GITHUB_TOKEN not found in .env'
  exit 1
fi
if [ -d /home/kwkrbcce/dmh-temp ]; then
  cd /home/kwkrbcce/dmh-temp
  git remote set-url origin https://${TOKEN}@github.com/congvinh298-byte/DMH.git
  git pull origin main
else
  cd /home/kwkrbcce
  rm -rf dmh-temp
  git clone https://${TOKEN}@github.com/congvinh298-byte/DMH.git dmh-temp
  cd dmh-temp
fi
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +
cd /home/kwkrbcce/public_html
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec cp -a {} /home/kwkrbcce/dmh-temp/ \;
cd /home/kwkrbcce/dmh-temp
rm -f .env .env.* .htpasswd shell.php path-test.php root-test.txt
find . -name '*.log' -delete
find . -name 'error_log' -delete
DATETIME=$(date '+%Y-%m-%d %H:%M:%S')
git config user.email 'anhthien@A-Thien' || true
git config user.name 'Thien (Auto Sync)' || true
git add -A
git commit -m "Auto sync from server - $DATETIME" || echo 'Nothing to commit'
git push origin main
git remote set-url origin https://github.com/congvinh298-byte/DMH.git
echo "Sync completed at $DATETIME"
