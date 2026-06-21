<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use Twig\Sandbox\SourcePolicyInterface;
use Twig\Source;

/**
 * Tells Twig's SandboxExtension which template sources to sandbox.
 *
 * We sandbox editor-authored content — templates that Grav creates in-memory
 * from page content / user input via `setTemplate()`, which end up with source
 * names prefixed `@Page:` (Twig::processPage) or `@Var:` (Twig::processString).
 *
 * We do NOT sandbox templates loaded from disk (themes, plugins, modular
 * partials) — those are trusted code authored by the site operator, and
 * sandboxing them would block legitimate uses of the full Grav container.
 *
 * This means a page with `process.twig: true` can still `{% include %}` a
 * theme partial; the include runs against the partial's own (file) source,
 * which is unsandboxed, while the surrounding editor template remains under
 * the policy.
 */
final class GravSourcePolicy implements SourcePolicyInterface
{
    public function enableSandbox(Source $source): bool
    {
        $name = $source->getName();
        // Editor-authored string templates registered via Twig::setTemplate().
        return str_starts_with($name, '@Page:') || str_starts_with($name, '@Var:');
    }
}
