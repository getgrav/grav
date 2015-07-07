<?php
namespace Grav\Common\Config;

use Grav\Common\Filesystem\Folder;

/**
 * The Configuration Finder class.
 *
 * @author RocketTheme
 * @license MIT
 */
class ConfigFinder
{
    /**
     * Get all locations for blueprint files (including plugins).
     *
     * @param array $blueprints
     * @param array $plugins
     * @return array
     */
    public function locateBlueprintFiles(array $blueprints, array $plugins)
    {
        $list = [];
        foreach (array_reverse($plugins) as $folder) {
            $list += $this->detectInFolder($folder, 'blueprints');
        }
        foreach (array_reverse($blueprints) as $folder) {
            $list += $this->detectRecursive($folder);
        }
        return $list;
    }

    /**
     * Get all locations for configuration files (including plugins).
     *
     * @param array $configs
     * @param array $plugins
     * @return array
     */
    public function locateConfigFiles(array $configs, array $plugins)
    {
        $list = [];
        foreach (array_reverse($plugins) as $folder) {
            $list += $this->detectInFolder($folder);
        }
        foreach (array_reverse($configs) as $folder) {
            $list += $this->detectRecursive($folder);
        }
        return $list;
    }

    public function locateLanguageFiles(array $languages, array $plugins)
    {
        $list = [];
        foreach (array_reverse($plugins) as $folder) {
            $list += $this->detectLanguagesInFolder($folder, 'languages');
        }
        foreach (array_reverse($languages) as $folder) {
            $list += $this->detectRecursive($folder);
        }
        return $list;
    }

    /**
     * Get all locations for a single configuration file.
     *
     * @param  array  $folders Locations to look up from.
     * @param  string $name    Filename to be located.
     * @return array
     */
    public function locateConfigFile(array $folders, $name)
    {
        $filename = "{$name}.yaml";

        $list = [];
        foreach ($folders as $folder) {
            $path = trim(Folder::getRelativePath($folder), '/');

            if (is_file("{$folder}/{$filename}")) {
                $modified = filemtime("{$folder}/{$filename}");
            } else {
                $modified = 0;
            }
            $list[$path] = [$name => ['file' => "{$path}/{$filename}", 'modified' => $modified]];
        }

        return $list;
    }

    /**
     * Detects all plugins with a configuration file and returns them with last modification time.
     *
     * @param  string $folder Location to look up from.
     * @param  string $lookup Filename to be located.
     * @return array
     * @internal
     */
    protected function detectInFolder($folder, $lookup = null)
    {
        $path = trim(Folder::getRelativePath($folder), '/');

        $list = [];

        if (is_dir($folder)) {
            $iterator = new \FilesystemIterator($folder);

            /** @var \DirectoryIterator $directory */
            foreach ($iterator as $directory) {
                if (!$directory->isDir()) {
                    continue;
                }

                $name = $directory->getBasename();
                $find = ($lookup ?: $name) . '.yaml';
                $filename = "{$path}/{$name}/$find";

                if (file_exists($filename)) {
                    $list["plugins/{$name}"] = ['file' => $filename, 'modified' => filemtime($filename)];
                }
            }
        }

        return [$path => $list];
    }

    protected function detectLanguagesInFolder($folder, $lookup = null)
    {
        $path = trim(Folder::getRelativePath($folder), '/');

        $list = [];

        if (is_dir($folder)) {
            $iterator = new \FilesystemIterator($folder);

            /** @var \DirectoryIterator $directory */
            foreach ($iterator as $directory) {
                if (!$directory->isDir()) {
                    continue;
                }

                $name = $directory->getBasename();
                $find = ($lookup ?: $name) . '.yaml';
                $filename = "{$path}/{$name}/$find";

                if (file_exists($filename)) {
                    $list["plugins"] = ['file' => $filename, 'modified' => filemtime($filename)];
                }
            }
        }

        return [$path => $list];
    }

    /**
     * Detects all plugins with a configuration file and returns them with last modification time.
     *
     * @param  string $folder Location to look up from.
     * @return array
     * @internal
     */
    protected function detectRecursive($folder)
    {
        $path = trim(Folder::getRelativePath($folder), '/');

        if (is_dir($folder)) {
            // Find all system and user configuration files.
            $options = [
                'compare' => 'Filename',
                'pattern' => '|\.yaml$|',
                'filters' => [
                    'key' => '|\.yaml$|',
                    'value' => function (\RecursiveDirectoryIterator $file) use ($path) {
                        return ['file' => "{$path}/{$file->getSubPathname()}", 'modified' => $file->getMTime()];
                    }
                ],
                'key' => 'SubPathname'
            ];

            $list = Folder::all($folder, $options);
        } else {
            $list = [];
        }

        return [$path => $list];
    }
}
