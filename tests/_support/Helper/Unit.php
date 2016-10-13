<?php
namespace Helper;

use Codeception;
// here you can define custom actions
// all public methods declared in helper class will be available in $I

/**
 * Class Unit
 * @package Helper
 */
class Unit extends Codeception\Module
{
    /**
     * HOOK: used after configuration is loaded
     */
    public function _initialize() {
    }

    /**
     * HOOK: on every Actor class initialization
     */
    public function _cleanup() {
    }

    /**
     * HOOK: before suite
     *
     * @param array $settings
     */
    public function _beforeSuite($settings = []) {
    }

    /**
     * HOOK: after suite
     **/
    public function _afterSuite() {
    }

    /**
     * HOOK: before each step
     *
     * @param Codeception\Step $step*
     */
    public function _beforeStep(Codeception\Step $step) {
    }

    /**
     * HOOK: after each step
     *
     * @param Codeception\Step $step
     */
    public function _afterStep(Codeception\Step $step) {
    }

    /**
     * HOOK: before each suite
     *
     * @param Codeception\TestCase $test
     */
    public function _before(Codeception\TestCase $test) {
    }

    /**
     * HOOK: before each suite
     *
     * @param Codeception\TestCase $test
     */
    public function _after(Codeception\TestCase $test) {
    }

    /**
     * HOOK: on fail
     *
     * @param Codeception\TestCase $test
     * @param $fail
     */
    public function _failed(Codeception\TestCase $test, $fail) {
    }
}
