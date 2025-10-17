# Grav Safe Self-Upgrade Prototype

This document tracks the design decisions behind the new self-upgrade prototype for Grav 1.8.

## Goals

- Prevent in-place mutation of the running Grav tree.
- Guarantee a restorable snapshot before any destructive change.
- Detect high-risk plugin incompatibilities (eg. `psr/log`) prior to upgrading.
- Provide a recovery surface that does not depend on a working Admin plugin.

## High-Level Flow

1. **Preflight**
   - Ensure PHP & extensions satisfy the target release requirements.
   - Refresh GPM metadata and require all plugins/themes to be on their latest compatible release.
   - Scan plugin `composer.json` files for dependencies that are known to break under Grav 1.8 (eg. `psr/log` < 3) and surface actionable warnings.
2. **Stage**
  - Download the Grav update archive into a staging area (`tmp://grav-snapshots/{timestamp}`).
   - Extract the package, then write a manifest describing the target version, PHP info, and enabled packages.
   - Snapshot the live `user/` directory and relevant metadata into the same stage folder.
3. **Promote**
   - Copy the staged package into place, overwriting Grav core files while leaving hydrated user content intact.
   - Clear caches in the staged tree before promotion.
   - Run Grav CLI smoke checks (`bin/grav check`) while still holding maintenance state; restore from the snapshot automatically on failure.
4. **Finalize**
   - Record the manifest under `user/data/upgrades`.
   - Resume normal traffic by removing the maintenance flag.
   - Leave the previous tree and manifest available for manual rollback commands.

## Recovery Mode

- Introduce a `system/recovery.flag` sentinel written whenever a fatal error occurs during bootstrap or when a promoted release fails validation.
- While the flag is present, Grav forces a minimal Recovery UI served outside of Admin, protected by a short-lived signed token.
- The Recovery UI lists recent manifests, quarantined plugins, and offers rollback/disabling actions.
- Clearing the flag requires either a successful rollback or a full Grav request cycle without fatal errors.

## CLI Additions

- `bin/gpm preflight grav@<version>`: runs the same preflight checks without executing the upgrade.
- `bin/gpm rollback [<manifest-id>]`: swaps the live tree with a stored rollback snapshot.
- Existing `self-upgrade` command now wraps the stage/promote pipeline and respects the snapshot manifest.

## Open Items

- Finalize compatibility heuristics (initial pass focuses on `psr/log` and removed logging APIs).
- UX polish for the Recovery UI (initial prototype will expose basic actions only).
- Decide retention policy for old manifests and snapshots (prototype keeps the most recent three).
