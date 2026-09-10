<?php

/**
 * PHPUnit bootstrap for the Teams module test suite.
 *
 * Most Teams classes depend directly on core Omeka S, Laminas, and Doctrine
 * classes (e.g. Omeka\Entity\Item, Omeka\Permissions\Assertion\AssertionNegation),
 * none of which are installable via Composer on their own. Rather than
 * hand-rolling stand-in classes -- which would test against a fake API that
 * could silently diverge from upstream Omeka S -- tests that need those
 * classes load the real ones from an actual Omeka S installation.
 *
 * That installation is located by:
 *   1. The OMEKA_S_PATH environment variable, if set.
 *   2. This module's conventional deployment location, <omeka-s>/modules/Teams,
 *      i.e. three directories above this file.
 *
 * See docker/README.md for a ready-made Omeka S environment (with this
 * module installed and active) that satisfies this requirement, and
 * Tests\TestCase::requireOmeka() for how individual tests opt in.
 */

require __DIR__ . '/../vendor/autoload.php';

$omekaPath = getenv('OMEKA_S_PATH') ?: dirname(__DIR__, 3);
$omekaAutoload = $omekaPath . '/vendor/autoload.php';

if (is_file($omekaAutoload)) {
    require $omekaAutoload;
    define('TEAMS_TESTS_OMEKA_AVAILABLE', true);
} else {
    define('TEAMS_TESTS_OMEKA_AVAILABLE', false);
}
