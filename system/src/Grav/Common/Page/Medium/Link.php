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
    protected array $attributes;
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

        $source = $medium->reset()->thumbnail()->display('thumbnail');
        if (!$source instanceof MediaObjectInterface) {
            throw new RuntimeException('Media has no thumbnail set');
        }

        $source->set('linked', true);

        $this->source = $source;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'attributes' => $this->attributes,
            'source' => $this->source
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->attributes = $data['attributes'];
        $this->source = $data['source'];
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
     * @phpstan-impure
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $id = null, $reset = true): array
    {
        $innerElement = $this->source->parsedownElement($title, $alt, $class, $id, $reset);

        return [
            'name' => 'a',
            'attributes' => $this->attributes,
            'handler' => 'element',
            'text' => $innerElement
        ];
    }

    /**
     * Forward the call to the source element
     *
     * @param string $method
     * @param array $args
     * @return MediaObjectInterface|MediaLinkInterface
     * @phpstan-impure
     */
    public function __call(string $method, array $args)
    {
        $object = $this->source;
        if (!(is_callable([$object, $method]) || $object->isAction($method))) {
            throw new BadMethodCallException(get_class($object) . '::' . $method . '() not found.');
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
