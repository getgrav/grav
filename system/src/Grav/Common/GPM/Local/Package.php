<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\Data\Data;
use Grav\Common\GPM\Common\Package as BasePackage;
use Parsedown;

/**
 * Class Package
 * @package Grav\Common\GPM\Local
 */
class Package extends BasePackage
{
    /** @var array */
    protected $settings;

    /**
     * Package constructor.
     * @param Data $package
     * @param string|null $package_type
     */
    public function __construct(Data $package, $package_type = null)
    {
        $data = new Data($package->blueprints()->toArray());
        parent::__construct($data, $package_type);

        $this->settings = $package->toArray();

        $html_description = Parsedown::instance()->line($this->__get('description'));
        $this->data->set('slug', $package->__get('slug'));
        $this->data->set('description_html', $html_description);
        $this->data->set('description_plain', strip_tags($html_description));
        $this->data->set('symlink', is_link(USER_DIR . $package_type . DS . $this->__get('slug')));
        $this->data->set('compatibility', $this->resolveCompatibility($data));
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return (bool)$this->settings['enabled'];
    }

    /**
     * Resolve the compatibility metadata for this package.
     *
     * @param Data $data Blueprint data
     * @return array{grav: string[], api: string[]}
     */
    protected function resolveCompatibility(Data $data): array
    {
        $raw = $data->get('compatibility');

        if (is_array($raw) && isset($raw['grav']) && is_array($raw['grav'])) {
            return [
                'grav' => array_map('strval', $raw['grav']),
                'api'  => isset($raw['api']) && is_array($raw['api']) ? array_map('strval', $raw['api']) : [],
            ];
        }

        return $this->inferCompatibility($data->get('dependencies') ?? []);
    }

    /**
     * Infer Grav compatibility from the dependencies array.
     *
     * @param array $dependencies
     * @return array{grav: string[], api: string[]}
     */
    protected function inferCompatibility(array $dependencies): array
    {
        foreach ($dependencies as $dep) {
            if (!is_array($dep) || ($dep['name'] ?? '') !== 'grav') {
                continue;
            }
            $version = $dep['version'] ?? '';

            if (!preg_match('/(\d+\.\d+(?:\.\d+)?)/', $version, $m)) {
                continue;
            }

            if (version_compare($m[1], '2.0', '>=')) {
                return ['grav' => ['2.0'], 'api' => []];
            }

            if (version_compare($m[1], '1.8', '>=')) {
                return ['grav' => ['1.8'], 'api' => []];
            }

            return ['grav' => ['1.7'], 'api' => []];
        }

        return ['grav' => ['1.7'], 'api' => []];
    }
}
