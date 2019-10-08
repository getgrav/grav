<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages;

use Grav\Common\Grav;
use Grav\Framework\Flex\FlexIndex;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;

/**
 * Class FlexPageObject
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 */
class FlexPageIndex extends FlexIndex
{
    const ORDER_PREFIX_REGEX = '/^\d+\./u';

    /**
     * @param string $route
     * @return string
     * @internal
     */
    static public function normalizeRoute(string $route)
    {
        static $case_insensitive;

        if (null === $case_insensitive) {
            $case_insensitive = Grav::instance()['config']->get('system.force_lowercase_urls', false);
        }

        return $case_insensitive ? mb_strtolower($route) : $route;
    }

    /**
     * @return FlexPageIndex
     */
    public function visible()
    {
        return $this->withVisible();
    }

    /**
     * @return FlexPageIndex
     */
    public function nonVisible()
    {
        return $this->withVisible(false);
    }

    /**
     * @param bool $bool
     * @return FlexPageIndex
     */
    public function withVisible(bool $bool = true)
    {
        $keys = $this->getIndexMap('key');
        $list = [];
        foreach ($keys as $key => $test) {
            $keyBase = basename($key);
            if ((int)$key > 0) {
                $testBase = basename($test);
                if (mb_strlen($keyBase) !== mb_strlen($testBase)) {
                    $list[] = $key;
                }
            }
        }

        return $this->select($list);
    }
}
