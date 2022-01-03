<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages;

use DateTime;
use Exception;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Traits\PageFormTrait;
use Grav\Common\User\Interfaces\UserCollectionInterface;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexTranslateInterface;
use Grav\Framework\Flex\Pages\Traits\PageAuthorsTrait;
use Grav\Framework\Flex\Pages\Traits\PageContentTrait;
use Grav\Framework\Flex\Pages\Traits\PageLegacyTrait;
use Grav\Framework\Flex\Pages\Traits\PageRoutableTrait;
use Grav\Framework\Flex\Pages\Traits\PageTranslateTrait;
use Grav\Framework\Flex\Traits\FlexMediaTrait;
use RuntimeException;
use stdClass;
use function array_key_exists;
use function is_array;

/**
 * Class FlexPageObject
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageObject extends FlexObject implements PageInterface, FlexTranslateInterface
{
    use PageAuthorsTrait;
    use PageContentTrait;
    use PageFormTrait;
    use PageLegacyTrait;
    use PageRoutableTrait;
    use PageTranslateTrait;
    use FlexMediaTrait;

    public const PAGE_ORDER_REGEX = '/^(\d+)\.(.*)$/u';
    public const PAGE_ORDER_PREFIX_REGEX = '/^[0-9]+\./u';

    /** @var array|null */
    protected $_reorder;
    /** @var FlexPageObject|null */
    protected $_originalObject;

    /**
     * Clone page.
     */
    #[\ReturnTypeWillChange]
    public function __clone()
    {
        parent::__clone();

        if (isset($this->header)) {
            $this->header = clone($this->header);
        }
    }

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            // Page Content Interface
            'header' => false,
            'summary' => true,
            'content' => true,
            'value' => false,
            'media' => false,
            'title' => true,
            'menu' => true,
            'visible' => true,
            'published' => true,
            'publishDate' => true,
            'unpublishDate' => true,
            'process' => true,
            'slug' => true,
            'order' => true,
            'id' => true,
            'modified' => true,
            'lastModified' => true,
            'folder' => true,
            'date' => true,
            'dateformat' => true,
            'taxonomy' => true,
            'shouldProcess' => true,
            'isPage' => true,
            'isDir' => true,
            'folderExists' => true,

            // Page
            'isPublished' => true,
            'isOrdered' => true,
            'isVisible' => true,
            'isRoutable' => true,
            'getCreated_Timestamp' => true,
            'getPublish_Timestamp' => true,
            'getUnpublish_Timestamp' => true,
            'getUpdated_Timestamp' => true,
        ] + parent::getCachedMethods();
    }

    /**
     * @param bool $test
     * @return bool
     */
    public function isPublished(bool $test = true): bool
    {
        $time = time();
        $start = $this->getPublish_Timestamp();
        $stop = $this->getUnpublish_Timestamp();

        return $this->published() && $start <= $time && (!$stop || $time <= $stop) === $test;
    }

    /**
     * @param bool $test
     * @return bool
     */
    public function isOrdered(bool $test = true): bool
    {
        return ($this->order() !== false) === $test;
    }

    /**
     * @param bool $test
     * @return bool
     */
    public function isVisible(bool $test = true): bool
    {
        return $this->visible() === $test;
    }

    /**
     * @param bool $test
     * @return bool
     */
    public function isRoutable(bool $test = true): bool
    {
        return $this->routable() === $test;
    }

    /**
     * @return int
     */
    public function getCreated_Timestamp(): int
    {
        return $this->getFieldTimestamp('created_date') ?? 0;
    }

    /**
     * @return int
     */
    public function getPublish_Timestamp(): int
    {
        return $this->getFieldTimestamp('publish_date') ?? $this->getCreated_Timestamp();
    }

    /**
     * @return int|null
     */
    public function getUnpublish_Timestamp(): ?int
    {
        return $this->getFieldTimestamp('unpublish_date');
    }

    /**
     * @return int
     */
    public function getUpdated_Timestamp(): int
    {
        return $this->getFieldTimestamp('updated_date') ?? $this->getPublish_Timestamp();
    }

    /**
     * @inheritdoc
     */
    public function getFormValue(string $name, $default = null, string $separator = null)
    {
        $test = new stdClass();

        $value = $this->pageContentValue($name, $test);
        if ($value !== $test) {
            return $value;
        }

        switch ($name) {
            case 'name':
                return $this->getProperty('template');
            case 'route':
                return $this->hasKey() ? '/' . $this->getKey() : null;
            case 'header.permissions.groups':
                $encoded = json_encode($this->getPermissions());
                if ($encoded === false) {
                    throw new RuntimeException('json_encode(): failed to encode group permissions');
                }

                return json_decode($encoded, true);
        }

        return parent::getFormValue($name, $default, $separator);
    }

    /**
     * Get master storage key.
     *
     * @return string
     * @see FlexObjectInterface::getStorageKey()
     */
    public function getMasterKey(): string
    {
        $key = (string)($this->storage_key ?? $this->getMetaData()['storage_key'] ?? null);
        if (($pos = strpos($key, '|')) !== false) {
            $key = substr($key, 0, $pos);
        }

        return $key;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getCacheKey()
     */
    public function getCacheKey(): string
    {
        return $this->hasKey() ? $this->getTypePrefix() . $this->getFlexType() . '.' . $this->getKey() . '.' . $this->getLanguage() : '';
    }

    /**
     * @param string|null $key
     * @return FlexObjectInterface
     */
    public function createCopy(string $key = null)
    {
        $this->copy();

        return parent::createCopy($key);
    }

    /**
     * @param array|bool $reorder
     * @return FlexObject|FlexObjectInterface
     */
    public function save($reorder = true)
    {
        return parent::save();
    }

    /**
     * Gets the Page Unmodified (original) version of the page.
     *
     * Assumes that object has been cloned before modifying it.
     *
     * @return FlexPageObject|null The original version of the page.
     */
    public function getOriginal()
    {
        return $this->_originalObject;
    }

    /**
     * Store the Page Unmodified (original) version of the page.
     *
     * Can be called multiple times, only the first call matters.
     *
     * @return void
     */
    public function storeOriginal(): void
    {
        if (null === $this->_originalObject) {
            $this->_originalObject = clone $this;
        }
    }

    /**
     * Get display order for the associated media.
     *
     * @return array
     */
    public function getMediaOrder(): array
    {
        $order = $this->getNestedProperty('header.media_order');

        if (is_array($order)) {
            return $order;
        }

        if (!$order) {
            return [];
        }

        return array_map('trim', explode(',', $order));
    }

    // Overrides for header properties.

    /**
     * Common logic to load header properties.
     *
     * @param string $property
     * @param mixed $var
     * @param callable $filter
     * @return mixed|null
     */
    protected function loadHeaderProperty(string $property, $var, callable $filter)
    {
        // We have to use parent methods in order to avoid loops.
        $value = null === $var ? parent::getProperty($property) : null;
        if (null === $value) {
            $value = $filter($var ?? $this->getProperty('header')->get($property));

            parent::setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = parent::getProperty($property);
            }
        }

        return $value;
    }

    /**
     * Common logic to load header properties.
     *
     * @param string $property
     * @param mixed $var
     * @param callable $filter
     * @return mixed|null
     */
    protected function loadProperty(string $property, $var, callable $filter)
    {
        // We have to use parent methods in order to avoid loops.
        $value = null === $var ? parent::getProperty($property) : null;
        if (null === $value) {
            $value = $filter($var);

            parent::setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = parent::getProperty($property);
            }
        }

        return $value;
    }

    /**
     * @param string $property
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($property, $default = null)
    {
        $method = static::$headerProperties[$property] ?? static::$calculatedProperties[$property] ?? null;
        if ($method && method_exists($this, $method)) {
            return $this->{$method}();
        }

        return parent::getProperty($property, $default);
    }

    /**
     * @param string $property
     * @param mixed $value
     * @return $this
     */
    public function setProperty($property, $value)
    {
        $method = static::$headerProperties[$property] ?? static::$calculatedProperties[$property] ?? null;
        if ($method && method_exists($this, $method)) {
            $this->{$method}($value);

            return $this;
        }

        parent::setProperty($property, $value);

        return $this;
    }

    /**
     * @param string $property
     * @param mixed $value
     * @param string|null $separator
     * @return $this
     */
    public function setNestedProperty($property, $value, $separator = null)
    {
        $separator = $separator ?: '.';
        if (strpos($property, 'header' . $separator) === 0) {
            $this->getProperty('header')->set(str_replace('header' . $separator, '', $property), $value, $separator);

            return $this;
        }

        parent::setNestedProperty($property, $value, $separator);

        return $this;
    }

    /**
     * @param string $property
     * @param string|null $separator
     * @return $this
     */
    public function unsetNestedProperty($property, $separator = null)
    {
        $separator = $separator ?: '.';
        if (strpos($property, 'header' . $separator) === 0) {
            $this->getProperty('header')->undef(str_replace('header' . $separator, '', $property), $separator);

            return $this;
        }

        parent::unsetNestedProperty($property, $separator);

        return $this;
    }

    /**
     * @param array $elements
     * @param bool $extended
     * @return void
     */
    protected function filterElements(array &$elements, bool $extended = false): void
    {
        // Markdown storage conversion to page structure.
        if (array_key_exists('content', $elements)) {
            $elements['markdown'] = $elements['content'];
            unset($elements['content']);
        }

        if (!$extended) {
            $folder = !empty($elements['folder']) ? trim($elements['folder']) : '';

            if ($folder) {
                $order = !empty($elements['order']) ? (int)$elements['order'] : null;
                // TODO: broken
                $elements['storage_key'] = $order ? sprintf('%02d.%s', $order, $folder) : $folder;
            }
        }

        parent::filterElements($elements);
    }

    /**
     * @param string $field
     * @return int|null
     */
    protected function getFieldTimestamp(string $field): ?int
    {
        $date = $this->getFieldDateTime($field);

        return $date ? $date->getTimestamp() : null;
    }

    /**
     * @param string $field
     * @return DateTime|null
     */
    protected function getFieldDateTime(string $field): ?DateTime
    {
        try {
            $value = $this->getProperty($field);
            if (is_numeric($value)) {
                $value = '@' . $value;
            }
            $date = $value ? new DateTime($value) : null;
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            $date = null;
        }

        return $date;
    }

    /**
     * @return UserCollectionInterface|null
     * @internal
     */
    protected function loadAccounts()
    {
        return Grav::instance()['accounts'] ?? null;
    }
}
