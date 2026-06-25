<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Grav-flavoured Twig sandbox policy. Feature-compatible with Twig's built-in
 * `SecurityPolicy`, plus one extension: wildcard `'*'` in per-class method /
 * property allowlists means "any member". Intended for small, safe classes like
 * `stdClass` (used for page headers built from YAML — dynamic key set) and
 * plain-data wrappers whose properties carry no executable risk.
 */
final class GravSecurityPolicy implements SecurityPolicyInterface
{
    /**
     * @param list<string>                         $allowedTags
     * @param list<string>                         $allowedFilters
     * @param array<class-string, list<string>>    $allowedMethods
     * @param array<class-string, list<string>>    $allowedProperties
     * @param list<string>                         $allowedFunctions
     */
    public function __construct(
        private array $allowedTags = [],
        private array $allowedFilters = [],
        private array $allowedMethods = [],
        private array $allowedProperties = [],
        private array $allowedFunctions = [],
    ) {
    }

    public function checkSecurity($tags, $filters, $functions): void
    {
        foreach ($tags as $tag) {
            if (!in_array($tag, $this->allowedTags, true)) {
                throw new SecurityNotAllowedTagError(sprintf('Tag "%s" is not allowed.', $tag), $tag);
            }
        }

        foreach ($filters as $filter) {
            if (!in_array($filter, $this->allowedFilters, true)) {
                throw new SecurityNotAllowedFilterError(sprintf('Filter "%s" is not allowed.', $filter), $filter);
            }
        }

        foreach ($functions as $function) {
            if (!in_array($function, $this->allowedFunctions, true)) {
                throw new SecurityNotAllowedFunctionError(sprintf('Function "%s" is not allowed.', $function), $function);
            }
        }
    }

    public function checkMethodAllowed($obj, $method): void
    {
        $method = strtolower($method);
        foreach ($this->allowedMethods as $class => $methods) {
            if ($obj instanceof $class && (in_array('*', $methods, true) || in_array($method, $methods, true))) {
                return;
            }
        }

        $class = $obj::class;
        throw new SecurityNotAllowedMethodError(
            sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, $class),
            $class,
            $method
        );
    }

    /**
     * True when $obj is an instance of any class in the method allowlist — i.e.
     * a type sandboxed content is permitted to interact with at all. The
     * dump/serialize filter guards (print_r, json_encode, yaml_encode, string)
     * use this to refuse objects that bypass the member gate by serializing PHP
     * state directly. Note: when `security.twig_content.config_access` is off,
     * the raw `Config`/`Data` entries are stripped in
     * Security::buildTwigSandboxPolicy(), so this returns false for them — only
     * the redacting SandboxConfig facade stays allowed. (GHSA-mc5q-6hpj-rp7j)
     */
    public function isClassAllowed(object $obj): bool
    {
        foreach (array_keys($this->allowedMethods) as $class) {
            if ($obj instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        foreach ($this->allowedProperties as $class => $properties) {
            $props = is_array($properties) ? $properties : [$properties];
            if ($obj instanceof $class && (in_array('*', $props, true) || in_array($property, $props, true))) {
                return;
            }
        }

        $class = $obj::class;
        throw new SecurityNotAllowedPropertyError(
            sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, $class),
            $class,
            $property
        );
    }
}
