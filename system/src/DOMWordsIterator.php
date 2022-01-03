<?php

/**
 * Iterates individual words of DOM text and CDATA nodes
 * while keeping track of their position in the document.
 *
 * Example:
 *
 *  $doc = new DOMDocument();
 *  $doc->load('example.xml');
 *  foreach(new DOMWordsIterator($doc) as $word) echo $word;
 *
 * @author pjgalbraith http://www.pjgalbraith.com
 * @author porneL http://pornel.net (based on DOMLettersIterator available at http://pornel.net/source/domlettersiterator.php)
 * @license Public Domain
 * @url https://github.com/antoligy/dom-string-iterators
 *
 * @implements Iterator<int,string>
 */

final class DOMWordsIterator implements Iterator
{
    /** @var DOMElement */
    private $start;
    /** @var DOMElement|null */
    private $current;
    /** @var int */
    private $offset = -1;
    /** @var int|null */
    private $key;
    /** @var array<int,array<int,int|string>>|null */
    private $words;

    /**
     * expects DOMElement or DOMDocument (see DOMDocument::load and DOMDocument::loadHTML)
     *
     * @param DOMNode $el
     */
    public function __construct(DOMNode $el)
    {
        if ($el instanceof DOMDocument) {
            $el = $el->documentElement;
        }

        if (!$el instanceof DOMElement) {
            throw new InvalidArgumentException('Invalid arguments, expected DOMElement or DOMDocument');
        }

        $this->start = $el;
    }

    /**
     * Returns position in text as DOMText node and character offset.
     * (it's NOT a byte offset, you must use mb_substr() or similar to use this offset properly).
     * node may be NULL if iterator has finished.
     *
     * @return array
     */
    public function currentWordPosition(): array
    {
        return [$this->current, $this->offset, $this->words];
    }

    /**
     * Returns DOMElement that is currently being iterated or NULL if iterator has finished.
     *
     * @return DOMElement|null
     */
    public function currentElement(): ?DOMElement
    {
        return $this->current ? $this->current->parentNode : null;
    }

    // Implementation of Iterator interface

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return int|null
     */
    public function key(): ?int
    {
        return $this->key;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        if (null === $this->current) {
            return;
        }

        if ($this->current->nodeType === XML_TEXT_NODE || $this->current->nodeType === XML_CDATA_SECTION_NODE) {
            if ($this->offset === -1) {
                $this->words = preg_split("/[\n\r\t ]+/", $this->current->textContent, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_OFFSET_CAPTURE) ?: [];
            }
            $this->offset++;

            if ($this->words && $this->offset < count($this->words)) {
                $this->key++;
                return;
            }
            $this->offset = -1;
        }

        while ($this->current->nodeType === XML_ELEMENT_NODE && $this->current->firstChild) {
            $this->current = $this->current->firstChild;
            if ($this->current->nodeType === XML_TEXT_NODE || $this->current->nodeType === XML_CDATA_SECTION_NODE) {
                $this->next();
                return;
            }
        }

        while (!$this->current->nextSibling && $this->current->parentNode) {
            $this->current = $this->current->parentNode;
            if ($this->current === $this->start) {
                $this->current = null;
                return;
            }
        }

        $this->current = $this->current->nextSibling;

        $this->next();
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return string|null
     */
    public function current(): ?string
    {
        return $this->words ? (string)$this->words[$this->offset][0] : null;
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return bool
     */
    public function valid(): bool
    {
        return (bool)$this->current;
    }

    public function rewind(): void
    {
        $this->current = $this->start;
        $this->offset = -1;
        $this->key = 0;
        $this->words = [];

        $this->next();
    }
}
