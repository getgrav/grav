<?php

/**
 * Relocate the content XSS output scan toggle out of the Twig-in-content
 * section.
 *
 * `security.twig_content.xss_scan_output` was never a Twig-in-content concern —
 * it validates editor-authored page *content* (it also applies to modular
 * pages, whose bodies are always Twig-processed regardless of the
 * `twig_content` gate). It moves to `security.content.xss_scan_output`.
 *
 * The runtime still honours the old key as a fallback (see
 * Security::isXssScanOutputEnabled()), so this migration only tidies the config
 * file: any explicit value at the legacy location is copied to the new one and
 * the legacy key is removed. Idempotent — once moved, the legacy key is gone so
 * a re-run is a no-op.
 */

use Grav\Installer\InstallException;
use Grav\Installer\VersionUpdate;
use Grav\Installer\YamlUpdater;

return [
    'preflight' => null,
    'postflight' =>
        function () {
            /** @var VersionUpdate $this */
            try {
                $yaml = YamlUpdater::instance(GRAV_ROOT . '/user/config/security.yaml');

                if ($yaml->exists('twig_content.xss_scan_output')) {
                    $value = $yaml->get('twig_content.xss_scan_output');
                    $yaml->undefine('twig_content.xss_scan_output');
                    // Only define the new key if it isn't already set, so an
                    // explicit new-location value always wins over the legacy one.
                    if (!$yaml->exists('content.xss_scan_output')) {
                        $yaml->define('content.xss_scan_output', $value);
                    }
                    $yaml->save();
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not migrate security.twig_content.xss_scan_output to security.content.xss_scan_output', $e);
            }
        }
];
