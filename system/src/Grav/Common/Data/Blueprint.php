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
     * Whether this blueprint's own field definitions came from a source the site
     * author controls — a blueprint file on disk, or PHP that declared itself
     * trusted. Both are the author speaking: a plugin shipping a `blueprints.yaml`
     * also ships code Grav autoloads, so honouring its `data-*@` directives grants
     * no capability it did not already have.
     *
     * @var bool
     */
    protected $trusted = true;

    /**
     * Per-field-path trust for directives contributed by {@see self::extend()},
     * keyed exactly like {@see BlueprintForm::$dynamic}. Lets a blueprint that is
     * mostly file-defined but has page-authored fields spliced into it keep its own
     * providers working while refusing the spliced ones, instead of the whole
     * object having to be demoted.
     *
     * @var array<string,bool>
     */
    protected $dynamicTrust = [];

    /**
     * Whether we are inside {@see self::load()}.
     *
     * Everything the loader merges through extend() is file content:
     * BlueprintForm::load() makes the first file `$this->items` and hands every
     * remaining one to extend() as a bare array — including the blueprint's OWN
     * content whenever it resolved a parent, because doLoad() returns
     * [...parents, ownContent] and the parent is what gets shifted off the
     * front. Those arrays have exactly the provenance of the file that became
     * `$this->items`, so they must not fall through to the page-frontmatter
     * default. Without this, every blueprint using `extends@` had its own
     * `data-*@` directives refused (getgrav/grav-plugin-email#193).
     *
     * @var bool
     */
    protected $loadingFiles = false;

    /**
     * @param string|string[]|null $filename
     * @param array $items
     * @param bool|null $trusted  Declare the provenance of `$items`. Null infers it:
     *                            a blueprint loaded from files is trusted, items
     *                            injected as data are not. Pass true from code that
     *                            builds a blueprint out of its own PHP array.
     */
    public function __construct($filename = null, array $items = [], ?bool $trusted = null)
    {
        parent::__construct($filename, $items);

        // BlueprintForm::load() skips file loading entirely when $items is non-empty,
        // so a blueprint is either file-derived or data-derived, never both. Injected
        // items with no declaration are the page-frontmatter shape (the form plugin's
        // Form::getBlueprint(), FlexForm, FlexDirectoryForm) — infer untrusted so a
        // path nobody enumerated fails safe rather than open.
        $this->trusted = $trusted ?? ($items === []);
    }

    /**
     * Whether this blueprint's own definitions are author-controlled.
     *
     * @return bool
     */
    public function isTrusted(): bool
    {
        return $this->trusted;
    }

    /**
     * Load blueprint from its files.
     *
     * Marks the loader as active so extend() can tell a file-derived merge from
     * a runtime one. A blueprint with `extends@`, `@parent`, or an explicit
     * `$extends` argument is loaded parent-first, which means its own fields
     * arrive through extend() rather than as `$this->items`.
     *
     * @param string|array|null $extends
     * @return $this
     */
    public function load($extends = null)
    {
        $previous = $this->loadingFiles;
        $this->loadingFiles = true;

        try {
            return parent::load($extends);
        } finally {
            $this->loadingFiles = $previous;
        }
    }

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
                // Carry provenance to the sink. Reaches both dynamicData() below and
                // every handler registered via addDynamicHandler() — notably Flex's,
                // which routes `data` through FlexDirectory::dynamicDataField().
                $call['trusted'] = $this->dynamicTrust[$key] ?? $this->trusted;

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
     * @param bool|null $trusted  Declare the provenance of `$extends`. Null infers it:
     *                            another blueprint contributes its own trust, a bare
     *                            array contributes none. Pass true from a plugin that
     *                            builds the array in its own PHP.
     * @return $this
     */
    public function extend($extends, $append = false, ?bool $trusted = null)
    {
        // Attribute the incoming directives before merging, while we can still tell
        // which fields this source contributes. Afterwards deepInit() re-walks the
        // merged tree and can no longer distinguish one source from another.
        if ($extends instanceof BlueprintForm) {
            // A blueprint loaded from files carries file provenance even though it
            // arrives through a PHP call — which is what every first-party plugin's
            // onBlueprintCreated handler does.
            $incomingTrust = $trusted ?? (!$extends instanceof self || $extends->isTrusted());
            $incoming = $extends->toArray();
        } else {
            // A bare array is page-authored by default, but not when the loader
            // is the one handing it over: that array was read off disk a moment
            // ago and shares this blueprint's provenance. (getgrav/grav-plugin-email#193)
            $incomingTrust = $trusted ?? ($this->loadingFiles && $this->trusted);
            $incoming = (array) $extends;
        }

        $paths = $this->dynamicPathsOf($incoming);

        if (null === $paths) {
            // Could not attribute the incoming fields. Fall back to demoting the whole
            // blueprint rather than letting unattributed directives inherit trust.
            $this->trusted = $this->trusted && $incomingTrust;
        } else {
            foreach ($paths as $path) {
                $this->dynamicTrust[$path] = $incomingTrust;
            }
        }

        parent::extend($extends, $append);

        $this->deepInit($this->items);

        return $this;
    }

    /**
     * Field paths in `$items` that carry a dynamic directive, keyed as
     * {@see BlueprintForm::$dynamic} keys them.
     *
     * Parses a throwaway blueprint rather than reimplementing the walk, so the paths
     * are guaranteed to line up with the ones init() dispatches on.
     *
     * @param array $items
     * @return string[]|null  Null when the source could not be parsed.
     */
    private function dynamicPathsOf(array $items): ?array
    {
        if (!$items) {
            return [];
        }

        try {
            $probe = new self(null, $items, true);
            $probe->deepInit($probe->items);

            return array_keys($probe->dynamic);
        } catch (\Throwable) {
            return null;
        }
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
        if (!self::isSafeDynamicCall($function, $params, (bool)($call['trusted'] ?? false))) {
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
     * `$trusted` says the directive came from a blueprint file on disk, or from PHP
     * that declared itself as the author. Those callers skip the allowlist: the
     * allowlist exists to stop a *page-authored* form from naming an arbitrary static
     * method, and a plugin that can ship a blueprint can already ship code. It
     * defaults to false so any caller that has not been taught to pass provenance —
     * including third-party ones — keeps the strict behaviour.
     *
     * @param mixed $function
     * @param array $params
     * @param bool $trusted
     * @return bool
     */
    public static function isSafeDynamicCall($function, array $params, bool $trusted = false): bool
    {
        // A `data-*@` callable may arrive as a `[Class, method]` pair, not only
        // as a `Class::method` string. PHP honours the array form in is_callable()
        // and call_user_func_array() (the sink in FlexDirectory::dynamicDataField),
        // so without normalising it here an attacker-supplied pair slips past both
        // the allowlist and the denylist below and reaches the sink unchecked — e.g.
        // [GPM\Installer::class, 'unZip'] to unpack an uploaded PHP shell into the
        // docroot. Fold a two-string pair into its `Class::method` string so it is
        // vetted like any other provider, and refuse any other array shape (an
        // instance/closure pair cannot come from a blueprint and is not a provider
        // we can vet). (GHSA-r94f-hx44-8jqf)
        if (is_array($function)) {
            if (count($function) === 2 && isset($function[0], $function[1])
                && is_string($function[0]) && is_string($function[1])) {
                $function = $function[0] . '::' . $function[1];
            } else {
                return false;
            }
        }

        if (is_string($function) && str_contains($function, '::')) {
            // `Class::method` providers skip the bare-function denylist below
            // (Utils::isDangerousFunction already rejects any string containing
            // `:` or `\`). Without a positive gate that carve-out let a page-edit
            // account name ANY public static method as a dynamic-field provider
            // and reach file-disclosure / file-write / secret-read gadgets
            // (Utils::download, Folder::copy/delete, Security::getNonceKey, ...).
            // Permit only the known-safe option providers on the allowlist — unless
            // the directive is author-controlled, in which case the allowlist is not
            // the boundary and only the trampoline guard below still applies.
            // (GHSA-7pgq-cr25-xvc8, GHSA-cxv3-5jj3-cpgr)
            if (!$trusted && !isset(self::$allowedDynamicCallables[strtolower(ltrim($function, '\\'))])) {
                return false;
            }

            return !self::paramsContainDangerousCallable($params);
        }

        // Symmetric with the `Class::method` branch above: a bare function is
        // gated on the same positive allowlist, not a denylist. A denylist can
        // never be complete — `error_log` is an arbitrary-file-append primitive,
        // `stream_socket_client` an SSRF one, and neither is in
        // Utils::isDangerousFunction() — so any function the denylist forgot ran.
        // No first-party blueprint uses a bare-function provider (every provider
        // is a `Class::method` on the allowlist), so refuse the branch unless a
        // plugin registered the function via addAllowedDynamicCallable().
        // Provenance deliberately does NOT lift this: nothing legitimate needs a
        // bare function, and the gadgets reachable through one (error_log as a
        // file-append primitive, stream_socket_client as an SSRF one) are worth
        // keeping behind an explicit registration even for trusted callers.
        // (GHSA-f8wv-xp27-6gq7, follow-up to GHSA-7pgq-cr25-xvc8)
        if (!is_string($function)
            || !isset(self::$allowedDynamicCallables[strtolower(ltrim($function, '\\'))])) {
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
