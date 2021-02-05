<?php

/**
 * @package    Grav\Framework\ContentBlock
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\ContentBlock;

use Exception;
use Grav\Framework\Compat\Serializable;
use InvalidArgumentException;
use RuntimeException;
use function get_class;

/**
 * Class to create nested blocks of content.
 *
 * $innerBlock = ContentBlock::create();
 * $innerBlock->setContent('my inner content');
 * $outerBlock = ContentBlock::create();
 * $outerBlock->setContent(sprintf('Inside my outer block I have %s.', $innerBlock->getToken()));
 * $outerBlock->addBlock($innerBlock);
 * echo $outerBlock;
 *
 * @package Grav\Framework\ContentBlock
 */
class ContentBlock implements ContentBlockInterface
{
    use Serializable;

    /** @var int */
    protected $version = 1;
    /** @var string */
    protected $id;
    /** @var string */
    protected $tokenTemplate = '@@BLOCK-%s@@';
    /** @var string */
    protected $content = '';
    /** @var array */
    protected $blocks = [];
    /** @var string */
    protected $checksum;
    /** @var bool */
    protected $cached = true;

    /**
     * @param string|null $id
     * @return static
     */
    public static function create($id = null)
    {
        return new static($id);
    }

    /**
     * @param array $serialized
     * @return ContentBlockInterface
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $serialized)
    {
        try {
            $type = $serialized['_type'] ?? null;
            $id = $serialized['id'] ?? null;

            if (!$type || !$id || !is_a($type, ContentBlockInterface::class, true)) {
                throw new InvalidArgumentException('Bad data');
            }

            /** @var ContentBlockInterface $instance */
            $instance = new $type($id);
            $instance->build($serialized);
        } catch (Exception $e) {
            throw new InvalidArgumentException(sprintf('Cannot unserialize Block: %s', $e->getMessage()), $e->getCode(), $e);
        }

        return $instance;
    }

    /**
     * Block constructor.
     *
     * @param string|null $id
     */
    public function __construct($id = null)
    {
        $this->id = $id ? (string) $id : $this->generateId();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return sprintf($this->tokenTemplate, $this->getId());
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $blocks = [];
        /** @var ContentBlockInterface $block */
        foreach ($this->blocks as $block) {
            $blocks[$block->getId()] = $block->toArray();
        }

        $array = [
            '_type' => get_class($this),
            '_version' => $this->version,
            'id' => $this->id,
            'cached' => $this->cached
        ];

        if ($this->checksum) {
            $array['checksum'] = $this->checksum;
        }

        if ($this->content) {
            $array['content'] = $this->content;
        }

        if ($blocks) {
            $array['blocks'] = $blocks;
        }

        return $array;
    }

    /**
     * @return string
     */
    public function toString()
    {
        if (!$this->blocks) {
            return (string) $this->content;
        }

        $tokens = [];
        $replacements = [];
        foreach ($this->blocks as $block) {
            $tokens[] = $block->getToken();
            $replacements[] = $block->toString();
        }

        return str_replace($tokens, $replacements, (string) $this->content);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (Exception $e) {
            return sprintf('Error while rendering block: %s', $e->getMessage());
        }
    }

    /**
     * @param array $serialized
     * @return void
     * @throws RuntimeException
     */
    public function build(array $serialized)
    {
        $this->checkVersion($serialized);

        $this->id = $serialized['id'] ?? $this->generateId();
        $this->checksum = $serialized['checksum'] ?? null;
        $this->cached = $serialized['cached'] ?? null;

        if (isset($serialized['content'])) {
            $this->setContent($serialized['content']);
        }

        $blocks = isset($serialized['blocks']) ? (array) $serialized['blocks'] : [];
        foreach ($blocks as $block) {
            $this->addBlock(self::fromArray($block));
        }
    }

    /**
     * @return bool
     */
    public function isCached()
    {
        if (!$this->cached) {
            return false;
        }

        foreach ($this->blocks as $block) {
            if (!$block->isCached()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return $this
     */
    public function disableCache()
    {
        $this->cached = false;

        return $this;
    }

    /**
     * @param string $checksum
     * @return $this
     */
    public function setChecksum($checksum)
    {
        $this->checksum = $checksum;

        return $this;
    }

    /**
     * @return string
     */
    public function getChecksum()
    {
        return $this->checksum;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param ContentBlockInterface $block
     * @return $this
     */
    public function addBlock(ContentBlockInterface $block)
    {
        $this->blocks[$block->getId()] = $block;

        return $this;
    }

    /**
     * @return array
     */
    final public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array $data
     * @return void
     */
    final public function __unserialize(array $data): void
    {
        $this->build($data);
    }

    /**
     * @return string
     */
    protected function generateId()
    {
        return uniqid('', true);
    }

    /**
     * @param array $serialized
     * @return void
     * @throws RuntimeException
     */
    protected function checkVersion(array $serialized)
    {
        $version = isset($serialized['_version']) ? (int) $serialized['_version'] : 1;
        if ($version !== $this->version) {
            throw new RuntimeException(sprintf('Unsupported version %s', $version));
        }
    }
}
