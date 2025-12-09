<?php

declare(strict_types=1);

namespace Grav\Common\Twig\Compatibility;

use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Decorates the active Twig loader to rewrite legacy Twig 1/2 constructs on the fly.
 *
 * This loader wraps the ChainLoader and transforms template source code for Twig 3 compatibility.
 * It also proxies common FilesystemLoader methods to maintain backwards compatibility with
 * plugins that may call these methods on the loader.
 */
class Twig3CompatibilityLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly Twig3CompatibilityTransformer $transformer
    ) {
    }

    /**
     * Get the inner loader (ChainLoader).
     *
     * @return LoaderInterface
     */
    public function getInnerLoader(): LoaderInterface
    {
        return $this->inner;
    }

    /**
     * Get the FilesystemLoader from the inner ChainLoader.
     *
     * @return FilesystemLoader|null
     */
    public function getFilesystemLoader(): ?FilesystemLoader
    {
        if ($this->inner instanceof ChainLoader) {
            foreach ($this->inner->getLoaders() as $loader) {
                if ($loader instanceof FilesystemLoader) {
                    return $loader;
                }
            }
        }

        return null;
    }

    /**
     * Proxy addPath to the FilesystemLoader.
     *
     * @param string $path
     * @param string $namespace
     * @return void
     */
    public function addPath(string $path, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        $loader = $this->getFilesystemLoader();
        if ($loader !== null) {
            $loader->addPath($path, $namespace);
        }
    }

    /**
     * Proxy prependPath to the FilesystemLoader.
     *
     * @param string $path
     * @param string $namespace
     * @return void
     */
    public function prependPath(string $path, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        $loader = $this->getFilesystemLoader();
        if ($loader !== null) {
            $loader->prependPath($path, $namespace);
        }
    }

    /**
     * Proxy getPaths to the FilesystemLoader.
     *
     * @param string $namespace
     * @return array
     */
    public function getPaths(string $namespace = FilesystemLoader::MAIN_NAMESPACE): array
    {
        $loader = $this->getFilesystemLoader();
        if ($loader !== null) {
            return $loader->getPaths($namespace);
        }

        return [];
    }

    /**
     * Proxy getNamespaces to the FilesystemLoader.
     *
     * @return array
     */
    public function getNamespaces(): array
    {
        $loader = $this->getFilesystemLoader();
        if ($loader !== null) {
            return $loader->getNamespaces();
        }

        return [];
    }

    /**
     * Proxy setPaths to the FilesystemLoader.
     *
     * @param array $paths
     * @param string $namespace
     * @return void
     */
    public function setPaths(array $paths, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        $loader = $this->getFilesystemLoader();
        if ($loader !== null) {
            $loader->setPaths($paths, $namespace);
        }
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
