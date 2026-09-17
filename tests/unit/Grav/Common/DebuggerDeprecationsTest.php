<?php

use Grav\Common\Debugger;

class DebuggerDeprecationsTest extends \Codeception\Test\Unit
{
    private function debugger(): DebuggerDeprecationsProbe
    {
        return new DebuggerDeprecationsProbe();
    }

    public function testRepeatedNoticesKeepOneTraceAndTheirOccurrenceCount(): void
    {
        $debugger = $this->debugger();
        for ($i = 0; $i < 4579; ++$i) {
            $debugger->deprecatedErrorHandler(E_DEPRECATED, 'Repeated plugin notice', '/plugins/example.php', 78);
        }

        $messages = $debugger->messages();
        $this->assertCount(1, $messages);
        $this->assertSame(4579, $messages[0]['count']);
        $this->assertSame('Repeated plugin notice', $messages[0]['message']);
        $this->assertSame('/plugins/example.php', $messages[0]['file']);
        $this->assertLessThan(20000, strlen(json_encode($messages)));
    }

    public function testDifferentMessagesFilesAndLinesRemainSeparate(): void
    {
        $debugger = $this->debugger();
        foreach ([
            ['First notice', '/plugins/example.php', 78],
            ['Second notice', '/plugins/example.php', 78],
            ['First notice', '/plugins/other.php', 78],
            ['First notice', '/plugins/example.php', 79],
        ] as [$message, $file, $line]) {
            $debugger->deprecatedErrorHandler(E_USER_DEPRECATED, $message, $file, $line);
        }

        $messages = $debugger->messages();
        $this->assertCount(4, $messages);
        $this->assertArrayNotHasKey('count', $messages[0]);
    }

    public function testYamlNoticesAreGroupedByDocumentInsteadOfParserLocation(): void
    {
        $debugger = $this->debugger();
        $debugger->yamlNotice('/pages/first.md');
        $debugger->yamlNotice('/pages/second.md');
        $debugger->yamlNotice('/pages/first.md');

        $messages = $debugger->messages();
        $this->assertCount(2, $messages);
        $this->assertSame('/pages/first.md', $messages[0]['file']);
        $this->assertSame(2, $messages[0]['count']);
        $this->assertSame('/pages/second.md', $messages[1]['file']);
        $this->assertArrayNotHasKey('count', $messages[1]);
    }

    public function testDisabledDebuggerDoesNotCollectNotices(): void
    {
        $debugger = $this->debugger();
        $debugger->disableCollection();
        $this->assertTrue($debugger->deprecatedErrorHandler(E_DEPRECATED, 'Ignored notice', '/plugins/example.php', 78));
        $this->assertSame([], $debugger->messages());
    }

    public function testTwigNoticesKeepDistinctTemplateLocations(): void
    {
        $debugger = $this->debugger();
        $loader = new class implements \Twig\Loader\LoaderInterface {
            public function getSourceContext(string $name): \Twig\Source
            {
                return new \Twig\Source('{{ notice() }}', $name, '/templates/' . $name);
            }

            public function getCacheKey(string $name): string
            {
                return 'debugger-deprecation-test/' . $name;
            }

            public function isFresh(string $name, int $time): bool
            {
                return true;
            }

            public function exists(string $name): bool
            {
                return true;
            }
        };
        $twig = new \Twig\Environment($loader, ['debug' => true]);
        $twig->addFunction(new \Twig\TwigFunction('notice', static function (): string {
            trigger_error('Deprecated template function', E_USER_DEPRECATED);

            return '';
        }));

        set_error_handler([$debugger, 'deprecatedErrorHandler']);
        try {
            $twig->render('first.twig');
            $twig->render('second.twig');
            $twig->render('first.twig');
        } finally {
            restore_error_handler();
        }

        $messages = array_values(array_filter($debugger->messages(), static fn(array $message): bool => $message['message'] === 'Deprecated template function'));
        $this->assertCount(2, $messages);
        $this->assertSame('/templates/first.twig', $messages[0]['file']);
        $this->assertSame(2, $messages[0]['count']);
        $this->assertSame('/templates/second.twig', $messages[1]['file']);
    }
}

class DebuggerDeprecationsProbe extends Debugger
{
    // Avoid registering a process-wide error handler for these isolated tests.
    public function __construct()
    {
        $this->enabled = true;
    }

    public function messages(): array
    {
        return $this->getDeprecations();
    }

    public function disableCollection(): void
    {
        $this->enabled = false;
    }

    public function yamlNotice(string $document): void
    {
        $this->deprecatedErrorHandler(E_USER_DEPRECATED, 'Deprecated YAML syntax', '/vendor/symfony/yaml/Parser.php', 100);
    }
}
