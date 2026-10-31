<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use Twig\Sandbox\CompileTimeSourcePolicyInterface;
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
 * That trust is for renders Grav starts. A page with `process.twig: true` can
 * still `{% include %}` a theme partial, but the partial then runs on variables
 * the page chose, so it has to stay inside the policy as well:
 * TwigEnvironment::render() switches the sandbox on for the whole render of a
 * source sandboxed here, whatever that source goes on to load. The partials
 * listed under `security.twig_sandbox.trusted_partials` get a trusted render on
 * Grav's own variables instead (TwigEnvironment::includeFromContent()).
 *
 * The same decision is also made once, when a template compiles, through the
 * getgrav/Twig fork's CompileTimeSourcePolicyInterface: a trusted template
 * compiles with no sandbox checks at all instead of asking this policy on every
 * print and attribute access. Twig keeps the full checks for anything compiled
 * while the sandbox is switched on (`{% sandbox %}`, sandboxed includes), for
 * templates that use the `{% sandbox %}` tag, and gives a trusted template a
 * guard that renders its fully checked variant instead when it is reached
 * inside a sandboxed render. The decision is
 * baked into compiled templates, so if these rules change, the compiled Twig
 * cache must be cleared.
 */
final class GravSourcePolicy implements CompileTimeSourcePolicyInterface
{
    public function enableSandbox(Source $source): bool
    {
        return self::isSandboxedName($source->getName());
    }

    /**
     * Whether a template name is one of the editor-authored string templates
     * registered via Twig::setTemplate(). These share the loader chain with the
     * theme and plugin templates, so a caller that resolves a template by a name
     * an editor controls (the page `template` header, a form action parameter)
     * can be handed content instead of a trusted template and must check here.
     */
    public static function isSandboxedName(string $name): bool
    {
        // This runs for every attribute access in every template, and trusted disk
        // templates (never '@'-prefixed) are the overwhelmingly common case.
        if ($name === '' || $name[0] !== '@') {
            return false;
        }

        // `@EmailVar:` is the Email plugin's form-action parameters (subject,
        // body, recipients). Those come from a page's `form.process.email.*`
        // front matter, so they are editor-authored too and must be sandboxed
        // like any other content Twig. (GHSA-gh8j-q67c-j53f)
        return str_starts_with($name, '@Page:')
            || str_starts_with($name, '@Var:')
            || str_starts_with($name, '@EmailVar:');
    }

    /**
     * Only templates loaded from a file skip the sandbox checks. A string
     * template without a path (`template_from_string()`, anything registered
     * with setTemplate()) keeps them even when it is not sandboxed, because it
     * may be created first and then rendered inside a sandboxed include, where a
     * trusted template would refuse to render.
     */
    public function isTrusted(Source $source): bool
    {
        return '' !== $source->getPath();
    }
}
