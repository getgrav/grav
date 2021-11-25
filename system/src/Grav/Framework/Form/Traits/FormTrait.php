<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form\Traits;

use ArrayAccess;
use Exception;
use FilesystemIterator;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Data\ValidationException;
use Grav\Common\Debugger;
use Grav\Common\Form\FormFlash;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Form\Interfaces\FormFlashInterface;
use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Framework\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use SplFileInfo;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Template;
use Twig\TemplateWrapper;
use function in_array;
use function is_array;
use function is_object;

/**
 * Trait FormTrait
 * @package Grav\Framework\Form
 */
trait FormTrait
{
    use Serializable;

    /** @var string */
    public $status = 'success';
    /** @var string|null */
    public $message;
    /** @var string[] */
    public $messages = [];

    /** @var string */
    private $name;
    /** @var string */
    private $id;
    /** @var bool */
    private $enabled = true;
    /** @var string */
    private $uniqueid;
    /** @var string */
    private $sessionid;
    /** @var bool */
    private $submitted;
    /** @var ArrayAccess|Data|null */
    private $data;
    /** @var array|UploadedFileInterface[] */
    private $files;
    /** @var FormFlashInterface|null */
    private $flash;
    /** @var string */
    private $flashFolder;
    /** @var Blueprint */
    private $blueprint;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uniqueid;
    }

    /**
     * @param string $uniqueId
     * @return void
     */
    public function setUniqueId(string $uniqueId): void
    {
        $this->uniqueid = $uniqueId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getFormName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNonceName(): string
    {
        return 'form-nonce';
    }

    /**
     * @return string
     */
    public function getNonceAction(): string
    {
        return 'form';
    }

    /**
     * @return string
     */
    public function getNonce(): string
    {
        return Utils::getNonce($this->getNonceAction());
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getTask(): string
    {
        return $this->getBlueprint()->get('form/task') ?? '';
    }

    /**
     * @param string|null $name
     * @return mixed
     */
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

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getValue(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
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

            if ((is_array($current) || $current instanceof ArrayAccess) && isset($current[$offset])) {
                $current = $current[$offset];
            } elseif (is_object($current) && isset($current->{$offset})) {
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
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = $grav['debugger'];
            $debugger->addException($e);

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

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->status === 'success';
    }

    /**
     * @return string|null
     */
    public function getError(): ?string
    {
        return !$this->isValid() ? $this->message : null;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return !$this->isValid() ? $this->messages : [];
    }

    /**
     * @return bool
     */
    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * @return bool
     */
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
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            $this->setError($e->getMessage());
        }

        $this->filterData($this->data);

        return $this->isValid();
    }

    /**
     * @param array $data
     * @param UploadedFileInterface[]|null $files
     * @return FormInterface|$this
     */
    public function submit(array $data, array $files = null): FormInterface
    {
        try {
            if ($this->isSubmitted()) {
                throw new RuntimeException('Form has already been submitted');
            }

            $this->data = new Data($data, $this->getBlueprint());
            $this->files = $files ?? [];

            if (!$this->validate()) {
                return $this;
            }

            $this->doSubmit($this->data->toArray(), $this->files);

            $this->submitted = true;
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            $this->setError($e->getMessage());
        }

        return $this;
    }

    /**
     * @return void
     */
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

    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->getBlueprint()->fields();
    }

    /**
     * @return array
     */
    public function getButtons(): array
    {
        return $this->getBlueprint()->get('form/buttons') ?? [];
    }

    /**
     * @return array
     */
    public function getTasks(): array
    {
        return $this->getBlueprint()->get('form/tasks') ?? [];
    }

    /**
     * @return Blueprint
     */
    abstract public function getBlueprint(): Blueprint;

    /**
     * Get form flash object.
     *
     * @return FormFlashInterface
     */
    public function getFlash()
    {
        if (null === $this->flash) {
            $grav = Grav::instance();
            $config = [
                'session_id' => $this->getSessionId(),
                'unique_id' => $this->getUniqueId(),
                'form_name' => $this->getName(),
                'folder' => $this->getFlashFolder()
            ];


            $this->flash = new FormFlash($config);
            $this->flash->setUrl($grav['uri']->url)->setUser($grav['user'] ?? null);
        }

        return $this->flash;
    }

    /**
     * Get all available form flash objects for this form.
     *
     * @return FormFlashInterface[]
     */
    public function getAllFlashes(): array
    {
        $folder = $this->getFlashFolder();
        if (!$folder || !is_dir($folder)) {
            return [];
        }

        $name = $this->getName();

        $list = [];
        /** @var SplFileInfo $file */
        foreach (new FilesystemIterator($folder) as $file) {
            $uniqueId = $file->getFilename();
            $config = [
                'session_id' => $this->getSessionId(),
                'unique_id' => $uniqueId,
                'form_name' => $name,
                'folder' => $this->getFlashFolder()
            ];
            $flash = new FormFlash($config);
            if ($flash->exists() && $flash->getFormName() === $name) {
                $list[] = $flash;
            }
        }

        return $list;
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

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->doSerialize();
    }

    /**
     * @return array
     */
    final public function __serialize(): array
    {
        return $this->doSerialize();
    }

    /**
     * @param array $data
     * @return void
     */
    final public function __unserialize(array $data): void
    {
        $this->doUnserialize($data);
    }

    protected function getSessionId(): string
    {
        if (null === $this->sessionid) {
            /** @var Grav $grav */
            $grav = Grav::instance();

            /** @var SessionInterface|null $session */
            $session = $grav['session'] ?? null;

            $this->sessionid = $session ? ($session->getId() ?? '') : '';
        }

        return $this->sessionid;
    }

    /**
     * @param string $sessionId
     * @return void
     */
    protected function setSessionId(string $sessionId): void
    {
        $this->sessionid = $sessionId;
    }

    /**
     * @return void
     */
    protected function unsetFlash(): void
    {
        $this->flash = null;
    }

    /**
     * @return string|null
     */
    protected function getFlashFolder(): ?string
    {
        $grav = Grav::instance();

        /** @var UserInterface|null $user */
        $user = $grav['user'] ?? null;
        if (null !== $user && $user->exists()) {
            $username = $user->username;
            $mediaFolder = $user->getMediaFolder();
        } else {
            $username = null;
            $mediaFolder = null;
        }
        $session = $grav['session'] ?? null;
        $sessionId = $session ? $session->getId() : null;

        // Fill template token keys/value pairs.
        $dataMap = [
            '[FORM_NAME]' => $this->getName(),
            '[SESSIONID]' => $sessionId ?? '!!',
            '[USERNAME]' => $username ?? '!!',
            '[USERNAME_OR_SESSIONID]' => $username ?? $sessionId ?? '!!',
            '[ACCOUNT]' => $mediaFolder ?? '!!'
        ];

        $flashLookupFolder = $this->getFlashLookupFolder();

        $path = str_replace(array_keys($dataMap), array_values($dataMap), $flashLookupFolder);

        // Make sure we only return valid paths.
        return strpos($path, '!!') === false ? rtrim($path, '/') : null;
    }

    /**
     * @return string
     */
    protected function getFlashLookupFolder(): string
    {
        if (null === $this->flashFolder) {
            $this->flashFolder = $this->getBlueprint()->get('form/flash_folder') ?? 'tmp://forms/[SESSIONID]';
        }

        return $this->flashFolder;
    }

    /**
     * @param string $folder
     * @return void
     */
    protected function setFlashLookupFolder(string $folder): void
    {
        $this->flashFolder = $folder;
    }

    /**
     * Set a single error.
     *
     * @param string $error
     * @return void
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
     * @return void
     */
    protected function setErrors(array $errors): void
    {
        $this->status = 'error';
        $this->messages = $errors;
    }

    /**
     * @param string $layout
     * @return Template|TemplateWrapper
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
        if (!in_array($method, ['PUT', 'POST', 'PATCH'])) {
            throw new RuntimeException(sprintf('FlexForm: Bad HTTP method %s', $method));
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
            $files
        ];
    }

    /**
     * Validate data and throw validation exceptions if validation fails.
     *
     * @param ArrayAccess|Data|null $data
     * @return void
     * @throws ValidationException
     * @throws Exception
     */
    protected function validateData($data = null): void
    {
        if ($data instanceof Data) {
            $data->validate();
        }
    }

    /**
     * Filter validated data.
     *
     * @param ArrayAccess|Data|null $data
     * @return void
     */
    protected function filterData($data = null): void
    {
        if ($data instanceof Data) {
            $data->filter();
        }
    }

    /**
     * Validate all uploaded files.
     *
     * @param array $files
     * @return void
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
     * @return void
     */
    protected function validateUpload(UploadedFileInterface $file): void
    {
        // Handle bad filenames.
        $filename = $file->getClientFilename();

        if ($filename && !Utils::checkFilename($filename)) {
            $grav = Grav::instance();
            throw new RuntimeException(
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
        if (!is_array($data)) {
            return [];
        }

        // Decode JSON encoded fields and merge them to data.
        if (isset($data['_json'])) {
            $data = array_replace_recursive($data, $this->jsonDecode($data['_json']));
            if (null === $data) {
                throw new RuntimeException(__METHOD__ . '(): Unexpected error');
            }
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
            if (is_array($value)) {
                $value = $this->jsonDecode($value);
            } elseif (trim($value) === '') {
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
     * @return void
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
