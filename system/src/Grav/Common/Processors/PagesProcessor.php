<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

class PagesProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'pages';
    public $title = 'Pages';

    public function process()
    {
        // Dump Cache state
        $this->container['debugger']->addMessage($this->container['cache']->getCacheStatus());

        $this->container['pages']->init();
        $this->container->fireEvent('onPagesInitialized', new Event(['pages' => $this->container['pages']]));
        $this->container->fireEvent('onPageInitialized', new Event(['page' => $this->container['page']]));

        /** @var Page $page */
        $page = $this->container['page'];

        if (!$page->routable()) {
            // If no page found, fire event
            $event = $this->container->fireEvent('onPageNotFound', new Event(['page' => $page]));

            if (isset($event->page)) {
                unset ($this->container['page']);
                $this->container['page'] = $event->page;
            } else {
                throw new \RuntimeException('Page Not Found', 404);
            }
        }

    }
}
