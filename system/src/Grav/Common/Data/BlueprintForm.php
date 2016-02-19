<?php
namespace Grav\Common\Data;

use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 */
class BlueprintForm implements \ArrayAccess, ExportInterface
{
    use Constructor, NestedArrayAccessWithGetters, Export;

    /**
     * @var array
     */
    protected $items;

    /**
     * Extend blueprint with another blueprint.
     *
     * @param BlueprintForm|array $extends
     * @param bool $append
     */
    public function extend($extends, $append = false)
    {
        if ($extends instanceof BlueprintForm) {
            $extends = $extends->toArray();
        }

        $blueprints = $append ? $this->items : $extends;
        $appended = $append ? $extends : $this->items;
        $bref_stack = array(&$blueprints);
        $head_stack = array($appended);

        do {
            end($bref_stack);
            $bref = &$bref_stack[key($bref_stack)];
            $head = array_pop($head_stack);
            unset($bref_stack[key($bref_stack)]);

            foreach (array_keys($head) as $key) {
                if (isset($key, $bref[$key]) && is_array($bref[$key]) && is_array($head[$key])) {
                    $bref_stack[] = &$bref[$key];
                    $head_stack[] = $head[$key];
                } else {
                    $bref = array_merge($bref, array($key => $head[$key]));
                }
            }
        } while (count($head_stack));

        $this->items = $blueprints;
    }

    /**
     * Get blueprints by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->resolve('this.is.my.nested.variable');
     * returns ['this.is.my', 'nested.variable']
     *
     * @param array  $path
     * @param string  $separator
     * @return array
     */
    public function resolve(array $path, $separator = '.')
    {
        $fields = false;
        $parts = [];
        $current = $this['form.fields'];
        $result = [null, null, null];

        while (($field = current($path)) !== null) {
            if (!$fields && isset($current['fields'])) {
                if (!empty($current['array'])) {
                    $result = [$current, $parts, $path ? implode($separator, $path) : null];
                    // Skip item offset.
                    $parts[] = array_shift($path);
                }

                $current = $current['fields'];
                $fields = true;

            } elseif (isset($current[$field])) {
                $parts[] = array_shift($path);
                $current = $current[$field];
                $fields = false;

            } elseif (isset($current['.' . $field])) {
                $parts[] = array_shift($path);
                $current = $current['.' . $field];
                $fields = false;

            } else {
                break;
            }
        }

        return $result;
    }
}
