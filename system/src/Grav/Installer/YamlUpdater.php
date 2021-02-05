<?php

/**
 * @package    Grav\Installer
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Installer;

use Symfony\Component\Yaml\Yaml;
use function assert;
use function count;
use function is_array;
use function strlen;

/**
 * Grav YAML updater.
 *
 * NOTE: This class can be initialized during upgrade from an older version of Grav. Make sure it runs there!
 */
final class YamlUpdater
{
    /** @var string */
    protected $filename;
    /** @var string[]  */
    protected $lines;
    /** @var array */
    protected $comments;
    /** @var array */
    protected $items;
    /** @var bool */
    protected $updated = false;

    /** @var self[] */
    protected static $instance;

    public static function instance(string $filename): self
    {
        if (!isset(self::$instance[$filename])) {
            self::$instance[$filename] = new self($filename);
        }

        return self::$instance[$filename];
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        if (!$this->updated) {
            return false;
        }

        try {
            if (!$this->isHandWritten()) {
                $yaml = Yaml::dump($this->items, 5, 2);
            } else {
                $yaml = implode("\n", $this->lines);

                $items = Yaml::parse($yaml);
                if ($items !== $this->items) {
                    throw new \RuntimeException('Failed saving the content');
                }
            }

            file_put_contents($this->filename, $yaml);

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to update ' . basename($this->filename) . ': ' . $e->getMessage());
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isHandWritten(): bool
    {
        return !empty($this->comments);
    }

    /**
     * @return array
     */
    public function getComments(): array
    {
        $comments = [];
        foreach ($this->lines as $i => $line) {
            if ($this->isLineEmpty($line)) {
                $comments[$i+1] = $line;
            } elseif ($comment = $this->getInlineComment($line)) {
                $comments[$i+1] = $comment;
            }
        }

        return $comments;
    }

    /**
     * @param string $variable
     * @param mixed $value
     */
    public function define(string $variable, $value): void
    {
        // If variable has already value, we're good.
        if ($this->get($variable) !== null) {
            return;
        }

        // If one of the parents isn't array, we're good, too.
        if (!$this->canDefine($variable)) {
            return;
        }

        $this->set($variable, $value);
        if (!$this->isHandWritten()) {
            return;
        }

        $parts = explode('.', $variable);

        $lineNos = $this->findPath($this->lines, $parts);
        $count = count($lineNos);
        $last = array_key_last($lineNos);

        $value = explode("\n", trim(Yaml::dump([$last => $this->get(implode('.', array_keys($lineNos)))], max(0, 5-$count), 2)));
        $currentLine = array_pop($lineNos) ?: 0;
        $parentLine = array_pop($lineNos);

        if ($parentLine !== null) {
            $c = $this->getLineIndentation($this->lines[$parentLine] ?? '');
            $n = $this->getLineIndentation($this->lines[$parentLine+1] ?? $this->lines[$parentLine] ?? '');
            $indent = $n > $c ? $n : $c + 2;
        } else {
            $indent = 0;
            array_unshift($value, '');
        }
        $spaces = str_repeat(' ', $indent);
        foreach ($value as &$line) {
            $line = $spaces . $line;
        }
        unset($line);

        array_splice($this->lines, abs($currentLine)+1, 0, $value);
    }

    public function undefine(string $variable): void
    {
        // If variable does not have value, we're good.
        if ($this->get($variable) === null) {
            return;
        }

        // If one of the parents isn't array, we're good, too.
        if (!$this->canDefine($variable)) {
            return;
        }

        $this->undef($variable);
        if (!$this->isHandWritten()) {
            return;
        }

        // TODO: support also removing property from handwritten configuration file.
    }

    private function __construct(string $filename)
    {
        $content = is_file($filename) ? (string)file_get_contents($filename) : '';
        $content = rtrim(str_replace(["\r\n", "\r"], "\n", $content));

        $this->filename = $filename;
        $this->lines = explode("\n", $content);
        $this->comments = $this->getComments();
        $this->items = $content ? Yaml::parse($content) : [];
    }

    /**
     * Return array of offsets for the parent nodes. Negative value means position, but not found.
     *
     * @param array $lines
     * @param array $parts
     * @return int[]
     */
    private function findPath(array $lines, array $parts)
    {
        $test = true;
        $indent = -1;
        $current = array_shift($parts);

        $j = 1;
        $found = [];
        $space = '';
        foreach ($lines as $i => $line) {
            if ($this->isLineEmpty($line)) {
                if ($this->isLineComment($line) && $this->getLineIndentation($line) > $indent) {
                    $j = $i;
                }
                continue;
            }

            if ($test === true) {
                $test = false;
                $spaces = strlen($line) - strlen(ltrim($line, ' '));
                if ($spaces <= $indent) {
                    $found[$current] = -$j;

                    return $found;
                }

                $indent = $spaces;
                $space = $indent ? str_repeat(' ', $indent) : '';
            }


            if (0 === \strncmp($line, $space, strlen($space))) {
                $pattern = "/^{$space}(['\"]?){$current}\\1\:/";

                if (preg_match($pattern, $line)) {
                    $found[$current] = $i;
                    $current = array_shift($parts);
                    if ($current === null) {
                        return $found;
                    }
                    $test = true;
                }
            } else {
                $found[$current] = -$j;

                return $found;
            }

            $j = $i;
        }

        $found[$current] = -$j;

        return $found;
    }

    /**
     * Returns true if the current line is blank or if it is a comment line.
     *
     * @param string $line Contents of the line
     * @return bool Returns true if the current line is empty or if it is a comment line, false otherwise
     */
    private function isLineEmpty(string $line): bool
    {
        return $this->isLineBlank($line) || $this->isLineComment($line);
    }

    /**
     * Returns true if the current line is blank.
     *
     * @param string $line Contents of the line
     * @return bool Returns true if the current line is blank, false otherwise
     */
    private function isLineBlank(string $line): bool
    {
        return '' === trim($line, ' ');
    }

    /**
     * Returns true if the current line is a comment line.
     *
     * @param string $line Contents of the line
     * @return bool Returns true if the current line is a comment line, false otherwise
     */
    private function isLineComment(string $line): bool
    {
        //checking explicitly the first char of the trim is faster than loops or strpos
        $ltrimmedLine = ltrim($line, ' ');

        return '' !== $ltrimmedLine && '#' === $ltrimmedLine[0];
    }

    /**
     * @param string $line
     * @return bool
     */
    private function isInlineComment(string $line): bool
    {
        return $this->getInlineComment($line) !== null;
    }

    /**
     * @param string $line
     * @return string|null
     */
    private function getInlineComment(string $line): ?string
    {
        $pos = strpos($line, ' #');
        if (false === $pos) {
            return null;
        }

        $parts = explode(' #', $line);
        $part = '';
        while ($part .= array_shift($parts)) {
            // Remove quoted values.
            $part = preg_replace('/(([\'"])[^\2]*\2)/', '', $part);
            assert(null !== $part);
            $part = preg_split('/[\'"]/', $part, 2);
            assert(false !== $part);
            if (!isset($part[1])) {
                $part = $part[0];
                array_unshift($parts, str_repeat(' ', strlen($part) - strlen(trim($part, ' '))));
                break;
            }
            $part = $part[1];
        }


        return implode(' #', $parts);
    }

    /**
     * Returns the current line indentation.
     *
     * @param string $line
     * @return int The current line indentation
     */
    private function getLineIndentation(string $line): int
    {
        return \strlen($line) - \strlen(ltrim($line, ' '));
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @param string $name Dot separated path to the requested value.
     * @param mixed $default Default value (or null).
     * @return mixed Value.
     */
    private function get(string $name, $default = null)
    {
        $path = explode('.', $name);
        $current = $this->items;

        foreach ($path as $field) {
            if (is_array($current) && isset($current[$field])) {
                $current = $current[$field];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @param string $name Dot separated path to the requested value.
     * @param mixed $value New value.
     */
    private function set(string $name, $value): void
    {
        $path = explode('.', $name);
        $current = &$this->items;

        foreach ($path as $field) {
            // Handle arrays and scalars.
            if (!is_array($current)) {
                $current = [$field => []];
            } elseif (!isset($current[$field])) {
                $current[$field] = [];
            }
            $current = &$current[$field];
        }

        $current = $value;
        $this->updated = true;
    }

    /**
     * Unset value by using dot notation for nested arrays/objects.
     *
     * @param string $name Dot separated path to the requested value.
     */
    private function undef(string $name): void
    {
        $path = $name !== '' ? explode('.', $name) : [];
        if (!$path) {
            return;
        }

        $var = array_pop($path);
        $current = &$this->items;

        foreach ($path as $field) {
            if (!is_array($current) || !isset($current[$field])) {
                return;
            }
            $current = &$current[$field];
        }

        unset($current[$var]);
        $this->updated = true;
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @param string $name Dot separated path to the requested value.
     * @return bool
     */
    private function canDefine(string $name): bool
    {
        $path = explode('.', $name);
        $current = $this->items;

        foreach ($path as $field) {
            if (is_array($current)) {
                if (!isset($current[$field])) {
                    return true;
                }
                $current = $current[$field];
            } else {
                return false;
            }
        }

        return true;
    }
}
