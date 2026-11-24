<?php

use Grav\Common\Filesystem\Folder;

/**
 * Signing in changes the session id, and the session left behind under the old id knows which
 * id replaced it. The old id only proves what its holder knew before the login, which is what
 * somebody who planted that id in the visitor's browser knows as well, so a request that still
 * sends it gets a new and empty session, never the one the login created. PHP never starts a
 * real session on the command line, so these tests send their requests to PHP's built-in server.
 */
class SessionRegeneratedIdTest extends \PHPUnit\Framework\TestCase
{
    private const COOKIE = 'grav-site-test';

    public function testOldIdDoesNotResumeTheNewSession(): void
    {
        $this->assertOldIdIsRetired(true);
    }

    /**
     * Without strict mode PHP opens whatever id it is given, the new id recorded in the old
     * session included.
     */
    public function testOldIdDoesNotResumeTheNewSessionWithoutStrictMode(): void
    {
        $this->assertOldIdIsRetired(false);
    }

    private function assertOldIdIsRetired(bool $strict): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('Needs proc_open().');
        }

        $root = sys_get_temp_dir() . '/grav-session-test-' . uniqid();
        mkdir($root . '/sessions', 0700, true);
        $process = null;

        try {
            file_put_contents($root . '/index.php', $this->script($root . '/sessions', $strict));

            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $this->assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);

            $env = getenv();
            unset($env['PHP_CLI_SERVER_WORKERS']);
            $process = proc_open(
                [PHP_BINARY, '-S', $address, '-t', $root],
                [1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']],
                $pipes,
                $root,
                $env
            );
            $this->assertIsResource($process);

            $deadline = microtime(true) + 5;
            do {
                $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
                if (is_resource($connection)) {
                    fclose($connection);
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline && proc_get_status($process)['running']);
            $this->assertNotFalse($connection, file_get_contents($root . '/server.log'));

            // A visitor gets a session, then signs in with it: the id changes.
            $guest = $this->request($address, '/');
            $oldId = $guest['id'];
            $this->assertNotEmpty($oldId);

            $login = $this->request($address, '/?login=1', $oldId);
            $newId = $login['id'];
            $this->assertNotEmpty($newId);
            $this->assertNotSame($oldId, $newId);
            $this->assertSame('admin', $login['username']);

            // The old id comes back while the session it named is still kept.
            $late = $this->request($address, '/', $oldId);
            $this->assertNull($late['username'], 'The old id is not signed in');
            $this->assertNotSame($newId, $late['id'], 'The old id does not lead to the new session');
            $this->assertNotSame($oldId, $late['id'], 'The old session is not resumed');
            $this->assertStringNotContainsString($newId, implode("\n", $late['headers']), 'The new id is not sent to the old one');

            // Nothing changed for the visitor who signed in.
            $visitor = $this->request($address, '/', $newId);
            $this->assertSame($newId, $visitor['id']);
            $this->assertSame('admin', $visitor['username']);
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            Folder::delete($root);
        }
    }

    /**
     * @return array{id: string|null, username: string|null, headers: string[]}
     */
    private function request(string $address, string $path, ?string $id = null): array
    {
        $http = ['timeout' => 5, 'ignore_errors' => true];
        if (null !== $id) {
            $http['header'] = 'Cookie: ' . self::COOKIE . '=' . $id;
        }

        $body = file_get_contents('http://' . $address . $path, false, stream_context_create(['http' => $http]));
        $headers = $http_response_header ?? [];
        $data = json_decode((string)$body, true);
        $this->assertIsArray($data, implode("\n", $headers) . "\n\n" . $body);

        return $data + ['headers' => $headers];
    }

    /**
     * The page the built-in server runs: start the session, sign in when asked to (as the login
     * plugin does: new id first, then the user), and say which session answered.
     */
    private function script(string $savePath, bool $strict): string
    {
        $autoload = var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true);
        $options = var_export(['name' => self::COOKIE, 'save_path' => $savePath, 'use_strict_mode' => $strict], true);

        return <<<PHP
<?php
require {$autoload};

\$session = new \Grav\Framework\Session\Session({$options});
\$session->start();
if (isset(\$_GET['login'])) {
    \$session->regenerateId();
    \$session->username = 'admin';
}

header('Content-Type: application/json');
echo json_encode(['id' => \$session->getId(), 'username' => \$session->username]);
\$session->close();
PHP;
    }
}
