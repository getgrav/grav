<?php
/**
 * @package    Grav\Framework\Page
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Page;

use RocketTheme\Toolbox\File\File;

interface FilePageInterface extends PageInterface
{
    /**
     * Create a new page by filename.
     *
     * @param string $filename
     * @return static
     */
    public static function createFromFilename($filename);

    /**
     * Create a new page by SplFileInfo object.
     *
     * @param \SplFileInfo $file
     * @return static
     */
    public static function createFromFileInfo(\SplFileInfo $file);

    /**
     * Get file object from the page.
     *
     * @return File
     */
    public function getFile();
}
