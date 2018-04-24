<?php
/**
 * @package    Grav\Framework\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Formatter;

class SerializeFormatter implements FormatterInterface
{
    public function getFileExtension()
    {
        return 'raw';
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
        return $this->preserveLines(unserialize($data), ['\\n', '\\r'], ["\n", "\r"]);
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