<?php

/**
 * @package    Grav\Framework\ContentBlock
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\ContentBlock;

use Serializable;

/**
 * ContentBlock Interface
 * @package Grav\Framework\ContentBlock
 */
interface ContentBlockInterface extends Serializable
{
    /**
     * @param string|null $id
     * @return static
     */
    public static function create($id = null);

    /**
     * @param array $serialized
     * @return ContentBlockInterface
     */
    public static function fromArray(array $serialized);

    /**
     * @param string|null $id
     */
    public function __construct($id = null);

    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getToken();

    /**
     * @return array
     */
    public function toArray();

    /**
     * @return string
     */
    public function toString();

    /**
     * @return string
     */
    public function __toString();

    /**
     * @param array $serialized
     * @return void
     */
    public function build(array $serialized);

    /**
     * @param string $checksum
     * @return $this
     */
    public function setChecksum($checksum);

    /**
     * @return string
     */
    public function getChecksum();

    /**
     * @param string $content
     * @return $this
     */
    public function setContent($content);

    /**
     * @param ContentBlockInterface $block
     * @return $this
     */
    public function addBlock(ContentBlockInterface $block);
}
