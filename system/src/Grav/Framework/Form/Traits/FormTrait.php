<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form\Traits;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Data\ValidationException;
use Grav\Common\Form\FormFlash;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Form\Interfaces\FormInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\TemplateWrapper;

/**
 * Trait FormTrait
 * @package Grav\Framework\Form
 */
trait FormTrait
{
    /** @var string */
    public $status = 'success';
    /** @var string */
    public $message;
    /** @var string[] */
    public $messages = [];

    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var string */
    private $uniqueid;
    /** @var bool */
    private $submitted;
    /** @var Data|object|null */
    private $data;
    /** @var array|UploadedFileInterface[] */
    private $files;
    /** @var FormFlash|null */
    private $flash;
    /** @var Blueprint */
    private $blueprint;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueid;
    }

    public function setUniqueId(string $uniqueId): void
    {
        $this->uniqueid = $uniqueId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFormName(): string
    {
        return $this->name;
    }

    public function getNonceName(): string
    {
        return 'form-nonce';
    }

    public function getNonceAction(): string
    {
        return 'form';
    }

    public function getNonce(): string
    {
        return Utils::getNonce($this->getNonceAction());
    }

    public function getAction(): string
    {
        return '';
    }

    public function getTask(): string
    {
        return $this->getBlueprint()->get('form/task') ?? '';
    }

    public function getData(string $name = null)
    {
        return null !== $name ? $this->data[$name] : $this->data;
    }

    /**
     * @return array|UploadedFileInterface[]
     */
    public function getFiles(): array
    {
        return $this->files ?? [];
    }

    public function getValue(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function getDefaultValue(string $name)
    {
        $path = explode('.', $name) ?: [];
        $offset = array_shift($path) ?? '';

        $current = $this->getDefaultValues();

        if (!isset($current[$offset])) {
            return null;
        }

        $current = $current[$offset];

        while ($path) {
            $offset = array_shift($path);

            if ((\is_array($current) || $current instanceof \ArrayAccess) && isset($current[$offset])) {
                $current = $current[$offset];
            } elseif (\is_object($current) && isset($current->{$offset})) {
                $current = $current->{$offset};
            } else {
                return null;
            }
        };

        return $current;
    }

    /**
     * @return array
     */
    public function getDefaultValues(): array
    {
        return $this->getBlueprint()->getDefaults();
    }

    /**
     * @param ServerRequestInterface $request
     * @return FormInterface|$this
     */
    public function handleRequest(ServerRequestInterface $request): FormInterface
    {
        // Set current form to be active.
        $grav = Grav::instance();
        $forms = $grav['forms'] ?? null;
        if ($forms) {
            $forms->setActiveForm($this);

            /** @var Twig $twig */
            $twig = $grav['twig'];
            $twig->twig_vars['form'] = $this;

        }

        try {
            [$data, $files] = $this->parseRequest($request);

            $this->submit($data, $files);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return FormInterface|$this
     */
    public function setRequest(ServerRequestInterface $request): FormInterface
    {
        [$data, $files] = $this->parseRequest($request);

        $this->data = new Data($data, $this->getBlueprint());
        $this->files = $files;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->status === 'success';
    }

    public function getError(): ?string
    {
        return !$this->isValid() ? $this->message : null;
    }

    public function getErrors(): array
    {
        return !$this->isValid() ? $this->messages : [];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function validate(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        try {
            $this->validateData($this->data);
            $this->validateUploads($this->getFiles());
        } catch (ValidationException $e) {
            $this->setErrors($e->getMessages());
        }  catch (\Exception $e) {
            $this->setError($e->getMessage());
        }

        $this->filterData($this->data);

        return $this->isValid();
    }

    /**
     * @param array $data
     * @param UploadedFileInterface[] $files
     * @return FormInterface|$this
     */
    public function submit(array $data, array $files = null): FormInterface
    {
        try {
            if ($this->isSubmitted()) {
                throw new \RuntimeException('Form has already been submitted');
            }

            $this->data = new Data($data, $this->getBlueprint());
            $this->files = $files ?? [];

            if (!$this->validate()) {
                return $this;
            }

            $this->doSubmit($this->data->toArray(), $this->files);

            $this->submitted = true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }

        return $this;
    }

    public function reset(): void
    {
        // Make sure that the flash object gets deleted.
        $this->getFlash()->delete();

        $this->data = null;
        $this->files = [];
        $this->status = 'success';
        $this->message = null;
        $this->messages = [];
        $this->submitted = false;
        $this->flash = null;
    }

    public function getFields(): array
    {
        return $this->getBlueprint()->fields();
    }

    public function getButtons(): array
    {
        return $this->getBlueprint()['form']['buttons'] ?? [];
    }

    public function getTasks(): array
    {
        return $this->getBlueprint()['form']['tasks'] ?? [];
    }

    abstract public function getBlueprint(): Blueprint;

    /**
     * Implements \Serializable::serialize().
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->doSerialize());
    }

    /**
     * Implements \Serializable::unserialize().
     *
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);

        $this->doUnserialize($data);
    }

    /**
     * Get form flash object.
     *
     * @return FormFlash
     */
    public function getFlash(): FormFlash
    {
        if (null === $this->flash) {
            /** @var Grav $grav */
            $grav = Grav::instance();
            $id = null;

            $user = $grav['user'] ?? null;
            if (isset($user)) {
                $rememberState = $this->getBlueprint()->get('form/remember_state');
                if ($rememberState === 'user') {
                    $id = $user->username;
                }
            }

            // Session Required for flash form
            $session = $grav['session'] ?? null;
            if (isset($session)) {
                // By default store flash by the session id.
                if (null === $id) {
                    $id = $session->getId();
                }


                $this->flash = new FormFlash($id, $this->getUniqueId(), $this->getName());
                $this->flash->setUrl($grav['uri']->url)->setUser($user);
            }
        }

        return $this->flash;
    }

    /**
     * {@inheritdoc}
     * @see FormInterface::render()
     */
    public function render(string $layout = null, array $context = [])
    {
        if (null === $layout) {
            $layout = 'default';
        }

        $grav = Grav::instance();

        $block = HtmlBlock::create();
        $block->disableCache();

        $output = $this->getTemplate($layout)->render(
            ['grav' => $grav, 'config' => $grav['config'], 'block' => $block, 'form' => $this, 'layout' => $layout] + $context
        );

        $block->setContent($output);

        return $block;
    }

    protected function unsetFlash(): void
    {
        $this->flash = null;
    }

    /**
     * Set a single error.
     *
     * @param string $error
     */
    protected function setError(string $error): void
    {
        $this->status = 'error';
        $this->message = $error;
    }

    /**
     * Set all errors.
     *
     * @param array $errors
     */
    protected function setErrors(array $errors): void
    {
        $this->status = 'error';
        $this->messages = $errors;
    }

    /**
     * @param string $layout
     * @return TemplateWrapper
     * @throws LoaderError
     * @throws SyntaxError
     */
    protected function getTemplate($layout)
    {
        $grav = Grav::instance();

        /** @var Twig $twig */
        $twig = $grav['twig'];

        return $twig->twig()->resolveTemplate(
            [
                "forms/{$layout}/form.html.twig",
                'forms/default/form.html.twig'
            ]
        );
    }

    /**
     * Parse PSR-7 ServerRequest into data and files.
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function parseRequest(ServerRequestInterface $request): array
    {
        $method = $request->getMethod();
        if (!\in_array($method, ['PUT', 'POST', 'PATCH'])) {
            throw new \RuntimeException(sprintf('FlexForm: Bad HTTP method %s', $method));
        }

        $body = $request->getParsedBody();
        $data = isset($body['data']) ? $this->decodeData($body['data']) : null;

        $flash = $this->getFlash();
        /*
        if (null !== $data) {
            $flash->setData($data);
            $flash->save();
        }
        */

        $blueprint = $this->getBlueprint();
        $includeOriginal = (bool)($blueprint->form()['images']['original'] ?? null);
        $files = $flash->getFilesByFields($includeOriginal);

        $data = $blueprint->processForm($data ?? [], $body['toggleable_data'] ?? []);

        return [
            $data,
            $files ?? []
        ];
    }

    /**
     * Form submit logic goes here.
     *
     * @param array $data
     * @param array $files
     * @return mixed
     */
    abstract protected function doSubmit(array $data, array $files);

    /**
     * Validate data and throw validation exceptions if validation fails.
     *
     * @param \ArrayAccess $data
     * @throws ValidationException
     * @throws \Exception
     */
    protected function validateData(\ArrayAccess $data): void
    {
        if ($data instanceof Data) {
            $data->validate();
        }
    }

    /**
     * Filter validated data.
     *
     * @param \ArrayAccess $data
     */
    protected function filterData(\ArrayAccess $data): void
    {
        if ($data instanceof Data) {
            $data->filter();
        }
    }

    /**
     * Validate all uploaded files.
     *
     * @param array $files
     */
    protected function validateUploads(array $files): void
    {
        foreach ($files as $file) {
            if (null === $file) {
                continue;
            }
            if ($file instanceof UploadedFileInterface) {
                $this->validateUpload($file);
            } else {
                $this->validateUploads($file);
            }
        }
    }

    /**
     * Validate uploaded file.
     *
     * @param UploadedFileInterface $file
     */
    protected function validateUpload(UploadedFileInterface $file): void
    {
        // Handle bad filenames.
        $filename = $file->getClientFilename();

        if (!Utils::checkFilename($filename)) {
            $grav = Grav::instance();
            throw new \RuntimeException(
                sprintf($grav['language']->translate('PLUGIN_FORM.FILEUPLOAD_UNABLE_TO_UPLOAD', null, true), $filename, 'Bad filename')
            );
        }
    }

    /**
     * Decode POST data
     *
     * @param array $data
     * @return array
     */
    protected function decodeData($data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        // Decode JSON encoded fields and merge them to data.
        if (isset($data['_json'])) {
            $data = array_replace_recursive($data, $this->jsonDecode($data['_json']));
            unset($data['_json']);
        }

        return $data;
    }

    /**
     * Recursively JSON decode POST data.
     *
     * @param  array $data
     * @return array
     */
    protected function jsonDecode(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (\is_array($value)) {
                $value = $this->jsonDecode($value);
            } elseif ($value === '') {
                unset($data[$key]);
            } else {
                $value = json_decode($value, true);
                if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
                    unset($data[$key]);
                    $this->setError("Badly encoded JSON data (for {$key}) was sent to the form");
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    protected function doSerialize(): array
    {
        $data = $this->data instanceof Data ? $this->data->toArray() : null;

        return [
            'name' => $this->name,
            'id' => $this->id,
            'uniqueid' => $this->uniqueid,
            'submitted' => $this->submitted,
            'status' => $this->status,
            'message' => $this->message,
            'messages' => $this->messages,
            'data' => $data,
            'files' => $this->files,
        ];
    }

    /**
     * @param array $data
     */
    protected function doUnserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->id = $data['id'];
        $this->uniqueid = $data['uniqueid'];
        $this->submitted = $data['submitted'] ?? false;
        $this->status = $data['status'] ?? 'success';
        $this->message = $data['message'] ?? null;
        $this->messages = $data['messages'] ?? [];
        $this->data = isset($data['data']) ? new Data($data['data'], $this->getBlueprint()) : null;
        $this->files = $data['files'] ?? [];
    }
}
