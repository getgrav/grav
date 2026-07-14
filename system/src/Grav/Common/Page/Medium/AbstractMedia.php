<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Media\Traits\MediaUploadTrait;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\Iterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function is_array;

/**
 * Class AbstractMedia
 * @package Grav\Common\Page\Medium
 */
abstract class AbstractMedia implements ExportInterface, MediaCollectionInterface, MediaUploadInterface
{
    use ArrayAccess;
    use Countable;
    use Iterator;
    use Export;
    use MediaUploadTrait;

    /** @var array */
    protected $items = [];
    /** @var string|null */
    protected $path;
    /** @var array */
    protected $images = [];
    /** @var array */
    protected $videos = [];
    /** @var array */
    protected $audios = [];
    /** @var array */
    protected $files = [];
    /** @var array|null */
    protected $media_order;

    /**
     * Return media path.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     */
    public function get($filename)
    {
        return $this->offsetGet($filename);
    }

    /**
     * Call object as function to get medium by filename.
     *
     * @param string $filename
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function __invoke($filename)
    {
        return $this->offsetGet($filename);
    }

    /**
     * Set file modification timestamps (query params) for all the media files.
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamps($timestamp = null)
    {
        foreach ($this->items as $instance) {
            $instance->setTimestamp($timestamp);
        }

        return $this;
    }

    /**
     * Get a list of all media.
     *
     * @return MediaObjectInterface[]
     */
    public function all()
    {
        $this->items = $this->orderMedia($this->items);

        return $this->items;
    }

    /**
     * Get a list of all image media.
     *
     * @return MediaObjectInterface[]
     */
    public function images()
    {
        $this->images = $this->orderMedia($this->images);

        return $this->images;
    }

    /**
     * Get a list of all video media.
     *
     * @return MediaObjectInterface[]
     */
    public function videos()
    {
        $this->videos = $this->orderMedia($this->videos);

        return $this->videos;
    }

    /**
     * Get a list of all audio media.
     *
     * @return MediaObjectInterface[]
     */
    public function audios()
    {
        $this->audios = $this->orderMedia($this->audios);

        return $this->audios;
    }

    /**
     * Get a list of all file media.
     *
     * @return MediaObjectInterface[]
     */
    public function files()
    {
        $this->files = $this->orderMedia($this->files);

        return $this->files;
    }

    /**
     * Comparison operators accepted by {@see filterBy()} / {@see findBy()}.
     *
     * Deliberately a fixed set: no regex or callable operators, so there is no
     * ReDoS or arbitrary-predicate surface. The API plugin reuses this list as
     * its operator allow-list.
     */
    public const META_OPERATORS = ['==', '!=', '>', '>=', '<', '<=', 'in', 'contains'];

