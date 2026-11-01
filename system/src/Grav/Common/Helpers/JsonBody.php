<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use JsonException;
use LengthException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Decodes JSON that arrives with a request (the body, a form field) within limits.
 *
 * json_decode() stores object members in PHP hash tables. Keys picked to share
 * one bucket make every insert walk all the earlier ones, so one object with
 * 65,000 such members takes seconds to decode instead of milliseconds. PHP caps
 * form posts with `max_input_vars` for the same reason; json_decode() has no cap,
 * so the members are counted first, in one linear pass that builds no arrays.
 *
 * Only members of the same object can collide, so the cost of a body is the sum
 * of the squares of its object sizes. A body may hold objects of up to
 * `max_members` members, and all its objects together may cost no more than one
 * object of that size: long lists of small objects pass, many big ones do not.
 *
 * The last body decoded is remembered, so the several places that read the body
 * of one request (the request service, Uri::post(), the API plugin) parse it once.
 */
final class JsonBody
{
    /** Request attribute holding the outcome: null when decoded, else the exception. */
    public const ATTRIBUTE = 'grav.json_body';

    /** Media types whose body is decoded into the parsed body of the request. */
    public const MEDIA_TYPES = ['application/json', 'application/vnd.api+json'];

    /** @var array{max_bytes:int,max_members:int,max_depth:int} */
    public const DEFAULTS = [
        'max_bytes' => 16777216,
        'max_members' => 10000,
        'max_depth' => 512,
    ];

    /** @var array{json:string,limits:array,result:mixed,error:Throwable|null}|null */
    private static ?array $last = null;

    /**
     * Limits from `system.request.json`, falling back to the defaults.
     *
     * @param Config|null $config
     * @return array{max_bytes:int,max_members:int,max_depth:int}
     */
    public static function limits(?Config $config = null): array
    {
        if (null === $config) {
            try {
                $grav = Grav::instance();
                $config = isset($grav['config']) ? $grav['config'] : null;
            } catch (Throwable) {
                $config = null;
            }
        }

        $limits = self::DEFAULTS;
        $configured = $config ? $config->get('system.request.json') : null;
        if (is_array($configured)) {
            foreach ($limits as $key => $value) {
                if (isset($configured[$key]) && is_numeric($configured[$key])) {
                    $limits[$key] = (int)$configured[$key];
                }
            }
        }
        if ($limits['max_depth'] < 1) {
            $limits['max_depth'] = self::DEFAULTS['max_depth'];
        }

        return $limits;
    }

    /**
     * Decode JSON from a request into arrays.
     *
     * @param string $json
     * @param array|null $limits Limits to apply, see limits(). Configured limits when null.
     * @param int|null $allowance Lets several pieces of JSON from one request (the fields of a
     *                            form) share the allowance of one body. Pass the same variable,
     *                            starting at null, to each call.
     * @return mixed
     * @throws LengthException The JSON is larger, deeper or has more object members than allowed.
     * @throws JsonException The JSON is not valid.
     */
    public static function decode(string $json, ?array $limits = null, ?int &$allowance = null): mixed
    {
        $limits = $limits ?? self::limits();
        $shared = func_num_args() > 2;

        $last = self::$last;
        if (!$shared && $last !== null && $last['limits'] === $limits && $last['json'] === $json) {
            if ($last['error']) {
                throw $last['error'];
            }

            return $last['result'];
        }

        $result = null;
        $error = null;
        try {
            self::check($json, $limits, $allowance);
            $result = json_decode($json, true, $limits['max_depth'], JSON_THROW_ON_ERROR);
        } catch (JsonException|LengthException $e) {
            $error = $e;
        }

        if (!$shared) {
            self::$last = ['json' => $json, 'limits' => $limits, 'result' => $result, 'error' => $error];
        }
        if ($error) {
            throw $error;
        }

        return $result;
    }

