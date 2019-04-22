<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Framework\Interfaces\RenderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Interface FormInterface
 * @package Grav\Framework\Form
 */
interface FormInterface extends RenderInterface, \Serializable
{
    /**
     * Get HTML id="..." attribute.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Sets HTML id="" attribute.
     *
     * @param string $id
     */
    public function setId(string $id): void;

    /**
     * Get unique id for the current form instance. By default regenerated on every page reload.
     *
     * This id is used to load the saved form state, if available.
     *
     * @return string
     */
    public function getUniqueId(): string;

    /**
     * Sets unique form id.
     *
     * @param string $uniqueId
     */
    public function setUniqueId(string $uniqueId): void;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * Get form name.
     *
     * @return string
     */
    public function getFormName(): string;

    /**
     * Get nonce name.
     *
     * @return string
     */
    public function getNonceName(): string;

    /**
     * Get nonce action.
     *
     * @return string
     */
    public function getNonceAction(): string;

    /**
     * Get the nonce value for a form
     *
     * @return string
     */
    public function getNonce(): string;

    /**
     * Get task for the form if set in blueprints.
     *
     * @return string
     */
    public function getTask(): string;

    /**
     * Get form action (URL). If action is empty, it points to the current page.
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * Get current data passed to the form.
     *
     * @return Data|object
     */
    public function getData();

    /**
     * Get files which were passed to the form.
     *
     * @return array|UploadedFileInterface[]
     */
    public function getFiles(): array;

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
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function handleRequest(ServerRequestInterface $request): FormInterface;

    /**
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return $this
     */
    public function submit(array $data, array $files = null): FormInterface;

    /**
     * @return bool
     */
    public function isValid(): bool;

    /**
     * @return string
     */
    public function getError(): ?string;

    /**
     * @return array
     */
    public function getErrors(): array;

    /**
     * @return bool
     */
    public function isSubmitted(): bool;

    /**
     * Reset form.
     */
    public function reset(): void;

    /**
     * Get form fields as an array.
     *
     * Note: Used in form fields.
     *
     * @return array
     */
    public function getFields(): array;

    /**
     * Get blueprint used in the form.
     *
     * @return Blueprint
     */
    public function getBlueprint(): Blueprint;
}
