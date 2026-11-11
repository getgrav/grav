<?php

use Codeception\Util\Fixtures;
use Grav\Common\Scheduler\Job;

/**
 * A failed command job used to keep only its error output. Console commands print their errors
 * to standard output ($io->error() among them), so a failed run was recorded with nothing in it
 * and nobody could tell why it failed. Both streams are kept now.
 */
class JobFailureOutputTest extends \Codeception\Test\Unit
{
    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testFailureKeepsStandardOutput(): void
    {
        $job = $this->job('echo "[ERROR] Scan failed: provider unavailable"; exit(1);');

        $job->inForeground()->run();

        $this->assertFalse($job->isSuccessful());
        $this->assertSame('[ERROR] Scan failed: provider unavailable', $job->getOutput());
    }

    public function testFailureKeepsBothStreamsStandardOutputFirst(): void
    {
        $job = $this->job('echo "1 post to judge"; fwrite(STDERR, "PHP Fatal error: boom"); exit(255);');

        $job->inForeground()->run();

        $this->assertFalse($job->isSuccessful());
        $this->assertSame("1 post to judge\nPHP Fatal error: boom", $job->getOutput());
    }

    public function testFailureWithOnlyErrorOutputIsUnchanged(): void
    {
        $job = $this->job('fwrite(STDERR, "only stderr\n"); exit(1);');

        $job->inForeground()->run();

        $this->assertSame('only stderr', $job->getOutput());
    }

    public function testFailureWithNoOutputIsEmpty(): void
    {
        $job = $this->job('exit(1);');

        $job->inForeground()->run();

        $this->assertFalse($job->isSuccessful());
        $this->assertSame('', $job->getOutput());
    }

    public function testSuccessStillKeepsOnlyStandardOutput(): void
    {
        $job = $this->job('echo "done"; fwrite(STDERR, "a deprecation notice");');

        $job->inForeground()->run();

        $this->assertTrue($job->isSuccessful());
        $this->assertSame('done', $job->getOutput());
    }

    private function job(string $code): Job
    {
        return new Job(PHP_BINARY, ['-r', $code], 'failure-output-' . bin2hex(random_bytes(3)));
    }
}
