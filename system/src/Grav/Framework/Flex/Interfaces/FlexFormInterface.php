<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Framework\Route\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FlexForm
 * @package Grav\Framework\Flex
 */
interface FlexFormInterface extends \Serializable
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param string $id
     */
    public function setId(string $id): void;

    /**
     * @return string
     */
    public function getUniqueId(): string;

    /**
     * @param string $uniqueId
     */
    public function setUniqueId(string $uniqueId): void;

    /**
     * @return string
     */
    public function getName(): string;


    /**
     * @return string
     */
    public function getNonceName(): string;

    /**
     * @return string
     */
    public function getNonceAction(): string;

    /**
     * @return string
     */
    public function getAction(): string;

    /**
     * @return Data|FlexObjectInterface
     */
    public function getData();

    /**
     * Get a value from the form.
     *
     * Note: Used in form fields.
     *
     * @param string $name
     * @return mixed
     */
    public function getValue(string $name);

    /**
     * @return UploadedFileInterface[]
     */
    public function getFiles() : array;

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
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return $this
     */
    public function submit(array $data, array $files = null): self;

    /**
     * @return $this
     */
    public function reset(): self;

    /**
     * Note: Used in form fields.
     *
     * @return array
     */
    public function getFields(): array;

    /**
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint;

    /**
     * @return string
     */
    public function getMediaTaskRoute(): string;

    /**
     * @return string
     */
    public function getMediaRoute(): string;
}
