<?php
/**
 * @package    Grav\Framework\Page
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Page;

use Grav\Common\Data\Blueprint;
use Grav\Framework\ContentBlock\ContentBlockInterface;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;

interface PageInterface extends ExportInterface
{
    /**
     * Attach another page to the
     *
     * @param PageInterface $page
     * @param string $relationship One of: auto|child|parent|alternate|translation|modular|media
     */
    public function attach(PageInterface $page, $relationship = 'auto');

    /**
     * Get the header of the page.
     *
     * @return PageHeaderInterface
     */
    public function getHeader();

    /**
     * Get the content of the page.
     *
     * @param string $type One of content|summary
     * @return string
     */
    public function getContent($type = 'content');

    /**
     * Get the associated media for the page.
     *
     * @return PageMediaCollectionInterface
     */
    public function getMedia();

    /**
     * Get blueprint for the page.
     *
     * @return Blueprint
     */
    public function getBlueprint();

    /**
     * Get the route for the page.
     *
     * @return string  The route for the Page.
     */
    public function getRoute();

    /**
     * Get the parent page.
     *
     * @return PageInterface|null the parent page object if it exists.
     */
    public function getParent();

    /**
     * Get all children of this page.
     *
     * @return PageCollectionInterface
     */
    public function getChildren();

    /**
     * Get all translations for the page.
     *
     * @return PageCollectionInterface
     */
    public function getTranslations();

    /**
     * Render page with context into a Content Block object.
     *
     * @param array  $context   Context variables for the page.
     * @param string $format    Rendering format, defaults to html.
     * @return ContentBlockInterface
     */
    public function render(array $context = [], $format = 'html');

    /**
     * Render page and its associated HTTP headers to a PSR 7 response object.
     *
     * @param Response  $response
     * @return Response
     */
    public function setResponse(Response $response);
}
