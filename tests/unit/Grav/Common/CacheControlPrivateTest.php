<?php

require_once GRAV_ROOT . '/tests/_support/HttpCgi.php';

class CacheControlPrivateTest extends \PHPUnit\Framework\TestCase
{
    public function testExplicitAndLateCookieHeadersArePrivate(): void
    {
        foreach ([
            "header('Set-Cookie: visitor=one'); header('Cache-Control: public, s-maxage=600, max-age=60');",
            "header('Cache-Control: public, max-age=60'); header('set-cookie: visitor=one');",
            "\$grav->header(new \\Grav\\Framework\\Psr7\\Response(200, ['Cache-Control' => 'public, max-age=60', 'Set-Cookie' => 'visitor=one']));",
            "header('Cache-Control: public'); header('Cache-Control: max-age=60', false); header('Set-Cookie: visitor=one');",
        ] as $code) {
            $output = $this->response($code);
            self::assertStringContainsString('Cache-Control: private, max-age=60', $output);
            self::assertStringNotContainsString('s-maxage', $output);
            self::assertStringNotContainsString('Cache-Control: public', $output);
        }
    }

    public function testRestrictiveDirectivesArePreserved(): void
    {
        $output = $this->response("header('Set-Cookie: visitor=one'); header('Cache-Control: no-store, no-cache, max-age=0, must-revalidate');");
        self::assertStringContainsString('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', $output);
    }

    public function testPrivateDefaultsKeepEarlierRestrictions(): void
    {
        self::assertSame('private, no-store, must-revalidate, max-age=0', \Grav\Common\Utils::getCacheControl(60, [
            'Set-Cookie: visitor=one',
            'Cache-Control: no-store, max-age=0, must-revalidate',
        ]));
        self::assertSame('private, max-age=10', \Grav\Common\Utils::getCacheControl(60, [
            'Set-Cookie: visitor=one',
            'Cache-Control: public, max-age=10, s-maxage=600',
        ]));
    }

    public function testCookielessResponsesRetainTheirCachePolicy(): void
    {
        $output = $this->response("header('Cache-Control: public, s-maxage=600, max-age=60');");
        self::assertStringContainsString('Cache-Control: public, s-maxage=600, max-age=60', $output);
    }

    public function testPageHeadersAndDownloadsUsePrivateDefaults(): void
    {
        foreach (['regular', 'flex', 'download'] as $kind) {
            $code = <<<'PHP_CODE'
                $grav['config'] = new \Grav\Common\Config\Config(['system' => ['cache' => ['enabled' => true], 'pages' => ['expires' => 60]]]);
                header('Set-Cookie: visitor=one');
                PHP_CODE;
            if ($kind === 'download') {
                $code .= "\\Grav\\Common\\Utils::download(__FILE__, true, 0, 1024, ['mime' => 'text/plain']);";
            } elseif ($kind === 'regular') {
                $code .= <<<'PHP_CODE'
                    $page = new \Grav\Common\Page\Page();
                    $page->header(['expires' => 60, 'etag' => false, 'last_modified' => false]);
                    $page->templateFormat('html');
                    echo json_encode($page->httpHeaders());
                    PHP_CODE;
            } else {
                $code .= <<<'PHP_CODE'
                    $page = new class {
                        use \Grav\Framework\Flex\Pages\Traits\PageLegacyTrait;
                        public function templateFormat() { return 'html'; }
                        public function cacheControl() { return null; }
                        public function expires() { return 60; }
                        public function lastModified() { return false; }
                        public function eTag() { return false; }
                    };
                    echo json_encode($page->httpHeaders());
                    PHP_CODE;
            }
            $output = $this->response($code);
            self::assertStringContainsString($kind === 'download' ? 'Cache-Control: private, max-age=60' : '"Cache-Control":"private, max-age=60"', $output, $kind);
        }
    }

    public function testExplicitPageCachePolicyIsPrivateAtEmission(): void
    {
        $output = $this->response(<<<'PHP_CODE'
            $page = new \Grav\Common\Page\Page();
            $page->header(['expires' => 60, 'cache_control' => 'public, s-maxage=600, max-age=60', 'etag' => false, 'last_modified' => false]);
            $page->templateFormat('html');
            foreach ($page->httpHeaders() as $name => $value) {
                header($name . ': ' . $value);
            }
            header('Set-Cookie: visitor=late');
            PHP_CODE
        );
        self::assertStringContainsString('Cache-Control: private, max-age=60', $output);
        self::assertStringNotContainsString('s-maxage', $output);
    }

    private function response(string $code): string
    {
        [$exit, $output, $error] = HttpCgi::run('$grav = \\Grav\\Common\\Grav::instance();' . $code . "\necho 'done';");
        self::assertSame(0, $exit, $error);
        self::assertSame('', $error);

        return $output;
    }
}
