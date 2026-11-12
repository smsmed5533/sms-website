#!/bin/bash
# SMS Medical website auto-deploy (runs from cron on the Hawk Host account, every minute).
# Pulls github.com/smsmed5533/sms-website (read-only deploy key) and, when main has a new
# commit, copies site/ into public_html. Overwrites only; never deletes anything on the server.
# A copy of every file it is about to overwrite is kept in ~/sms-website-deploy/backups/<time>-<commit>/.
#
# Server copy lives at ~/sms-website-deploy/deploy.sh. Cron runs it through `tr -d '\r'`,
# because cPanel's file editor saves with Windows line endings.

set -u
BASE="$HOME/sms-website-deploy"
REPO="$BASE/repo"
WEB="$HOME/public_html"
KEY="$HOME/.ssh/sms_website_deploy"
LOG="$BASE/deploy.log"
STATUS="$WEB/deploy-status.json"
BRANCH=main

mkdir -p "$BASE/backups"
exec 9>"$BASE/lock"
flock -n 9 || exit 0

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"; }
status() { # state commit message
  printf '{"state":"%s","commit":"%s","time":"%s","message":"%s"}\n' \
    "$1" "$2" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$(echo "$3" | tr -d '"\\' | tr '\n' ' ' | cut -c1-300)" > "$STATUS.tmp" \
    && mv -f "$STATUS.tmp" "$STATUS"
}

GIT=$(command -v git || echo /usr/local/cpanel/3rdparty/bin/git)
PHP=/usr/local/bin/php
SSHOPTS="-i $KEY -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=$BASE/known_hosts -o ConnectTimeout=20"
export GIT_SSH_COMMAND="ssh $SSHOPTS"

# GitHub over port 22, or over 443 (ssh.github.com) if 22 is blocked
URL=git@github.com:smsmed5533/sms-website.git
[ -f "$BASE/use443" ] && { URL=ssh://git@ssh.github.com:443/smsmed5533/sms-website.git; }

if [ ! -d "$REPO/.git" ]; then
  rm -rf "$REPO"
  if ! out=$("$GIT" clone -q --depth 20 --branch "$BRANCH" "$URL" "$REPO" 2>&1); then
    if [ ! -f "$BASE/use443" ] && echo "$out" | grep -qiE 'timed out|refused|unreachable|resolve'; then
      touch "$BASE/use443"; log "port 22 blocked, switching to 443: $out"
    else log "clone failed: $out"; fi
    status error none "clone failed: $out"; exit 1
  fi
  "$GIT" -C "$REPO" remote set-url origin "$URL"
  log "cloned"
fi

cd "$REPO" || exit 1
if ! out=$("$GIT" fetch -q --depth 20 origin "$BRANCH" 2>&1); then
  log "fetch failed: $out"; status error "$(cat "$BASE/deployed" 2>/dev/null)" "fetch failed: $out"; exit 1
fi
NEW=$("$GIT" rev-parse "origin/$BRANCH")
OLD=$(cat "$BASE/deployed" 2>/dev/null || echo none)
[ "$NEW" = "$OLD" ] && exit 0
[ "$NEW" = "$(cat "$BASE/blocked" 2>/dev/null)" ] && exit 0
"$GIT" reset -q --hard "origin/$BRANCH"
MSG=$("$GIT" log -1 --format=%s)

# Checks before anything goes live
fail() { log "NOT deployed $NEW: $1"; status blocked "$NEW" "$1"; echo "$NEW" > "$BASE/blocked"; exit 1; }
[ -d site ] || fail "no site/ folder"
[ -s site/index.html ] && [ -s site/index.php ] || fail "index.html or index.php missing"
grep -q '</html>' site/index.html || fail "index.html looks truncated"
if [ -x "$PHP" ]; then
  while IFS= read -r f; do
    o=$("$PHP" -l "$f" 2>&1) || fail "PHP syntax error in $f: $o"
  done < <(find site -name '*.php' -type f)
fi
find site -type f \( -name '*.key' -o -name '.resend_key' -o -name '*.env' -o -name '.env*' \) | grep -q . && fail "secret-looking file in site/"

# Back up what will be overwritten, then copy
STAMP="$(date '+%Y%m%d-%H%M%S')-${NEW:0:7}"
BK="$BASE/backups/$STAMP"
mkdir -p "$BK"
( cd site && find . -type f ) | while IFS= read -r f; do
  if [ -f "$WEB/$f" ]; then mkdir -p "$BK/$(dirname "$f")"; cp -p "$WEB/$f" "$BK/$f"; fi
done
if ! out=$(tar -C site -cf - . | tar -C "$WEB" -xf - 2>&1); then
  log "copy failed for $NEW: $out"; status error "$NEW" "copy failed: $out"; exit 1
fi
echo "$NEW" > "$BASE/deployed"
rm -f "$BASE/blocked"
log "deployed $NEW ($MSG) backup=$STAMP"
status deployed "$NEW" "$MSG"

# Keep the 30 most recent backups and a short log
ls -1dt "$BASE"/backups/*/ 2>/dev/null | tail -n +31 | xargs -r rm -rf
tail -n 500 "$LOG" > "$LOG.tmp" && mv -f "$LOG.tmp" "$LOG"
