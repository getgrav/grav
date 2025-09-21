<?php

declare(strict_types=1);

namespace Grav\Common\Twig\Compatibility;

use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Decorates the active Twig loader to rewrite legacy Twig 1/2 constructs on the fly.
 */
class Twig3CompatibilityLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly Twig3CompatibilityTransformer $transformer
    ) {
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->inner->getSourceContext($name);

        return new Source(
            $this->transformer->transform($source->getCode()),
            $source->getName(),
            $source->getPath()
        );
    }

    public function exists(string $name): bool
    {
        return $this->inner->exists($name);
    }

    public function getCacheKey(string $name): string
    {
        return $this->inner->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->inner->isFresh($name, $time);
    }
}