    /**
     * Decode a JSON request body into the parsed body of the request, once.
     *
     * The outcome is kept in the ATTRIBUTE attribute: null when the body was decoded (a body
     * that is empty or a JSON scalar leaves no parsed body), else the exception. Invalid JSON
     * leaves no parsed body, as before; a body over the limits is answered by rejection().
     *
     * @param ServerRequestInterface $request
     * @param array|null $limits
     * @return ServerRequestInterface
     */
    public static function parseRequest(ServerRequestInterface $request, ?array $limits = null): ServerRequestInterface
    {
        if (array_key_exists(self::ATTRIBUTE, $request->getAttributes()) || !self::isJsonRequest($request)) {
            return $request;
        }

        $json = (string)$request->getBody();
        if ($json === '') {
            return $request->withParsedBody(null)->withAttribute(self::ATTRIBUTE, null);
        }

        try {
            $decoded = self::decode($json, $limits);
        } catch (JsonException|LengthException $e) {
            return $request->withParsedBody(null)->withAttribute(self::ATTRIBUTE, $e);
        }

        // withParsedBody() accepts an array, an object or null. A JSON scalar
        // ("a string", 12345, true) is not a body a handler can read.
        return $request->withParsedBody(is_array($decoded) ? $decoded : null)->withAttribute(self::ATTRIBUTE, null);
    }

    /**
     * A 413 response for a request whose JSON body was over the limits, else null.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface|null
     */
    public static function rejection(ServerRequestInterface $request): ?ResponseInterface
    {
        $error = $request->getAttribute(self::ATTRIBUTE);
        if (!$error instanceof LengthException) {
            return null;
        }

        $body = json_encode([
            'status' => 413,
            'title' => 'Payload Too Large',
            'detail' => $error->getMessage(),
        ], JSON_UNESCAPED_SLASHES);

        return new Response(413, [
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store, max-age=0',
        ], (string)$body);
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     */
    public static function isJsonRequest(ServerRequestInterface $request): bool
    {
        $header = $request->getHeaderLine('Content-Type');
        $type = strtolower(trim(explode(';', $header, 2)[0]));

        return in_array($type, self::MEDIA_TYPES, true);
    }

    /**
     * Check size, depth and object members without building any arrays.
     *
     * @param string $json
     * @param array $limits
     * @param int|null $allowance
     * @return void
     * @throws LengthException
     * @throws JsonException
     */
    private static function check(string $json, array $limits, ?int &$allowance): void
    {
        $maxBytes = $limits['max_bytes'];
        if ($maxBytes > 0 && strlen($json) > $maxBytes) {
            throw new LengthException(sprintf('The JSON is %d bytes, more than the %d allowed.', strlen($json), $maxBytes));
        }

        if (!json_validate($json, $limits['max_depth'])) {
            if (json_last_error() === JSON_ERROR_DEPTH) {
                throw new LengthException(sprintf('The JSON is nested deeper than the %d levels allowed.', $limits['max_depth']));
            }
            throw new JsonException(json_last_error_msg(), json_last_error());
        }

        $max = $limits['max_members'];
        if ($max <= 0) {
            return;
        }

        $budget = $allowance ?? $max * $max;

        // Every member has a colon, so when there are few colons (counting those in
        // strings, too) there is nothing to look at more closely.
        $colons = substr_count($json, ':');
        if ($colons * $colons <= $budget && $colons <= $max) {
            $cost = $colons * $colons;
        } else {
            $cost = self::cost($json, $max);
        }

        if ($cost > $budget) {
            throw new LengthException(sprintf('The JSON has more object members than the %d allowed.', $max));
        }
        $allowance = $budget - $cost;
    }

    /**
     * Sum of the squares of the member counts of all objects in valid JSON.
     *
     * @param string $json
     * @param int $max
     * @return int
     * @throws LengthException One object has more than $max members.
     */
    private static function cost(string $json, int $max): int
    {
        // Empty the strings, so that only structural colons and braces remain. In valid
        // JSON a backslash always starts a two-character escape, so replacing `\\` and
        // then `\"` leaves strings with no quote inside them.
        $structure = preg_replace('/"[^"]*+"/', '""', str_replace(['\\\\', '\\"'], '__', $json));
        if (null === $structure || false === preg_match_all('/[{}:]/', $structure, $matches)) {
            // Out of PCRE resources: count every colon as a member of one object.
            $colons = substr_count($json, ':');
            if ($colons > $max) {
                throw new LengthException(sprintf('The JSON has more object members than the %d allowed.', $max));
            }

            return $colons * $colons;
        }

        $cost = 0;
        $members = 0;
        $open = [];
        foreach ($matches[0] as $token) {
            if ($token === ':') {
                if (++$members > $max) {
                    throw new LengthException(sprintf('A JSON object has more than the %d members allowed.', $max));
                }
            } elseif ($token === '{') {
                $open[] = $members;
                $members = 0;
            } else {
                $cost += $members * $members;
                $members = (int)array_pop($open);
            }
        }

        return $cost;
    }
}
