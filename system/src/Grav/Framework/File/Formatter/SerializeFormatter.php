<?php
/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

class SerializeFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    /**
     * IniFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config + [
                'file_extension' => '.ser'
            ];
    }

    /**
     * @deprecated 1.5 Use $formatter->getDefaultFileExtension() instead.
     */
    public function getFileExtension()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use getDefaultFileExtension() method instead', E_USER_DEPRECATED);

        return $this->getDefaultFileExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultFileExtension()
    {
        $extensions = $this->getSupportedFileExtensions();

        return (string) reset($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFileExtensions()
    {
        return (array) $this->config['file_extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return serialize($this->preserveLines($data, ["\n", "\r"], ['\\n', '\\r']));
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data)
    {
        $decoded = @unserialize($data);

        if ($decoded === false) {
            throw new \RuntimeException('Decoding serialized data failed');
        }

        return $this->preserveLines($decoded, ['\\n', '\\r'], ["\n", "\r"]);
    }

    /**
     * Preserve new lines, recursive function.
     *
     * @param mixed $data
     * @param array $search
     * @param array $replace
     * @return mixed
     */
    protected function preserveLines($data, $search, $replace)
    {
        if (is_string($data)) {
            $data = str_replace($search, $replace, $data);
        } elseif (is_array($data)) {
            foreach ($data as &$value) {
                $value = $this->preserveLines($value, $search, $replace);
            }
            unset($value);
        }

        return $data;
    }
}