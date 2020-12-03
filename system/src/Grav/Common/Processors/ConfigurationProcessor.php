<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\File\YamlFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ConfigurationProcessor extends ProcessorBase
{
    public $id = '_config';
    public $title = 'Configuration';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();
        $config = $this->container['config'];
        $config->init();
        $this->container['plugins']->setup();

        if ($config->get('versions') === null) {
            $filename = GRAV_ROOT . '/user/config/versions.yaml';
            if (!is_file($filename)) {
                $versions = [
                    'core' => [
                        'grav' => [
                            'version' => GRAV_VERSION,
                            'history' => ['version' => GRAV_VERSION, 'date' => gmdate('Y-m-d H:i:s')]
                        ]
                    ]
                ];
                $config->set('versions', $versions);

                $file = new YamlFile($filename, new YamlFormatter(['inline' => 4]));
                $file->save($versions);
            }
        }
        $this->stopTimer();

        return $handler->handle($request);
    }
}
