<?php

namespace Grav\Common\Page\Traits;

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;
use function is_array;

/**
 * Trait PageFormTrait
 * @package Grav\Common\Page\Traits
 */
trait PageFormTrait
{
    /** @var array|null */
    private $_forms;

    /**
     * Return all the forms which are associated to this page.
     *
     * Forms are returned as [name => blueprint, ...], where blueprint follows the regular form blueprint format.
     *
     * @return array
     */
    public function getForms(): array
    {
        if (null === $this->_forms) {
            $header = $this->header();

            // Call event to allow filling the page header form dynamically (e.g. use case: Comments plugin)
            $grav = Grav::instance();
            $grav->fireEvent('onFormPageHeaderProcessed', new Event(['page' => $this, 'header' => $header]));

            $rules = $header->rules ?? null;
            if (!is_array($rules)) {
                $rules = [];
            }

            $forms = [];

            // First grab page.header.form
            $form = $this->normalizeForm($header->form ?? null, null, $rules);
            if ($form) {
                $forms[$form['name']] = $form;
            }

            // Append page.header.forms (override singular form if it clashes)
            $headerForms = $header->forms ?? null;
            if (is_array($headerForms)) {
                foreach ($headerForms as $name => $form) {
                    $form = $this->normalizeForm($form, $name, $rules);
                    if ($form) {
                        $forms[$form['name']] = $form;
                    }
                }
            }

            $this->_forms = $forms;
        }

        return $this->_forms;
    }

    /**
     * Add forms to this page.
     *
     * @param array $new
     * @param bool $override
     * @return $this
     */
    public function addForms(array $new, $override = true)
    {
        // Initialize forms.
        $this->forms();

        foreach ($new as $name => $form) {
            $form = $this->normalizeForm($form, $name);
            $name = $form['name'] ?? null;
            if ($name && ($override || !isset($this->_forms[$name]))) {
                $this->_forms[$name] = $form;
            }
        }

        return $this;
    }

    /**
     * Alias of $this->getForms();
     *
     * @return array
     */
    public function forms(): array
    {
        return $this->getForms();
    }

    /**
     * @param array|null $form
     * @param string|null $name
     * @param array $rules
     * @return array|null
     */
    protected function normalizeForm($form, $name = null, array $rules = []): ?array
    {
        if (!is_array($form)) {
            return null;
        }

        // Ignore numeric indexes on name.
        if (!$name || (string)(int)$name === (string)$name) {
            $name = null;
        }

        $name = $name ?? $form['name'] ?? $this->slug();

        $formRules = $form['rules'] ?? null;
        if (!is_array($formRules)) {
            $formRules = [];
        }

        return ['name' => $name, 'rules' => $rules + $formRules] + $form;
    }

    abstract public function header($var = null);
    abstract public function slug($var = null);
}
