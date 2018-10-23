<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class RenderProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'render';
    public $title = 'Render';

    public function process()
    {
        $container = $this->container;
        $output =  $container['output'];

        if ($output instanceof \Psr\Http\Message\ResponseInterface) {
            // Support for custom output providers like Slim Framework.
        } else {
            // Use internal Grav output.
            $container->output = $output;
            $container->fireEvent('onOutputGenerated');

            // Set the header type
            $container->header();

            echo $container->output;

            // remove any output
            $container->output = '';

            $this->container->fireEvent('onOutputRendered');
        }
    }
}
