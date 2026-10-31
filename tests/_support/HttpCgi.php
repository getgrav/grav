<?php

class HttpCgi
{
    public static function run(string $code, array $server = []): array
    {
        $file = tempnam(GRAV_ROOT . '/tests/_output', 'http-');
        $autoload = var_export(GRAV_ROOT . '/vendor/autoload.php', true);
        file_put_contents($file, "<?php\ndefine('GRAV_CLI', true);\ndefine('GRAV_REQUEST_TIME', microtime(true));\nrequire {$autoload};\n" . $code);
        try {
            $process = proc_open(
                implode(' ', array_map('escapeshellarg', [dirname(PHP_BINARY) . '/php-cgi', '-d', 'display_errors=stderr', '-d', 'output_buffering=0', '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED)])),
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                GRAV_ROOT,
                $server + ['REDIRECT_STATUS' => '1', 'SCRIPT_FILENAME' => $file, 'REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com'] + getenv()
            );
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);

            return [$exit, $output, $error];
        } finally {
            unlink($file);
        }
    }
}