    /**
     * Filter the collection by a `.meta.yaml` field value.
     *
     * Returns a new collection (same type) containing only the media whose
     * `$field` satisfies `$operator` against `$value`, so calls chain:
     * `page.media.filterBy('rating', 3, '>=').sortBy('rating', 'desc')`.
     *
     * Operators: `== != > >= < <= in contains`. Comparison is
     * loose-comparison-safe — numeric only when both operands are numeric,
     * otherwise a strict string compare. For list fields (e.g. `tags`),
     * `contains` tests membership and `in` tests intersection with `$value`.
     *
     * @param string $field    Metadata key (dot notation supported).
     * @param mixed  $value    Value to compare against.
     * @param string $operator One of {@see META_OPERATORS}.
     * @return static
     */
    public function filterBy($field, $value, $operator = '==')
    {
        if (!in_array($operator, self::META_OPERATORS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported media filter operator "%s".', $operator));
        }

        $items = array_filter(
            $this->all(),
            static fn($medium) => self::compareMeta($medium->get($field), $value, $operator)
        );

        return $this->createFrom($items);
    }

    /**
     * Filter the collection by several equality criteria at once (ANDed).
     *
     * Each `field => value` pair must match. A scalar value is compared with
     * `==`; an array value is treated as an `in` set (the field must equal one
     * of the listed values). For anything beyond equality/membership, chain
     * {@see filterBy()} calls instead.
     *
     * `page.media.where({ copyright: 'Jane Doe', rating: [4, 5] })`
     *
     * @param array $criteria
     * @return static
     */
    public function where(array $criteria)
    {
        $result = $this;
        foreach ($criteria as $field => $value) {
            $result = $result->filterBy((string) $field, $value, is_array($value) ? 'in' : '==');
        }

        return $result;
    }

    /**
     * Return the first medium whose `$field` satisfies the criterion, or null.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $operator One of {@see META_OPERATORS}.
     * @return MediaObjectInterface|null
     */
    public function findBy($field, $value, $operator = '==')
    {
        if (!in_array($operator, self::META_OPERATORS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported media filter operator "%s".', $operator));
        }

        foreach ($this->all() as $medium) {
            if (self::compareMeta($medium->get($field), $value, $operator)) {
                return $medium;
            }
        }

        return null;
    }

    /**
     * Return a new collection sorted by a `.meta.yaml` field.
     *
     * Values are compared numerically when both are numeric, otherwise with a
     * natural, case-insensitive string compare (matching Grav's default media
     * ordering). Media missing the field always sort last, regardless of
     * direction, so an empty `rating` never jumps to the front of a `desc` sort.
     *
     * @param string $field
     * @param string $dir 'asc' (default) or 'desc'.
     * @return static
     */
    public function sortBy($field, $dir = 'asc')
    {
        $desc = strtolower((string) $dir) === 'desc';
        $items = $this->all();

        uasort($items, static function ($a, $b) use ($field, $desc) {
            $va = $a->get($field);
            $vb = $b->get($field);
            $ea = $va === null || $va === '' || $va === [];
            $eb = $vb === null || $vb === '' || $vb === [];
            if ($ea || $eb) {
                // Empties sink to the end in both directions.
                return $ea <=> $eb;
            }

            if (is_numeric($va) && is_numeric($vb)) {
                $cmp = ($va + 0) <=> ($vb + 0);
            } else {
                $cmp = strnatcasecmp((string) $va, (string) $vb);
            }

            return $desc ? -$cmp : $cmp;
        });

        return $this->createFrom($items);
    }

    /**
     * Group the collection by a `.meta.yaml` field value.
     *
     * Returns a map of `value => collection` (each value a full sub-collection,
     * so it iterates and chains like `page.media`). Media missing the field are
     * grouped under an empty-string key. A multi-valued field (e.g. `tags`)
     * places its medium under every value it holds, so an image tagged
     * `[sunset, beach]` appears in both the `sunset` and `beach` groups. Group
     * keys are returned in natural, case-insensitive order.
     *
     * @param string $field
     * @return array<string, static>
     */
    public function groupBy($field)
    {
        $groups = [];
        foreach ($this->all() as $name => $medium) {
            $value = $medium->get($field);
            if (is_array($value)) {
                $keys = $value === [] ? [''] : $value;
            } else {
                $keys = [$value === null ? '' : (string) $value];
            }

            foreach ($keys as $key) {
                $groups[(string) $key][$name] = $medium;
            }
        }

        uksort($groups, 'strnatcasecmp');

        return array_map(fn(array $items) => $this->createFrom($items), $groups);
    }

    /**
     * Return a new collection of only the media that carry `.meta.yaml`
     * metadata, dropping bare files.
     *
     * @return static
     */
    public function withMeta()
    {
        $items = array_filter($this->all(), static fn($medium) => $medium->hasMeta());

        return $this->createFrom($items);
    }

    /**
     * @param string $name
     * @param MediaObjectInterface|null $file
     * @return void
     */
    public function add($name, $file)
    {
        if (null === $file) {
            return;
        }

        $this->offsetSet($name, $file);

        switch ($file->type) {
            case 'image':
                $this->images[$name] = $file;
                break;
            case 'video':
                $this->videos[$name] = $file;
                break;
            case 'audio':
                $this->audios[$name] = $file;
                break;
            default:
                $this->files[$name] = $file;
        }
    }

    /**
     * @param string $name
     * @return void
     */
    public function hide($name)
    {
        $this->offsetUnset($name);

        unset($this->images[$name], $this->videos[$name], $this->audios[$name], $this->files[$name]);
    }

    /**
     * Create Medium from a file.
     *
     * @param  string $file
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromFile($file, array $params = [])
    {
        return MediumFactory::fromFile($file, $params);
    }

        /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    public function createFromArray(array $items = [], ?Blueprint $blueprint = null)
    {
        return MediumFactory::fromArray($items, $blueprint);
    }

    /**
     * @param MediaObjectInterface $mediaObject
     * @return ImageFile
     */
    public function getImageFileObject(MediaObjectInterface $mediaObject): ImageFile
    {
        return ImageFile::open($mediaObject->get('filepath'));
    }

    /**
     * Order the media based on the page's media_order
     *
     * @param array $media
     * @return array
     */
    protected function orderMedia($media)
    {
        if (null === $this->media_order) {
            $path = $this->getPath();
            if (null !== $path) {
                /** @var Pages $pages */
                $pages = Grav::instance()['pages'];
                $page = $pages->get($path);
                if ($page && isset($page->header()->media_order)) {
                    $this->media_order = array_map('trim', explode(',', $page->header()->media_order));
                }
            }
        }

        if (!empty($this->media_order) && is_array($this->media_order)) {
            $media = Utils::sortArrayByArray($media, $this->media_order);
        } else {
            ksort($media, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $media;
    }

    /**
     * Build a new collection of the same type from a subset of already-loaded
     * media, keeping the type buckets (images/videos/…) consistent so the
     * result behaves exactly like `page.media` — iterable, countable, and
     * further chainable. The media objects themselves are shared, not cloned:
     * these are read-only query results.
     *
     * @param array<string, MediaObjectInterface> $items filename => Medium
     * @return static
     */
    protected function createFrom(array $items)
    {
        $clone = clone $this;
        $clone->items = $items;
        $clone->images = [];
        $clone->videos = [];
        $clone->audios = [];
        $clone->files = [];

        foreach ($items as $name => $file) {
            switch ($file->type) {
                case 'image':
                    $clone->images[$name] = $file;
                    break;
                case 'video':
                    $clone->videos[$name] = $file;
                    break;
                case 'audio':
                    $clone->audios[$name] = $file;
                    break;
                default:
                    $clone->files[$name] = $file;
            }
        }

        return $clone;
    }

    /**
     * Compare a medium's metadata value against a target using one of
     * {@see META_OPERATORS}. Kept loose-comparison-safe on purpose: scalar
     * comparisons are numeric only when both operands are numeric, otherwise a
     * strict string compare, so `'0'` never equals `'false'` and `'10'` never
     * sorts before `'9'` as strings. `in`/`contains` normalize to explicit
     * list/substring tests. Pure in-memory: no I/O, no injection surface.
     *
     * @param mixed  $actual
     * @param mixed  $expected
     * @param string $operator
     * @return bool
     */
    protected static function compareMeta($actual, $expected, string $operator): bool
    {
        // A medium that lacks the queried field never matches any operator: an
        // absent value can't be ordered, contained, or equal to a target. This
        // keeps filtering predictable (unrated media stay out of a `rating < 3`
        // result) and mirrors how sortBy() sinks empties to the end.
        if ($actual === null) {
            return false;
        }

        switch ($operator) {
            case 'in':
                // $expected is a set; the field matches if it (or any of its
                // values, for list fields) is in that set.
                $set = is_array($expected) ? $expected : [$expected];
                if (is_array($actual)) {
                    foreach ($actual as $item) {
                        if (self::listContains($set, $item)) {
                            return true;
                        }
                    }
                    return false;
                }
                return self::listContains($set, $actual);

            case 'contains':
                // List field: does it contain $expected? String field:
                // substring test.
                if (is_array($actual)) {
                    return self::listContains($actual, $expected);
                }
                if (is_array($expected)) {
                    return false;
                }
                return $expected === '' ? true : str_contains((string) $actual, (string) $expected);

            default:
                // Scalar comparison operators never match array operands.
                if (is_array($actual) || is_array($expected)) {
                    return false;
                }
                return self::compareScalar($actual, $expected, $operator);
        }
    }

    /**
     * Loose-comparison-safe scalar comparison.
     *
     * @param mixed  $actual
     * @param mixed  $expected
     * @param string $operator
     * @return bool
     */
    protected static function compareScalar($actual, $expected, string $operator): bool
    {
        $bothNumeric = is_numeric($actual) && is_numeric($expected);

        switch ($operator) {
            case '==':
                return $bothNumeric
                    ? ($actual + 0) == ($expected + 0)
                    : (string) $actual === (string) $expected;
            case '!=':
                return !self::compareScalar($actual, $expected, '==');
            case '>':
                return $bothNumeric ? ($actual + 0) > ($expected + 0) : strcmp((string) $actual, (string) $expected) > 0;
            case '>=':
                return $bothNumeric ? ($actual + 0) >= ($expected + 0) : strcmp((string) $actual, (string) $expected) >= 0;
            case '<':
                return $bothNumeric ? ($actual + 0) < ($expected + 0) : strcmp((string) $actual, (string) $expected) < 0;
            case '<=':
                return $bothNumeric ? ($actual + 0) <= ($expected + 0) : strcmp((string) $actual, (string) $expected) <= 0;
        }

        return false;
    }

    /**
     * True if $value equals any entry of $list (using the same
     * loose-comparison-safe equality as `==`).
     *
     * @param array $list
     * @param mixed $value
     * @return bool
     */
    protected static function listContains(array $list, $value): bool
    {
        foreach ($list as $item) {
            if (!is_array($item) && !is_array($value) && self::compareScalar($item, $value, '==')) {
                return true;
            }
        }

        return false;
    }

    protected function fileExists(string $filename, string $destination): bool
    {
        return file_exists("{$destination}/{$filename}");
    }

    /**
     * Get filename, extension and meta part.
     *
     * @param  string $filename
     * @return array
     */
    protected function getFileParts($filename)
    {
        if (preg_match('/(.*)@(\d+)x\.(.*)$/', $filename, $matches)) {
            $name = $matches[1];
            $extension = $matches[3];
            $extra = (int) $matches[2];
            $type = 'alternative';

            if ($extra === 1) {
                $type = 'base';
                $extra = null;
            }
        } else {
            $fileParts = explode('.', $filename);

            $name = array_shift($fileParts);
            $extension = null;
            $extra = null;
            $type = 'base';

            while (($part = array_shift($fileParts)) !== null) {
                if ($part !== 'meta' && $part !== 'thumb') {
                    if (null !== $extension) {
                        $name .= '.' . $extension;
                    }
                    $extension = $part;
                } else {
                    $type = $part;
                    $extra = '.' . $part . '.' . implode('.', $fileParts);
                    break;
                }
            }
        }

        return [$name, $extension, $type, $extra];
    }

    protected function getGrav(): Grav
    {
        return Grav::instance();
    }

    protected function getConfig(): Config
    {
        return $this->getGrav()['config'];
    }

    protected function getLanguage(): Language
    {
        return $this->getGrav()['language'];
    }

    protected function clearCache(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        $locator->clearCache();
    }
}
