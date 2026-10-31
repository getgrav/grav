<?php

use Grav\Common\Filesystem\Folder;

class RouterTest extends \Codeception\Test\Unit
{
    public function testServesWellKnownFiles(): void
    {
        $this->assertRoutes([
            '/asset.txt',
            '/.well-known/anything',
            '/.well-known/acme-challenge/token',
            '/.well-known/security.txt',
            '/nested/.well-known/anything',
        ], 'static file');
    }

    public function testServesPublicUserFiles(): void
    {
        $this->assertRoutes([
            '/user/themes/theme/css/site.css',
            '/user/plugins/plugin/js/app.js',
            '/user/pages/01.home/image.jpg',
            '/user/accounts/avatars/avatar.png',
            '/user/accounts/admin/avatar.JPG',
            '/user/env/example.com/accounts/avatars/avatar.webp',
            '/user/data/flex-objects/photo.jpg',
            '/user/data/uploads/document.pdf',
            '/user/env/example.com/data/uploads/photo.png',
            '/images/derivative.jpg',
            '/assets/combined.css',
        ], 'static file');
    }

    public function testKeepsRestrictedFilesBehindGrav(): void
    {
        $this->assertRoutes([
            '/.git/config',
            '/.env',
            '/nested/.hidden/file.txt',
            '/.well-known-private/file.txt',
            '/.well-known/.git/config',
            '/.well-known/acme-challenge/.secret',
            '/.hidden/.well-known/file.txt',
            '/.well-known/file.md',
            '/cache/.well-known/file.txt',
            '/bin/file.txt',
            '/logs/file.txt',
            '/backup/file.txt',
            '/tmp/download/package.zip',
            '/webserver-configs/file.txt',
            '/tests/file.txt',
            '/system/.well-known/file.json',
            '/vendor/file.json',
            '/user/.well-known/file.yaml',
            '/user/config/security.pem',
            '/user/config/image.jpg',
            '/USER/CONFIG/security.pem',
            '/user/env/example.com/config/security.pem',
            '/user/accounts/admin.yaml',
            '/user/accounts/avatars/avatar.svg',
            '/user/accounts/avatars/file.docx',
            '/user/accounts/image.jpg',
            '/user/accounts/admin/sub/image.png',
            '/user/env/example.com/accounts/admin.yaml',
            '/user/data/database.sqlite',
            '/user/data/app.log',
            '/user/data/uploads/image.svg',
            '/USER/DATA/database.sqlite',
            '/user/env/example.com/data/database.sqlite',
            '/user/plugins/plugin/plugin.phar',
            '/images/script.php',
            '/images/script.phtml',
            '/assets/script.php',
            '/vendor/file.shtml',
            '/LICENSE.txt',
            '/composer.lock',
            '/composer.json',
            '/.htaccess',
        ], 'grav index');
    }

    private function assertRoutes(array $paths, string $expected): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('Needs proc_open().');
        }

        $root = sys_get_temp_dir() . '/grav-router-test-' . uniqid();
        mkdir($root);
        $process = null;

        try {
            file_put_contents($root . '/index.php', '<?php echo "grav index";');
            foreach ($paths as $path) {
                $directory = dirname($root . $path);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                file_put_contents($root . $path, 'static file');
            }

            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $this->assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);

            $env = getenv();
            unset($env['GRAV_BASEDIR'], $env['PHP_CLI_SERVER_WORKERS']);
            $process = proc_open(
                [PHP_BINARY, '-S', $address, '-t', $root, dirname(__DIR__, 3) . '/system/router.php'],
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

            $context = stream_context_create(['http' => ['timeout' => 5]]);
            foreach ($paths as $path) {
                $this->assertSame($expected, file_get_contents('http://' . $address . $path, false, $context), $path);
            }
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            Folder::delete($root);
        }
    }
}
