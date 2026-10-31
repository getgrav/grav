<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use BadMethodCallException;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use RuntimeException;
use function get_class;
use function is_array;
use function method_exists;

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

        // Attributes set on the medium before it was linked (loading, decoding,
        // class, id, ...) describe the image, not the anchor, so carry them over the
        // reset below and onto the image we wrap. getgrav/grav#4282.
        $inherited = $medium->getAttributes();

        $source = $medium->reset()->thumbnail('auto')->display('thumbnail');
        if (!$source instanceof MediaObjectInterface) {
            throw new RuntimeException('Media has no thumbnail set');
        }

        foreach ($inherited as $name => $value) {
            $source->attribute($name, $value);
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
     * Check the wrapper and the medium to which it forwards calls.
     *
     * @param string $method
     * @return bool
     */
    public function hasMethod(string $method): bool
    {
        return method_exists($this, $method) || method_exists($this->source, $method);
    }

    /**
     * Forward the call to the source element
     *
     * @param string $method
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function __call($method, mixed $args)
    {
        $object = $this->source;

        // Forward plain method names using the same dispatch as media actions.
        if (!method_exists($object, $method) && !method_exists($object, '__call')) {
            throw new BadMethodCallException($object::class . '::' . $method . '() not found.');
        }

        $object = $object->{$method}(...$args);
        if (!$object instanceof MediaLinkInterface) {
            // Don't start nesting links, if user has multiple link calls in his
            // actions, we will drop the previous links.
            return $this;
        }

        $this->source = $object;

        return $object;
    }
}
