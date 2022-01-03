<?php declare(strict_types=1);

/**
 * @package    Grav\Framework\Mime
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Mime;

use function in_array;

/**
 * Class to handle mime-types.
 */
class MimeTypes
{
    /** @var array */
    protected $extensions;
    /** @var array */
    protected $mimes;

    /**
     * Create a new mime types instance with the given mappings.
     *
     * @param array $mimes An associative array containing ['ext' => ['mime/type', 'mime/type2']]
     */
    public static function createFromMimes(array $mimes): self
    {
        $extensions = [];
        foreach ($mimes as $ext => $list) {
            foreach ($list as $mime) {
                $list = $extensions[$mime] ?? [];
                if (!in_array($ext, $list, true)) {
                    $list[] = $ext;
                    $extensions[$mime] = $list;
                }
            }
        }

        return new static($extensions, $mimes);
    }

    /**
     * @param string $extension
     * @return string|null
     */
    public function getMimeType(string $extension): ?string
    {
        $extension = $this->cleanInput($extension);

        return $this->mimes[$extension][0] ?? null;
    }

    /**
     * @param string $mime
     * @return string|null
     */
    public function getExtension(string $mime): ?string
    {
        $mime = $this->cleanInput($mime);

        return $this->extensions[$mime][0] ?? null;
    }

    /**
     * @param string $extension
     * @return array
     */
    public function getMimeTypes(string $extension): array
    {
        $extension = $this->cleanInput($extension);

        return $this->mimes[$extension] ?? [];
    }

    /**
     * @param string $mime
     * @return array
     */
    public function getExtensions(string $mime): array
    {
        $mime = $this->cleanInput($mime);

        return $this->extensions[$mime] ?? [];
    }

    /**
     * @param string $input
     * @return string
     */
    protected function cleanInput(string $input): string
    {
        return strtolower(trim($input));
    }

    /**
     * @param array $extensions
     * @param array $mimes
     */
    protected function __construct(array $extensions, array $mimes)
    {
        $this->extensions = $extensions;
        $this->mimes = $mimes;
    }
}
