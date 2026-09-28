<?php

/**
 * Undo the Twig-sandbox denials the 2026-08-12 migration wrote for additions lists.
 *
 * That migration reads each `security.twig_sandbox.allowed_*` list as an old
 * replacement list and denies every default it omits. It runs on a site's first
 * upgrade whenever versions.yaml predates it, which was every fresh install
 * (they recorded a stale GRAV_SCHEMA) and every site migrated from 1.7 (it keeps
 * its 1.7 schema). On those sites `allowed_*` holds additions for the additive
 * model, such as a plugin's Twig function or what the migrate-grav wizard found
 * in page content, so the migration denied nearly the whole default sandbox:
 * `date`, `max`, `batch` and a hundred more stopped working in page Twig.
 *
 * The planner now skips such lists. This removes what the unguarded planner
 * added for them: the entries come out only when every one of them is still
 * in the `denied_*` list, and anything else in it stays. A replacement list is
 * made of defaults, so it is left alone.
 *
 * The test for an additions list is made here rather than by the new planner,
 * because the Security class loaded during an upgrade is usually the old one.
 * For the same reason the 2026-08-12 migration may still run unguarded in this
 * very upgrade, and this, which runs after it, undoes that too.
 */

use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\SandboxDefaults;
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
                    return;
                }
                $yaml = YamlUpdater::instance($file);
                $sandbox = (array) ($yaml->get('twig_sandbox', []) ?? []);
                // Old and new planners both take this call unguarded.
                $wrong = Security::planSandboxDefaultsMigration($sandbox, false);

                // Lowercased members; `class::member` for the per-class lists.
                $members = static function ($list): array {
                    $out = [];
                    foreach ((array) $list as $key => $entry) {
                        if (is_array($entry) && isset($entry['class'])) {
                            [$class, $names] = [(string) $entry['class'], $entry['methods'] ?? []];
                        } elseif (is_string($key) && is_array($entry)) {
                            [$class, $names] = [$key, $entry];
                        } elseif (is_scalar($entry)) {
                            $out[] = strtolower((string) $entry);
                            continue;
                        } else {
                            continue;
                        }
                        $names = is_string($names) ? explode(',', $names) : (array) $names;
                        foreach ($names as $name) {
                            $out[] = strtolower($class . '::' . trim((string) $name));
                        }
                    }

                    return array_values(array_unique($out));
                };
                $defaults = SandboxDefaults::all();

                $restored = [];
                foreach ($wrong as $key => $entries) {
                    $type = substr($key, strlen('denied_'));
                    $user = $members($sandbox['allowed_' . $type] ?? []);
                    $known = count(array_intersect($user, $members($defaults[$type] ?? [])));
                    if (count($user) - $known <= $known) {
                        continue; // made of defaults: a replacement list, whose denials stand
                    }
                    $current = $sandbox[$key] ?? null;
                    if (!is_array($current)) {
                        continue;
                    }
                    foreach ($entries as $entry) {
                        if (!in_array($entry, $current, true)) {
                            continue 2; // changed since, so not ours to undo
                        }
                    }
                    $rest = array_values(array_filter($current, static fn($entry) => !in_array($entry, $entries, true)));
                    $yaml->undefine("twig_sandbox.{$key}");
                    if ($rest) {
                        $yaml->define("twig_sandbox.{$key}", $rest);
                    }
                    $restored[] = $key . ' (' . count($entries) . ')';
                }

                if ($restored) {
                    $yaml->save();
                    error_log('Grav upgrade: restored Twig-sandbox defaults an earlier upgrade denied by mistake, removing ' . implode(', ', $restored) . ' from user/config/security.yaml.');
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not restore the Twig-sandbox defaults', $e);
            }
        }
];
