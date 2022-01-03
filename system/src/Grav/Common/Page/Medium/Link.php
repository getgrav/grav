<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use BadMethodCallException;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use RuntimeException;
use function call_user_func_array;
use function get_class;
use function is_array;
use function is_callable;

/**
 * Class Link
 * @package Grav\Common\Page\Medium
 */
class Link implements RenderableInterface, MediaLinkInterface
{
    use ParsedownHtmlTrait;

    /** @var array */
    protected $attributes = [];
    /** @var MediaObjectInterface|MediaLinkInterface */
    protected $source;

    /**
     * Construct.
     * @param array  $attributes
     * @param MediaObjectInterface $medium
     */
    public function __construct(array $attributes, MediaObjectInterface $medium)
    {
        $this->attributes = $attributes;

        $source = $medium->reset()->thumbnail('auto')->display('thumbnail');
        if (!$source instanceof MediaObjectInterface) {
            throw new RuntimeException('Media has no thumbnail set');
        }

        $source->set('linked', true);

        $this->source = $source;
    }

    /**
     * Get an element (is array) that can be rendered by the Parsedown engine
     *
     * @param  string|null  $title
     * @param  string|null  $alt
     * @param  string|null  $class
     * @param  string|null  $id
     * @param  bool $reset
     * @return array
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $id = null, $reset = true)
    {
        $innerElement = $this->source->parsedownElement($title, $alt, $class, $id, $reset);

        return [
            'name' => 'a',
            'attributes' => $this->attributes,
            'handler' => is_array($innerElement) ? 'element' : 'line',
            'text' => $innerElement
        ];
    }

    /**
     * Forward the call to the source element
     *
     * @param string $method
     * @param mixed $args
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function __call($method, $args)
    {
        $object = $this->source;
        $callable = [$object, $method];
        if (!is_callable($callable)) {
            throw new BadMethodCallException(get_class($object) . '::' . $method . '() not found.');
        }

        $object = call_user_func_array($callable, $args);
        if (!$object instanceof MediaLinkInterface) {
            // Don't start nesting links, if user has multiple link calls in his
            // actions, we will drop the previous links.
            return $this;
        }

        $this->source = $object;

        return $object;
    }
}
