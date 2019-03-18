<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;

class MarkdownFormatter extends AbstractFormatter
{
    /** @var FileFormatterInterface */
    private $headerFormatter;

    public function __construct(array $config = [], FileFormatterInterface $headerFormatter = null)
    {
        $config += [
            'file_extension' => '.md',
            'header' => 'header',
            'body' => 'markdown',
            'raw' => 'frontmatter',
            'yaml' => ['inline' => 20]
        ];

        parent::__construct($config);

        $this->headerFormatter = $headerFormatter ?: new YamlFormatter($config['yaml']);
    }

    /**
     * Returns header field used in both encode() and decode().
     *
     * @return string
     */
    public function getHeaderField(): string
    {
        return $this->getConfig('header');
    }

    /**
     * Returns body field used in both encode() and decode().
     *
     * @return string
     */
    public function getBodyField(): string
    {
        return $this->getConfig('body');
    }

    /**
     * Returns raw field used in both encode() and decode().
     *
     * @return string
     */
    public function getRawField(): string
    {
        return $this->getConfig('raw');
    }

    /**
     * Returns header formatter object used in both encode() and decode().
     *
     * @return FileFormatterInterface
     */
    public function getHeaderFormatter(): FileFormatterInterface
    {
        return $this->headerFormatter;
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::encode()
     */
    public function encode($data): string
    {
        $headerVar = $this->getHeaderField();
        $bodyVar = $this->getBodyField();

        $header = isset($data[$headerVar]) ? (array) $data[$headerVar] : [];
        $body = isset($data[$bodyVar]) ? (string) $data[$bodyVar] : '';

        // Create Markdown file with YAML header.
        $encoded = '';
        if ($header) {
            $encoded = "---\n" . trim($this->getHeaderFormatter()->encode($data['header'])) . "\n---\n\n";
        }
        $encoded .= $body;

        // Normalize line endings to Unix style.
        $encoded = preg_replace("/(\r\n|\r)/", "\n", $encoded);

        return $encoded;
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    public function decode($data): array
    {
        $headerVar = $this->getHeaderField();
        $bodyVar = $this->getBodyField();
        $rawVar = $this->getRawField();

        // Define empty content
        $content = [
            $headerVar => [],
            $bodyVar => ''
        ];

        $headerRegex = "/^---\n(.+?)\n---\n{0,}(.*)$/uis";

        // Normalize line endings to Unix style.
        $data = preg_replace("/(\r\n|\r)/", "\n", $data);

        // Parse header.
        preg_match($headerRegex, ltrim($data), $matches);
        if(empty($matches)) {
            $content[$bodyVar] = $data;
        } else {
            // Normalize frontmatter.
            $frontmatter = preg_replace("/\n\t/", "\n    ", $matches[1]);
            if ($rawVar) {
                $content[$rawVar] = $frontmatter;
            }
            $content[$headerVar] = $this->getHeaderFormatter()->decode($frontmatter);
            $content[$bodyVar] = $matches[2];
        }

        return $content;
    }
}
