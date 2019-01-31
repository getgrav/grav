<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Flex\FlexForm;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Interface FlexObjectInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexObjectInterface extends NestedObjectInterface, \ArrayAccess
{
    /**
     * @param array $elements
     * @param string $key
     * @param FlexDirectory $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements, $key, FlexDirectory $type);

    /**
     * Returns the directory where the object belongs into.
     *
     * @return FlexDirectory
     */
    public function getFlexDirectory() : FlexDirectory;

    /**
     * Returns a unique key for this object.
     *
     * NOTE: Please do not override the method!
     *
     * @return string
     */
    public function getFlexKey();

    /**
     * Returns a storage key which is used for figuring out the filename or database id.
     *
     * @return string
     */
    public function getStorageKey();

    /**
     * Returns a cache key which is used for caching the object.
     *
     * @return string
     */
    public function getCacheKey();

    /**
     * Returns cache checksum for the object. If checksum changes, cache gets invalided.
     *
     * @return string
     */
    public function getCacheChecksum();

    /**
     * Returns a last updated timestamp for the object.
     *
     * @return int
     */
    public function getTimestamp() : int;

    /**
     * Returns true if the object exists in the storage.
     *
     * @return bool
     */
    public function exists();

    /**
     * Returns the blueprint for the object.
     *
     * @param string $name
     * @return Blueprint
     */
    public function getBlueprint(string $name = '');

    /**
     * Returns a form instance for the object.
     *
     * @param string $name
     * @param array|null $form
     * @return FlexForm
     */
    public function getForm(string $name = '', array $form = null);

    /**
     * @param string $layout
     * @param array $context
     * @return HtmlBlock
     * @throws \Exception
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    public function render($layout = null, array $context = []);
}
