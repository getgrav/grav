<?php

use Grav\Common\Data\Blueprint;
use Grav\Common\Helpers\JsonBody;
use Grav\Framework\Form\Traits\FormTrait;
use Nyholm\Psr7\ServerRequest;

/**
 * JSON sent with a request is decoded within limits, once.
 *
 * Object keys that share one PHP hash bucket make json_decode() quadratic: 65,536 of
 * them take seconds where ordinary keys take milliseconds. Every request that said it
 * carried JSON was decoded that way before any route or permission had been looked at.
 */
class JsonBodyTest extends \PHPUnit\Framework\TestCase
{
    private const LIMITS = ['max_bytes' => 16777216, 'max_members' => 10000, 'max_depth' => 512];

    /** One object whose integer keys all land in one hash bucket. */
    private static function colliding(int $members, bool $close = true): string
    {
        $json = '{' . implode(',', array_map(static fn($i) => '"' . ($i << 20) . '":0', range(0, $members - 1)));

        return $close ? $json . '}' : $json;
    }

    private static function request(string $body, string $type = 'application/json', string $method = 'POST'): ServerRequest
    {
        return new ServerRequest($method, 'http://localhost/', ['Content-Type' => $type], $body);
    }

    public function testOrdinaryPayloadsDecodeUnchanged(): void
    {
        // A config save: nested objects, lists, scalars.
        $config = ['pages' => ['theme' => 'quark2', 'order' => ['by' => 'default', 'dir' => 'asc']], 'languages' => ['supported' => ['en', 'fr']], 'cache' => ['enabled' => true, 'lifetime' => 604800]];
        // A page save with a large body full of colons, quotes, backslashes and JSON-looking text.
        $content = str_repeat("Time: 10:30 \"quoted\" C:\\path {\"k\":\"v\"} \\\" end\n", 40000);
        $page = ['header' => ['title' => 'Big', 'taxonomy' => ['tag' => ['a', 'b']]], 'content' => $content];
        // A translations save of 9,000 keys in one object.
        $translations = ['set' => array_combine(array_map(static fn($i) => "PLUGIN_X.KEY_$i", range(1, 9000)), array_fill(0, 9000, 'Value: text'))];
        // A long list of small objects (20,000 objects, 100,000 members in all).
        $list = array_map(static fn($i) => ['route' => "/p$i", 'order' => $i, 'a' => 1, 'b' => 2, 'c' => 3], range(1, 20000));

        foreach ([$config, $page, $translations, $list] as $value) {
            $json = json_encode($value);
            self::assertSame($value, JsonBody::decode($json, self::LIMITS));
        }
        self::assertGreaterThan(1000000, strlen(json_encode($page)));
    }

    public function testCollidingObjectIsRefusedQuickly(): void
    {
        $started = microtime(true);
        try {
            JsonBody::decode(self::colliding(65536), self::LIMITS);
            self::fail('A 65,536-member object was decoded');
        } catch (LengthException $e) {
            self::assertStringContainsString('10000', $e->getMessage());
        }
        self::assertLessThan(0.5, microtime(true) - $started);
    }

    public function testTruncatedBodyIsRejectedWithoutBuildingIt(): void
    {
        $started = microtime(true);
        $this->expectException(JsonException::class);
        try {
            JsonBody::decode(self::colliding(65536, false), self::LIMITS);
        } finally {
            self::assertLessThan(0.5, microtime(true) - $started);
        }
    }

    public function testObjectAtTheLimitPassesAndOneMoreDoesNot(): void
    {
        $limits = ['max_members' => 100] + self::LIMITS;
        self::assertCount(100, JsonBody::decode(self::colliding(100), $limits));

        $this->expectException(LengthException::class);
        JsonBody::decode(self::colliding(101), $limits);
    }

    public function testManyLargeObjectsShareOneAllowance(): void
    {
        $limits = ['max_members' => 100] + self::LIMITS;
        // Two objects of 70 cost 2 * 70^2 = 9,800, under 100^2; a third goes over.
        $two = '[' . self::colliding(70) . ',' . self::colliding(70) . ']';
        self::assertCount(2, JsonBody::decode($two, $limits));

        $json = '[' . self::colliding(70) . ',' . self::colliding(70) . ',' . self::colliding(70) . ']';
        $this->expectException(LengthException::class);
        JsonBody::decode($json, $limits);
    }

    public function testColonsInsideStringsAreNotMembers(): void
    {
        $limits = ['max_members' => 3] + self::LIMITS;
        $json = json_encode(['a' => str_repeat('x:y "q": \\', 100), 'b' => ['c' => '{"d":1,"e":2}']]);

        self::assertIsArray(JsonBody::decode($json, $limits));
    }

    public function testBytesAndDepthLimits(): void
    {
        try {
            JsonBody::decode('[' . str_repeat('1,', 100) . '1]', ['max_bytes' => 100] + self::LIMITS);
            self::fail('Body over max_bytes was decoded');
        } catch (LengthException) {
        }

        $deep = str_repeat('[', 20) . str_repeat(']', 20);
        self::assertIsArray(JsonBody::decode($deep, self::LIMITS));
        $this->expectException(LengthException::class);
        JsonBody::decode($deep, ['max_depth' => 10] + self::LIMITS);
    }

