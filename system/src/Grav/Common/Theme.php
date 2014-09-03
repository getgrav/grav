<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Component\Filesystem\File\Yaml;
use Grav\Component\Filesystem\ResourceLocator;

class Theme extends Plugin
{
    public $name;

    /**
     * Constructor.
     *
     * @param Grav $grav
     * @param Config $config
     * @param string $name
     */
    public function __construct(Grav $grav, Config $config, $name)
    {
        $this->name = $name;

        parent::__construct($grav, $config);
    }

    public function configure() {
        $this->loadConfiguration();

        /** @var ResourceLocator $locator */
        $locator = $this->grav['locator'];

        // TODO: move
        $registered = stream_get_wrappers();
        $schemes = $this->config->get(
            "themes.{$this->name}.streams.scheme",
            ['theme' => ['paths' => ["user/themes/{$this->name}"]]]
        );

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }

            if (in_array($scheme, $registered)) {
                stream_wrapper_unregister($scheme);
            }
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\Grav\\Component\\Filesystem\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new \InvalidArgumentException("Stream '{$type}' could not be initialized.");
            }

        }
    }

    protected function loadConfiguration()
    {
        $themeConfig = Yaml::instance(THEMES_DIR . "{$this->name}/{$this->name}.yaml")->content();

        $this->config->merge(['themes' => [$this->name => $themeConfig]]);
    }
}
