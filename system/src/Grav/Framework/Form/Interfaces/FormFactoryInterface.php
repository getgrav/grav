<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form\Interfaces;

use Grav\Common\Page\Page;

interface FormFactoryInterface
{
    /**
     * Create form using the header of the page.
     *
     * @param Page $page
     * @param string $name
     * @param array $form
     * @return FormInterface|null
     */
    public function createPageForm(Page $page, string $name, array $form): ?FormInterface;
}
