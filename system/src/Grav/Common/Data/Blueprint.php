<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Blueprints\BlueprintForm;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function call_user_func_array;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function strlen;

/**
 * Class Blueprint
 * @package Grav\Common\Data
 */
class Blueprint extends BlueprintForm
{
    /** @var string */
    protected $context = 'blueprints://';

    /** @var string|null */
    protected $scope;

    /** @var BlueprintSchema|null */
    protected $blueprintSchema;

    /** @var object|null */
    protected $object;

    /** @var array|null */
    protected $defaults;

    /** @var array */
    protected $handlers = [];

    /**
     * Clone blueprint.
     */
    public function __clone()
    {
        if (null !== $this->blueprintSchema) {
            $this->blueprintSchema = clone $this->blueprintSchema;
        }
    }

    /**
     * @param string $scope
     * @return void
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @param object $object
     * @return void
     */
    public function setObject($object)
    {
        $this->object = $object;
    }

    /**
     * Set default values for field types.
     *
     * @param array $types
     * @return $this
     */
    public function setTypes(array $types)
    {
        $this->initInternals();

        $this->blueprintSchema->setTypes($types);

        return $this;
    }

    /**
     * @param string $name
     * @return array|mixed|null
     * @since 1.7
     */
    public function getDefaultValue(string $name)
    {
        $path = explode('.', $name);
        $current = $this->getDefaults();

        foreach ($path as $field) {
            if (is_object($current) && isset($current->{$field})) {
                $current = $current->{$field};
            } elseif (is_array($current) && isset($current[$field])) {
                $current = $current[$field];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Get nested structure containing default values defined in the blueprints.
     *
     * Fields without default value are ignored in the list.
     *
     * @return array
     */
    public function getDefaults()
    {
        $this->initInternals();

        if (null === $this->defaults) {
            $this->defaults = $this->blueprintSchema->getDefaults();
        }

        return $this->defaults;
    }

    /**
     * Initialize blueprints with its dynamic fields.
     *
     * @return $this
     */
    public function init()
    {
        foreach ($this->dynamic as $key => $data) {
            // Locate field.
            $path = explode('/', (string) $key);
            $current = &$this->items;

            foreach ($path as $field) {
                if (is_object($current)) {
                    // Handle objects.
                    if (!isset($current->{$field})) {
                        $current->{$field} = [];
                    }

                    $current = &$current->{$field};
                } else {
                    // Handle arrays and scalars.
                    if (!is_array($current)) {
                        $current = [$field => []];
                    } elseif (!isset($current[$field])) {
                        $current[$field] = [];
                    }

                    $current = &$current[$field];
                }
            }

            // Set dynamic property.
            foreach ($data as $property => $call) {
                $action = $call['action'];
                $method = 'dynamic' . ucfirst((string) $action);
                $call['object'] = $this->object;

                if (isset($this->handlers[$action])) {
                    $callable = $this->handlers[$action];
                    $callable($current, $property, $call);
                } elseif (method_exists($this, $method)) {
                    $this->{$method}($current, $property, $call);
                }
            }
        }

        return $this;
    }

    /**
     * Extend blueprint with another blueprint.
     *
     * @param BlueprintForm|array $extends
     * @param bool $append
     * @return $this
     */
    public function extend($extends, $append = false)
    {
        parent::extend($extends, $append);

        $this->deepInit($this->items);

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param string $separator
     * @param bool $append
     * @return $this
     */
    public function embed($name, $value, $separator = '/', $append = false)
    {
        parent::embed($name, $value, $separator, $append);

        $this->deepInit($this->items);

        return $this;
    }

    /**
     * Merge two arrays by using blueprints.
     *
     * @param  array $data1
     * @param  array $data2
     * @param  string|null $name         Optional
     * @param  string $separator    Optional
     * @return array
     */
    public function mergeData(array $data1, array $data2, $name = null, $separator = '.')
    {
        $this->initInternals();

        return $this->blueprintSchema->mergeData($data1, $data2, $name, $separator);
    }

    /**
     * Process data coming from a form.
     *
     * @param array $data
     * @param array $toggles
     * @return array
     */
    public function processForm(array $data, array $toggles = [])
    {
        $this->initInternals();

        return $this->blueprintSchema->processForm($data, $toggles);
    }

    /**
     * Return data fields that do not exist in blueprints.
     *
     * @param  array  $data
     * @param  string $prefix
     * @return array
     */
    public function extra(array $data, $prefix = '')
    {
        $this->initInternals();

        return $this->blueprintSchema->extra($data, $prefix);
    }

    /**
     * Validate data against blueprints.
     *
     * @param  array $data
     * @param  array $options
     * @return void
     * @throws RuntimeException
     */
    public function validate(array $data, array $options = [])
    {
        $this->initInternals();

        $this->blueprintSchema->validate($data, $options);
    }

    /**
     * Filter data by using blueprints.
     *
     * @param  array $data
     * @param  bool $missingValuesAsNull
     * @param  bool $keepEmptyValues
     * @return array
     */
    public function filter(array $data, bool $missingValuesAsNull = false, bool $keepEmptyValues = false)
    {
        $this->initInternals();

        return $this->blueprintSchema->filter($data, $missingValuesAsNull, $keepEmptyValues) ?? [];
    }


    /**
     * Flatten data by using blueprints.
     *
     * @param array $data       Data to be flattened.
     * @param bool $includeAll  True if undefined properties should also be included.
     * @param string $name      Property which will be flattened, useful for flattening repeating data.
     * @return array
     */
    public function flattenData(array $data, bool $includeAll = false, string $name = '')
    {
        $this->initInternals();

        return $this->blueprintSchema->flattenData($data, $includeAll, $name);
    }


    /**
     * Return blueprint data schema.
     *
     * @return BlueprintSchema
     */
    public function schema()
    {
        $this->initInternals();

        return $this->blueprintSchema;
    }

    /**
     * @param string $name
     * @param callable $callable
     * @return void
     */
    public function addDynamicHandler(string $name, callable $callable): void
    {
        $this->handlers[$name] = $callable;
    }

    /**
     * Initialize validator.
     *
     * @return void
     */
    protected function initInternals()
    {
        if (null === $this->blueprintSchema) {
            $types = Grav::instance()['plugins']->formFieldTypes;

            $this->blueprintSchema = new BlueprintSchema;

            if ($types) {
                $this->blueprintSchema->setTypes($types);
            }

            $this->blueprintSchema->embed('', $this->items);
            $this->blueprintSchema->init();
            $this->defaults = null;
        }
    }

    /**
     * @param string $filename
     * @return array
     */
    protected function loadFile($filename)
    {
        $file = CompiledYamlFile::instance($filename);
        $content = (array)$file->content();
        $file->free();

        return $content;
    }

    /**
     * @param string|array $path
     * @param string|null $context
     * @return array
     */
    protected function getFiles($path, $context = null)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        if (is_string($path) && !$locator->isStream($path)) {
            if (is_file($path)) {
                return [$path];
            }

            // Find path overrides.
            if (null === $context) {
                $paths = (array) ($this->overrides[$path] ?? null);
            } else {
                $paths = [];
            }

            // Add path pointing to default context.
            if ($context === null) {
                $context = $this->context;
            }

            if ($context && $context[strlen($context)-1] !== '/') {
                $context .= '/';
            }

            $path = $context . $path;

            if (!preg_match('/\.yaml$/', $path)) {
                $path .= '.yaml';
            }

            $paths[] = $path;
        } else {
            $paths = (array) $path;
        }

        $files = [];
        foreach ($paths as $lookup) {
            if (is_string($lookup) && strpos($lookup, '://')) {
                $files = array_merge($files, $locator->findResources($lookup));
            } else {
                $files[] = $lookup;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicData(array &$field, $property, array &$call)
    {
        $params = $call['params'];

        if (is_array($params)) {
            $function = array_shift($params);
        } else {
            $function = $params;
            $params = [];
        }

        // Security guard. A `data-*@` directive may come from a user-controlled
        // source, most notably a form blueprint the Form plugin assembles from
        // page frontmatter, and this method hands the callable and its arguments
        // straight to call_user_func_array(). Refuse anything that could run
        // arbitrary code: a directly dangerous top-level function (system, exec,
        // ...), or any argument that is itself a dangerous callable. The latter
        // is what stops a benign-looking helper from being used as a trampoline,
        // e.g. Utils::arrayFilterRecursive($cmd, 'system'). Legitimate providers
        // are static methods returning option arrays and never take a callable
        // argument, so real blueprints are unaffected. (GHSA-fj2p-qj2f-74v5)
        if (!self::isSafeDynamicCall($function, $params)) {
            return;
        }

        [$o, $f] = explode('::', (string) $function, 2);

        $data = null;
        if (!$f) {
            if (function_exists($o)) {
                $data = call_user_func_array($o, $params);
            }
        } else {
            if (method_exists($o, $f)) {
                $data = call_user_func_array([$o, $f], $params);
            }
        }

        // If function returns a value,
        if (null !== $data) {
            if (is_array($data) && isset($field[$property]) && is_array($field[$property])) {
                // Combine field and @data-field together.
                $field[$property] += $data;
            } else {
                // Or create/replace field with @data-field.
                $field[$property] = $data;
            }
        }
    }

    /**
     * Guard for {@see dynamicData}: decide whether a dynamic `data-*@` call is
     * safe to execute. A bare top-level function (no `Class::method`) is refused
     * when it is one of the dangerous functions Grav already recognises, and
     * every argument is scanned recursively so a callable cannot be smuggled in
     * as a parameter to a trampoline helper. (GHSA-fj2p-qj2f-74v5)
     *
     * Shared by {@see Blueprint::dynamicData} and Flex's
     * {@see \Grav\Framework\Flex\FlexDirectory::dynamicDataField}, which route
     * the same `data-*@` directive through separate dispatch paths and must
     * enforce the same guard. (GHSA-fj2p-qj2f-74v5, GHSA-c4wf-2xxc-68qm)
     *
     * @param mixed $function
     * @param array $params
     * @return bool
     */
    public static function isSafeDynamicCall($function, array $params): bool
    {
        if (is_string($function) && str_contains($function, '::')) {
            // `Class::method` providers skip the bare-function denylist below
            // (Utils::isDangerousFunction already rejects any string containing
            // `:` or `\`). Without a positive gate that carve-out let a page-edit
            // account name ANY public static method as a dynamic-field provider
            // and reach file-disclosure / file-write / secret-read gadgets
            // (Utils::download, Folder::copy/delete, Security::getNonceKey, ...).
            // Permit only the known-safe option providers on the allowlist.
            // (GHSA-7pgq-cr25-xvc8, GHSA-cxv3-5jj3-cpgr)
            if (!isset(self::$allowedDynamicCallables[strtolower(ltrim($function, '\\'))])) {
                return false;
            }

            return !self::paramsContainDangerousCallable($params);
        }

        if (is_string($function) && Utils::isDangerousFunction($function)) {
            return false;
        }

        return !self::paramsContainDangerousCallable($params);
    }

    /**
     * Allowlist of `Class::method` dynamic-data providers a `data-*@` directive
     * may invoke. Seeded from the providers first-party blueprints actually use;
     * plugins that ship their own raw `Class::method` provider register it once
     * via {@see self::addAllowedDynamicCallable()}. Any `Class::method` not listed
     * is refused, which is what closes the arbitrary-static-method bypass.
     * Keys are lowercased and stripped of a leading `\` so both `\Grav\...` and
     * `Grav\...` spellings match (PHP class/method names are case-insensitive).
     *
     * @var array<string,bool>
     */
    private static $allowedDynamicCallables = [
        'grav\\common\\page\\pages::pagetypes' => true,
        'grav\\common\\page\\pages::types' => true,
        'grav\\common\\security::pageprocessdefaults' => true,
        'grav\\common\\security::pageprocessoptions' => true,
        'grav\\common\\user\\group::groupnames' => true,
        'grav\\common\\utils::dateformats' => true,
        'grav\\common\\utils::timezones' => true,
        'grav\\common\\flex\\types\\usergroups\\usergroupobject::groupnames' => true,
        'grav\\plugin\\admin\\admin::adminlanguages' => true,
        'grav\\plugin\\admin\\admin::contenteditor' => true,
        'grav\\plugin\\admin\\admin::getlastpagename' => true,
        'grav\\plugin\\adminplugin::pagesmodulartypes' => true,
        'grav\\plugin\\adminplugin::pagestypes' => true,
        'grav\\plugin\\adminplugin::themeoptions' => true,
        'grav\\plugin\\flexobjectsplugin::directoryoptions' => true,
    ];

    /**
     * Register an additional `Class::method` dynamic-data provider as safe. Call
     * once at plugin init (e.g. `onPluginsInitialized`) for any blueprint that
     * uses a raw `data-options@: 'My\Plugin::provider'` directive.
     *
     * @param string $callable  A `Class::method` string.
     * @return void
     */
    public static function addAllowedDynamicCallable(string $callable): void
    {
        self::$allowedDynamicCallables[strtolower(ltrim($callable, '\\'))] = true;
    }

    /**
     * Recursively test whether any argument value is a dangerous callable. This
     * blocks the trampoline gadget where a safe helper is handed `system` (or a
     * similar callable) to invoke on attacker-controlled input. (GHSA-fj2p-qj2f-74v5)
     *
     * @param array $params
     * @return bool
     */
    private static function paramsContainDangerousCallable(array $params): bool
    {
        foreach ($params as $value) {
            if (is_array($value)) {
                if (self::paramsContainDangerousCallable($value)) {
                    return true;
                }
                continue;
            }

            if (is_string($value) && Utils::isDangerousFunction($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicConfig(array &$field, $property, array &$call)
    {
        $params = $call['params'];
        if (is_array($params)) {
            $value = array_shift($params);
            $params = array_shift($params);
        } else {
            $value = $params;
            $params = [];
        }

        $default = $field[$property] ?? null;
        $config = Grav::instance()['config']->get($value, $default);
        if (!empty($field['value_only'])) {
            $config = array_combine($config, $config);
        }

        if (null !== $config) {
            if (!empty($params['append']) && is_array($config) && isset($field[$property]) && is_array($field[$property])) {
                // Combine field and @config-field together.
                $field[$property] += $config;
            } else {
                // Or create/replace field with @config-field.
                $field[$property] = $config;
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicSecurity(array &$field, $property, array &$call)
    {
        if ($property || !empty($field['validate']['ignore'])) {
            return;
        }

        $grav = Grav::instance();
        $actions = (array)$call['params'];

        /** @var UserInterface|null $user */
        $user = $grav['user'] ?? null;
        $success = null !== $user;
        if ($success) {
            $success = $this->resolveActions($user, $actions);
        }
        if (!$success) {
            static::addPropertyRecursive($field, 'validate', ['ignore' => true]);
        }
    }

    /**
     * @param UserInterface|null $user
     * @param array $actions
     * @param string $op
     * @return bool
     */
    protected function resolveActions(?UserInterface $user, array $actions, string $op = 'and')
    {
        if (null === $user) {
            return false;
        }

        $c = $i = count($actions);
        foreach ($actions as $key => $action) {
            if (!is_int($key) && is_array($actions)) {
                $i -= $this->resolveActions($user, $action, $key);
            } elseif ($user->authorize($action)) {
                $i--;
            }
        }

        if ($op === 'and') {
            return $i === 0;
        }

        return $c !== $i;
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicScope(array &$field, $property, array &$call)
    {
        if ($property && $property !== 'ignore') {
            return;
        }

        $scopes = (array)$call['params'];
        $matches = in_array($this->scope, $scopes, true);
        if ($this->scope && $property !== 'ignore') {
            $matches = !$matches;
        }

        if ($matches) {
            static::addPropertyRecursive($field, 'validate', ['ignore' => true]);
            return;
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @return void
     */
    public static function addPropertyRecursive(array &$field, $property, mixed $value)
    {
        if (is_array($value) && isset($field[$property]) && is_array($field[$property])) {
            $field[$property] = array_merge_recursive($field[$property], $value);
        } else {
            $field[$property] = $value;
        }

        if (!empty($field['fields'])) {
            foreach ($field['fields'] as $key => &$child) {
                static::addPropertyRecursive($child, $property, $value);
            }
        }
    }
}
