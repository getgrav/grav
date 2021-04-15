<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Uri;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use function explode;
use function fopen;
use function function_exists;
use function in_array;
use function is_array;
use function strtolower;
use function trim;

/**
 * Class RequestServiceProvider
 * @package Grav\Common\Service
 */
class RequestServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['request'] = function () {
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory, // ServerRequestFactory
                $psr17Factory, // UriFactory
                $psr17Factory, // UploadedFileFactory
                $psr17Factory  // StreamFactory
            );

            $server = $_SERVER;
            if (false === isset($server['REQUEST_METHOD'])) {
                $server['REQUEST_METHOD'] = 'GET';
            }
            $method = $server['REQUEST_METHOD'];

            $headers = function_exists('getallheaders') ? getallheaders() : $creator::getHeadersFromServer($_SERVER);

            $post = null;
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                foreach ($headers as $headerName => $headerValue) {
                    if ('content-type' !== strtolower($headerName)) {
                        continue;
                    }

                    $contentType = strtolower(trim(explode(';', $headerValue, 2)[0]));
                    switch ($contentType) {
                        case 'application/x-www-form-urlencoded':
                        case 'multipart/form-data':
                            $post = $_POST;
                            break 2;
                        case 'application/json':
                        case 'application/vnd.api+json':
                            try {
                                $json = file_get_contents('php://input');
                                $post = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                                if (!is_array($post)) {
                                    $post = null;
                                }
                            } catch (JsonException $e) {
                                $post = null;
                            }
                            break 2;
                    }
                }
            }

            // Remove _url from ngnix routes.
            $get = $_GET;
            unset($get['_url']);
            if (isset($server['QUERY_STRING'])) {
                $query = $server['QUERY_STRING'];
                if (strpos($query, '_url=') !== false) {
                    parse_str($query, $query);
                    unset($query['_url']);
                    $server['QUERY_STRING'] = http_build_query($query);
                }
            }

            return $creator->fromArrays($server, $headers, $_COOKIE, $get, $post, $_FILES, fopen('php://input', 'rb') ?: null);
        };

        $container['route'] = $container->factory(function () {
            return clone Uri::getCurrentRoute();
        });
    }
}
