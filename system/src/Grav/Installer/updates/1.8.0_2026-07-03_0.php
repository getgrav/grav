<?php

/**
 * Retire the render-time content XSS output scan.
 *
 * `security.content.xss_scan_output` (and its legacy location
 * `security.twig_content.xss_scan_output`) gated a render-time re-scan of
 * editor-authored content. That scan is gone: the render-time-assembled-payload
 * gap it covered is now closed at SAVE time by
 * Security::detectXssInEditorContent (see system/config/security.yaml). Neither
 * key is read anymore, and `security.xss_allowed_iframe_hosts` — which only fed
 * that scan — is likewise dead.
 *
 * This migration strips the now-inert keys from user config so a stale toggle
 * doesn't linger in the admin. Idempotent — once removed, a re-run is a no-op.
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

                $changed = false;
                foreach (['twig_content.xss_scan_output', 'content.xss_scan_output', 'xss_allowed_iframe_hosts'] as $key) {
                    if ($yaml->exists($key)) {
                        $yaml->undefine($key);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $yaml->save();
                }
            } catch (\Exception $e) {
                throw new InstallException('Could not remove the retired security.*.xss_scan_output settings', $e);
            }
        }
];
