#!/usr/bin/env bash
# Local self-upgrade test harness.
# Spins up a fake GPM feed, points a target Grav install at it, and exercises
# the upgrade flow against a locally-built grav-update zip.
#
# Modes:
#   cli     run `bin/gpm selfupgrade` end-to-end non-interactively, then tear down
#   admin   patch URL + start server, keep both running so you can trigger the
#           upgrade from the admin UI in a browser; Ctrl+C to tear down
#   snapshot  snapshot the target install to $SNAPSHOT so you can re-run tests
#   restore   restore the target install from $SNAPSHOT
#
# Usage:  bin/test-selfupgrade.sh [cli|admin|snapshot|restore]   (default: cli)
#
# Env overrides: TARGET, SNAPSHOT, ZIP_PATH, TO_VERSION, PORT, CDN_DIR

set -euo pipefail

MODE="${1:-cli}"

TARGET="${TARGET:-$HOME/workspace/grav-admin-180b29}"
SNAPSHOT="${SNAPSHOT:-${TARGET}.snapshot}"
ZIP_PATH="${ZIP_PATH:-/tmp/grav-build-local/grav-dist/grav-update-v1.8.0-beta.30.zip}"
TO_VERSION="${TO_VERSION:-1.8.0-beta.30}"
PORT="${PORT:-8765}"
CDN_DIR="${CDN_DIR:-/tmp/grav-fake-cdn}"

core="$TARGET/system/src/Grav/Common/GPM/Remote/GravCore.php"

require_target() {
  [[ -d "$TARGET" ]] || { echo "ERROR: target install not found at $TARGET" >&2; exit 1; }
}

require_zip() {
  [[ -f "$ZIP_PATH" ]] || { echo "ERROR: zip not found at $ZIP_PATH" >&2; exit 1; }
}

do_snapshot() {
  require_target
  if [[ -d "$SNAPSHOT" ]]; then
    echo "snapshot already exists at $SNAPSHOT — delete it first if you want a fresh one"
    exit 1
  fi
  echo "snapshotting $TARGET -> $SNAPSHOT"
  cp -R "$TARGET" "$SNAPSHOT"
  echo "done."
}

do_restore() {
  [[ -d "$SNAPSHOT" ]] || { echo "ERROR: no snapshot at $SNAPSHOT" >&2; exit 1; }
  echo "restoring $TARGET from $SNAPSHOT"
  rm -rf "$TARGET"
  cp -R "$SNAPSHOT" "$TARGET"
  echo "done."
}

stage_cdn() {
  require_zip
  local zip_name zip_size
  zip_name="$(basename "$ZIP_PATH")"
  zip_size="$(stat -f%z "$ZIP_PATH" 2>/dev/null || stat -c%s "$ZIP_PATH")"

  echo "=== staging fake CDN at $CDN_DIR ==="
  rm -rf "$CDN_DIR"
  mkdir -p "$CDN_DIR"
  cp "$ZIP_PATH" "$CDN_DIR/$zip_name"

  cat > "$CDN_DIR/grav.json" <<JSON
{
  "version": "$TO_VERSION",
  "date": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "assets": {
    "grav-update": {
      "name": "$zip_name",
      "type": "binary/octet-stream",
      "size": $zip_size,
      "download": "http://localhost:$PORT/$zip_name"
    }
  },
  "url": "http://localhost:$PORT/$zip_name",
  "min_php": "8.3.0",
  "changelog": {
    "$TO_VERSION": {
      "date": "$(date +%m/%d/%Y)",
      "content": "Local test build"
    }
  }
}
JSON
  echo "wrote $CDN_DIR/grav.json and $CDN_DIR/$zip_name"
}

patch_core() {
  echo "=== patching $core (backup at $core.bak) ==="
  cp "$core" "$core.bak"
  sed -i.tmp "s#https://getgrav.org/downloads/grav.json#http://localhost:$PORT/grav.json#g" "$core"
  rm -f "$core.tmp"
  grep "repository" "$core" | head -1
}

restore_core() {
  [[ -f "$core.bak" ]] && mv "$core.bak" "$core" && echo "restored $core"
}

plant_sentinels() {
  echo "=== planting upgrade.php / needs_fixing.txt sentinels in target ==="
  touch "$TARGET/upgrade.php" "$TARGET/needs_fixing.txt"
  ls -l "$TARGET/upgrade.php" "$TARGET/needs_fixing.txt"
}

start_server() {
  echo "=== starting php -S localhost:$PORT (log at /tmp/grav-cdn.log) ==="
  pushd "$CDN_DIR" >/dev/null
  php -S "localhost:$PORT" >/tmp/grav-cdn.log 2>&1 &
  server_pid=$!
  popd >/dev/null
  sleep 1
  curl -sf "http://localhost:$PORT/grav.json" >/dev/null && echo "server up, feed reachable"
}

clear_gpm_cache() {
  rm -rf "$TARGET/cache/gpm"
  echo "cleared $TARGET/cache/gpm"
}

verify() {
  echo "=== verify ==="
  echo -n "GRAV_VERSION now: "
  grep "GRAV_VERSION" "$TARGET/system/defines.php"
  echo -n "schema stamp now: "
  grep "schema:" "$TARGET/user/config/versions.yaml" | head -1
  echo -n "upgrade.php:     "
  [[ -f "$TARGET/upgrade.php" ]] && echo "STILL EXISTS (fail)" || echo "removed (good)"
  echo -n "needs_fixing.txt:"
  [[ -f "$TARGET/needs_fixing.txt" ]] && echo " STILL EXISTS (fail)" || echo " removed (good)"
}

case "$MODE" in
  snapshot) do_snapshot ;;
  restore)  do_restore ;;

  cli)
    require_target
    plant_sentinels
    stage_cdn
    patch_core
    clear_gpm_cache
    start_server
    trap 'kill $server_pid 2>/dev/null || true; restore_core' EXIT

    echo
    echo "=== running bin/gpm selfupgrade -y -f ==="
    pushd "$TARGET" >/dev/null
    bin/gpm selfupgrade -y -f || echo "(selfupgrade exited non-zero)"
    popd >/dev/null

    echo
    verify
    echo
    echo "Done. GravCore.php restored, server stopped."
    ;;

  admin)
    require_target
    plant_sentinels
    stage_cdn
    patch_core
    clear_gpm_cache
    start_server

    cat <<EOF

=== admin UI test mode ===
Fake CDN running at:  http://localhost:$PORT/grav.json
Zip being served:     $(basename "$ZIP_PATH")
Target install:       $TARGET
GravCore.php patched: yes (backup at $core.bak)

Open your admin (e.g. http://localhost/grav-admin-180b29/admin) and trigger
the Grav core update. The admin should see v$TO_VERSION available.

If the admin doesn't see the update, hard-refresh or visit:
  http://localhost/grav-admin-180b29/admin/update (force GPM refresh)

After the upgrade completes in the browser, come back here and press Ctrl+C
to tear down (server stops, GravCore.php restored).

Tip: snapshot first so you can re-run —
  bin/test-selfupgrade.sh snapshot
  bin/test-selfupgrade.sh admin
  # ... test ...
  # Ctrl+C
  bin/test-selfupgrade.sh restore

EOF
    trap 'echo; echo "tearing down..."; kill $server_pid 2>/dev/null || true; restore_core; echo "done."; verify' EXIT INT TERM
    wait $server_pid
    ;;

  *)
    echo "usage: $0 [cli|admin|snapshot|restore]" >&2
    exit 2
    ;;
esac