    public function testZeroDisablesMemberAndByteLimits(): void
    {
        $limits = ['max_bytes' => 0, 'max_members' => 0, 'max_depth' => 512];
        self::assertCount(200, JsonBody::decode(self::colliding(200), $limits));
    }

    public function testInvalidJsonThrowsJsonException(): void
    {
        $this->expectException(JsonException::class);
        JsonBody::decode('{"a":', self::LIMITS);
    }

    public function testParseRequestSetsParsedBodyOnce(): void
    {
        $request = JsonBody::parseRequest(self::request('{"title":"x","list":[1,2]}'), self::LIMITS);
        self::assertSame(['title' => 'x', 'list' => [1, 2]], $request->getParsedBody());
        self::assertArrayHasKey(JsonBody::ATTRIBUTE, $request->getAttributes());
        self::assertNull($request->getAttribute(JsonBody::ATTRIBUTE));
        self::assertNull(JsonBody::rejection($request));

        // A second pass leaves the request alone, even with a parsed body that differs.
        $changed = $request->withParsedBody(['other' => true]);
        self::assertSame($changed, JsonBody::parseRequest($changed, self::LIMITS));
    }

    public function testParseRequestCoversMediaTypesAndMethods(): void
    {
        foreach (['application/vnd.api+json', 'Application/JSON ; charset=utf-8'] as $type) {
            self::assertSame(['a' => 1], JsonBody::parseRequest(self::request('{"a":1}', $type), self::LIMITS)->getParsedBody());
        }
        self::assertSame(['a' => 1], JsonBody::parseRequest(self::request('{"a":1}', 'application/json', 'GET'), self::LIMITS)->getParsedBody());

        $form = self::request('{"a":1}', 'text/plain');
        self::assertSame($form, JsonBody::parseRequest($form, self::LIMITS));
    }

    public function testScalarAndInvalidBodiesLeaveNoParsedBody(): void
    {
        foreach (['"a string"', '12345', 'true', ''] as $body) {
            $request = JsonBody::parseRequest(self::request($body), self::LIMITS);
            self::assertNull($request->getParsedBody(), $body);
            self::assertNull(JsonBody::rejection($request));
        }

        $request = JsonBody::parseRequest(self::request('{"a":'), self::LIMITS);
        self::assertNull($request->getParsedBody());
        self::assertInstanceOf(JsonException::class, $request->getAttribute(JsonBody::ATTRIBUTE));
        // Invalid JSON is not refused here; the handler decides, as before.
        self::assertNull(JsonBody::rejection($request));
    }

    public function testOverLimitBodyIsAnswered413(): void
    {
        $request = JsonBody::parseRequest(self::request(self::colliding(65536)), self::LIMITS);
        self::assertNull($request->getParsedBody());
        self::assertInstanceOf(LengthException::class, $request->getAttribute(JsonBody::ATTRIBUTE));

        $response = JsonBody::rejection($request);
        self::assertNotNull($response);
        self::assertSame(413, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame(413, $body['status']);
        self::assertStringContainsString('members', $body['detail']);
    }

    public function testSameBodyIsParsedOnce(): void
    {
        $json = json_encode(['a' => range(1, 1000)]);
        $first = JsonBody::decode($json, self::LIMITS);
        // The second call returns the remembered result; the outcome is the same either way.
        self::assertSame($first, JsonBody::decode($json, self::LIMITS));

        $bad = self::colliding(20000);
        foreach ([1, 2] as $attempt) {
            try {
                JsonBody::decode($bad, self::LIMITS);
                self::fail("Attempt $attempt decoded an over-limit body");
            } catch (LengthException) {
            }
        }
    }

    public function testLimitsComeFromConfig(): void
    {
        $config = new \Grav\Common\Config\Config([]);
        self::assertSame(JsonBody::DEFAULTS, JsonBody::limits($config));

        $config->set('system.request.json', ['max_bytes' => 0, 'max_members' => '500', 'max_depth' => 0]);
        self::assertSame(['max_bytes' => 0, 'max_members' => 500, 'max_depth' => 512], JsonBody::limits($config));
    }

    public function testFormJsonFieldsShareTheAllowance(): void
    {
        $form = new class () {
            use FormTrait;

            public array $errors = [];

            public function getBlueprint(): Blueprint
            {
                return new Blueprint('form');
            }

            public function setError(string $error): void
            {
                $this->errors[] = $error;
            }

            public function decode(array $data): array
            {
                return $this->jsonDecode($data);
            }
        };

        // Ordinary fields still decode, scalars and null included.
        self::assertSame(['a' => ['x' => 1], 'b' => [1, 2], 'c' => 5, 'd' => null], $form->decode(['a' => '{"x":1}', 'b' => '[1,2]', 'c' => '5', 'd' => 'null', 'e' => ' ']));
        self::assertSame([], $form->errors);

        // Two fields of 8,000 colliding members: each under the limit, together over it.
        $started = microtime(true);
        $decoded = $form->decode(['x' => self::colliding(8000), 'y' => ['z' => self::colliding(8000)]]);
        self::assertLessThan(0.5, microtime(true) - $started);
        self::assertArrayHasKey('x', $decoded);
        self::assertArrayNotHasKey('z', $decoded['y']);
        self::assertCount(1, $form->errors);
        self::assertStringContainsString('too large', $form->errors[0]);
    }
}
