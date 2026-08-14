<?php

/**
 * Preserve a site's tightened Twig-sandbox policy across the defaults-in-code move.
 *
 * The 2026-08-12 security-config audit moved the ~300-line Twig-sandbox default
 * allowlists out of the shipped system/config/security.yaml and into code
 * (Grav\Common\Twig\Sandbox\SandboxDefaults). The `security.twig_sandbox.allowed_*`
 * keys are now ADDITIVE over those defaults instead of REPLACING them.
 *
 * That change is behaviour-preserving for the common case (a site that never
 * touched the lists), but not for a site that wrote its own `allowed_*` list to
 * TIGHTEN the policy: under the old replace-merge, omitting a default blocked it;
 * under the new additive model that default silently returns. This migration
 * denies precisely the defaults such a list omitted, reproducing the site's exact
 * pre-upgrade effective policy — anything blocked before stays blocked, anything
 * allowed before stays allowed. Sites that never set an `allowed_*` key are left
 * untouched. Idempotent: re-running merges the same denials with no net change.
 *
 * The planning is in Security::planSandboxDefaultsMigration() (pure, unit-tested).
 */

use Grav\Common\Security;
use Grav\Installer\InstallException;
use Grav\Installer\VersionUpdate;
use Grav\Installer\YamlUpdater;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            /** @var VersionUpdate $this */
            try {
                $file = GRAV_ROOT . '/user/config/security.yaml';
                if (!is_file($file)) {
                    return; // no user overrides → defaults apply, nothing to migrate
                }

                $yaml = YamlUpdater::instance($file);

                $userSandbox = (array) ($yaml->get('twig_sandbox', []) ?? []);
                $plan = Security::planSandboxDefaultsMigration($userSandbox);
                if (!$plan) {
                    return; // lists untouched (or already supersets) → no denials needed
                }

                foreach ($plan as $key => $additions) {
                    $existing = (array) ($yaml->get("twig_sandbox.{$key}", []) ?? []);
                    // Flat lists de-dup by value; row lists append (a class row for
                    // a class not already denied). Both are idempotent because the
                    // planner is deterministic and merge below drops exact dupes.
                    $merged = $existing;
                    foreach ($additions as $entry) {
                        if (!in_array($entry, $merged, true)) {
                            $merged[] = $entry;
                        }
                    }
                    $yaml->define("twig_sandbox.{$key}", array_values($merged));
                }

                $yaml->save();

                $summary = [];
                foreach ($plan as $key => $additions) {
                    $summary[] = $key . ' (' . count($additions) . ')';
                }
                error_log(
                    'Grav upgrade: preserved tightened Twig-sandbox policy in '
                    . 'user/config/security.yaml by adding ' . implode(', ', $summary)
                    . '. Review these denials if any were defaults you actually want; '
                    . 'see security.twig_sandbox.denied_* in the file.'
                );
            } catch (\Exception $e) {
                throw new InstallException('Could not migrate the Twig-sandbox allowlists to the additive-defaults model', $e);
            }
        }
];
