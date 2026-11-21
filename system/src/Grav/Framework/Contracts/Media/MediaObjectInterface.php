<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Media;

use Grav\Framework\Contracts\Object\IdentifierInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Media Object Interface
 */
interface MediaObjectInterface extends IdentifierInterface
{
    /**
     * Returns true if the object exists.
     *
     * @return bool
     * @phpstan-pure
     */
    public function exists(): bool;

    /**
     * Get metadata associated to the media object.
     *
     * @return array
     */
    public function getMeta(): array;

    /**
     * @param string $field
     * @return mixed
     */
    public function get(string $field);

    /**
     * Return URL pointing to the media object.
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Create media response.
     *
     * @param array $actions
     * @return ResponseInterface
     */
    public function createResponse(array $actions): ResponseInterface;
}
