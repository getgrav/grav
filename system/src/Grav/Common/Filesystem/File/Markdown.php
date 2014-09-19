<?php
namespace Grav\Common\Filesystem\File;

use \Symfony\Component\Yaml\Yaml as YamlParser;

/**
 * File handling class.
 *
 * @author RocketTheme
 * @license MIT
 */
class Markdown extends General
{
    /**
     * @var string
     */
    protected $extension = '.md';

    /**
     * @var array|General[]
     */
    static protected $instances = array();

    /**
     * Get/set file header.
     *
     * @param array $var
     *
     * @return array
     */
    public function header(array $var = null)
    {
        $content = $this->content();

        if ($var !== null) {
            $content['header'] = $var;
            $this->content($content);
        }

        return $content['header'];
    }

    /**
     * Get/set markdown content.
     *
     * @param string $var
     *
     * @return string
     */
    public function markdown($var = null)
    {
        $content = $this->content();

        if ($var !== null) {
            $content['markdown'] = (string) $var;
            $this->content($content);
        }

        return $content['markdown'];
    }

    /**
     * Get/set frontmatter content.
     *
     * @param string $var
     *
     * @return string
     */
    public function frontmatter($var = null)
    {
        $content = $this->content();

        if ($var !== null) {
            $content['frontmatter'] = (string) $var;
            $this->content($content);
        }

        return $content['frontmatter'];
    }

    /**
     * Check contents and make sure it is in correct format.
     *
     * @param array $var
     * @return array
     */
    protected function check($var)
    {
        $var = (array) $var;
        if (!isset($var['header']) || !is_array($var['header'])) {
            $var['header'] = array();
        }
        if (!isset($var['markdown']) || !is_string($var['markdown'])) {
            $var['markdown'] = '';
        }

        return $var;
    }

    /**
     * Encode contents into RAW string.
     *
     * @param string $var
     * @return string
     */
    protected function encode($var)
    {
        // Create Markdown file with YAML header.
        $o = (!empty($var['header']) ? "---\n" . trim(YamlParser::dump($var['header'])) . "\n---\n\n" : '') . $var['markdown'];

        // Normalize line endings to Unix style.
        $o = preg_replace("/(\r\n|\r)/", "\n", $o);

        return $o;
    }

    /**
     * Decode RAW string into contents.
     *
     * @param string $var
     * @return array mixed
     */
    protected function decode($var)
    {
        $content = array();
        $content['header'] = array();
        $content['frontmatter'] = array();

        // Normalize line endings to Unix style.
        $var = preg_replace("/(\r\n|\r)/", "\n", $var);

        // Parse header.
        preg_match("/---\n(.+?)\n---(\n\n|$)/uism", $var, $m);
        if (isset($m[1])) {
            $content['frontmatter'] = preg_replace("/\n\t/", "\n    ", $m[1]);
            $content['header'] = YamlParser::parse($content['frontmatter']);
        }

        // Strip header to get content.
        $content['markdown'] = trim(preg_replace("/---\n(.+?)\n---(\n\n|$)/uism", '', $var));

        return $content;
    }
}
