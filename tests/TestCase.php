<?php

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for the Teams module test suite.
 *
 * Args:
 *     None.
 *
 * Most tests in this suite exercise classes that depend directly on core
 * Omeka S, Laminas, or Doctrine classes, so a real Omeka S installation
 * (with its own Composer autoloader) must be available. By default, setUp()
 * skips the test when one cannot be found; see requireOmeka() for how to
 * make one available, and override setUp() (without calling
 * parent::setUp()) for tests that genuinely need no Omeka S classes.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireOmeka();
    }

    /**
     * Skip the current test unless a real Omeka S installation is available.
     *
     * Point the OMEKA_S_PATH environment variable at an Omeka S checkout
     * (with `composer install` already run there) to enable these tests
     * locally, or run the suite inside the Docker environment in docker/,
     * which already provisions one. See docker/README.md.
     *
     * Returns:
     *     void
     */
    protected function requireOmeka(): void
    {
        if (!TEAMS_TESTS_OMEKA_AVAILABLE) {
            $this->markTestSkipped(
                'Requires a real Omeka S installation. Set the OMEKA_S_PATH '
                . 'environment variable to one, or run this suite inside the '
                . 'Docker environment described in docker/README.md.'
            );
        }
    }
}
