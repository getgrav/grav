<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Route\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
interface FlexFormInterface extends \Serializable, FormInterface
{
    /**
     * @return FlexObjectInterface
     */
    public function getObject(): FlexObjectInterface;

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function handleRequest(ServerRequestInterface $request): self;

    /**
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return $this
     */
    public function submit(array $data, array $files = null): self;

    /**
     * @return bool
     */
    public function isValid(): bool;

    /**
     * @return array
     */
    public function getErrors(): array;

    /**
     * @return bool
     */
    public function isSubmitted(): bool;

    /**
     * @return string
     */
    public function getMediaTaskRoute(): string;

    /**
     * @return string
     */
    public function getMediaRoute(): string;

    /**
     * @return Route|null
     */
    public function getFileUploadAjaxRoute(): ?Route;

    /**
     * @param $field
     * @param $filename
     * @return Route|null
     */
    public function getFileDeleteAjaxRoute($field, $filename): ?Route;
}
