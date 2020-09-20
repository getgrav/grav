<?php
namespace Grav\Common\Page\Interfaces;

/**
 * Interface PageFormInterface
 * @package Grav\Common\Page\Interfaces
 */
interface PageFormInterface
{
    /**
     * Return all the forms which are associated to this page.
     *
     * Forms are returned as [name => blueprint, ...], where blueprint follows the regular form blueprint format.
     *
     * @return array
     */
    //public function getForms(): array;

    /**
     * Add forms to this page.
     *
     * @param array $new
     * @return $this
     */
    public function addForms(array $new/*, $override = true*/);

    /**
     * Alias of $this->getForms();
     *
     * @return array
     */
    public function forms();//: array;
}
