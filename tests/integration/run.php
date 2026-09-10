#!/usr/bin/env php
<?php
declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\Authentication\AuthenticationService;
use Laminas\Uri\Http;
use Omeka\Api\Response;
use Omeka\Entity\Module as ModuleEntity;
use Omeka\Entity\User;
use Omeka\Mvc\Application;
use Teams\Entity\TeamUser;
use Teams\Service\SitePermissionManager;

$omekaPath = dirname(__DIR__, 4);
chdir($omekaPath);
require $omekaPath . '/bootstrap.php';

$application = Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();
$services->get('Router')->setRequestUri(new Http(getenv('OMEKA_SERVER_URL') ?: 'http://localhost:8080'));

/** @var \Omeka\Api\Manager $api */
$api = $services->get('Omeka\ApiManager');
/** @var EntityManager $entityManager */
$entityManager = $services->get('Omeka\EntityManager');
$connection = $services->get('Omeka\Connection');
/** @var AuthenticationService $auth */
$auth = $services->get('Omeka\AuthenticationService');

$failures = 0;

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        fwrite(STDOUT, "PASS: {$message}\n");
        return;
    }
    $failures++;
    fwrite(STDERR, "FAIL: {$message}\n");
};

$assertSame = static function ($expected, $actual, string $message) use (&$failures): void {
    if ($expected === $actual) {
        fwrite(STDOUT, "PASS: {$message}\n");
        return;
    }
    $failures++;
    fwrite(STDERR, sprintf("FAIL: %s (expected %s, got %s)\n", $message, var_export($expected, true), var_export($actual, true)));
};

$adminEmail = getenv('TEAMS_ADMIN_EMAIL') ?: 'admin@example.com';
$adminPassword = getenv('TEAMS_ADMIN_PASSWORD') ?: 'TeamsTestPass123!';

authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
$assert($auth->hasIdentity(), 'Authenticated as the Omeka admin test user');

$moduleEntity = $entityManager->getRepository(ModuleEntity::class)->findOneBy(['id' => 'Teams']);
$assert($moduleEntity instanceof ModuleEntity && $moduleEntity->isActive(), 'Teams module is active in Omeka');

$runId = 'teams-int-' . gmdate('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

$managerRole = $api->create('team-role', [
    'o:name' => $runId . '-manager',
    'comment' => 'Integration-test manager role',
    'can_add_users' => 1,
    'can_add_items' => 1,
    'can_add_itemsets' => 1,
    'can_modify_resources' => 1,
    'can_delete_resources' => 1,
    'can_add_site_pages' => 1,
])->getContent();
$viewerRole = $api->create('team-role', [
    'o:name' => $runId . '-viewer',
    'comment' => 'Integration-test viewer role',
    'can_add_users' => 0,
    'can_add_items' => 0,
    'can_add_itemsets' => 0,
    'can_modify_resources' => 0,
    'can_delete_resources' => 0,
    'can_add_site_pages' => 0,
])->getContent();

$teamA = $api->create('team', [
    'o:name' => $runId . '-team-a',
    'o:description' => 'Integration team A',
])->getContent();
$teamB = $api->create('team', [
    'o:name' => $runId . '-team-b',
    'o:description' => 'Integration team B',
])->getContent();

$siteA = $api->create('sites', [
    'o:title' => $runId . ' Site A',
    'o:slug' => $runId . '-site-a',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();
$siteB = $api->create('sites', [
    'o:title' => $runId . ' Site B',
    'o:slug' => $runId . '-site-b',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();

$teamAUsers = [
    createFixtureUser($entityManager, $runId . '-a1@example.com', $runId . '-a1', 'site_admin'),
    createFixtureUser($entityManager, $runId . '-a2@example.com', $runId . '-a2', 'site_admin'),
];
$teamBUsers = [
    createFixtureUser($entityManager, $runId . '-b1@example.com', $runId . '-b1', 'site_admin'),
    createFixtureUser($entityManager, $runId . '-b2@example.com', $runId . '-b2', 'site_admin'),
];

foreach ($teamAUsers as $user) {
    $api->create('team-user', [
        'team' => $teamA->id(),
        'user' => $user->getId(),
        'role' => $managerRole->id(),
    ]);
}
foreach ($teamBUsers as $user) {
    $api->create('team-user', [
        'team' => $teamB->id(),
        'user' => $user->getId(),
        'role' => $viewerRole->id(),
    ]);
}
$api->create('team-site', ['team' => $teamA->id(), 'site' => $siteA->id()]);
$api->create('team-site', ['team' => $teamB->id(), 'site' => $siteB->id()]);

$teamUserRows = (int) $connection->fetchOne('SELECT COUNT(*) FROM team_user');
$searchResponse = null;
$searchThrew = false;
try {
    $searchResponse = $api->search('team-user', []);
} catch (Throwable $e) {
    $searchThrew = true;
    fwrite(STDERR, 'FAIL: team-user search without criteria threw: ' . $e::class . ' - ' . $e->getMessage() . PHP_EOL);
    $failures++;
}

$assert(!$searchThrew, "Omeka\\Api\\Manager::search('team-user', []) does not throw");
if ($searchResponse instanceof Response) {
    $assertSame($teamUserRows, $searchResponse->getTotalResults(), 'Unscoped team-user search returns all team_user rows');
}

$fixtureUserIds = array_map(
    static fn(User $user): int => $user->getId(),
    array_merge($teamAUsers, $teamBUsers)
);
$fixtureSiteIds = [$siteA->id(), $siteB->id()];
$quotedUserIds = implode(',', array_map('intval', $fixtureUserIds));
$quotedSiteIds = implode(',', array_map('intval', $fixtureSiteIds));
$connection->executeStatement(sprintf(
    'DELETE FROM site_permission WHERE user_id IN (%s) AND site_id IN (%s)',
    $quotedUserIds,
    $quotedSiteIds
));
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$services->get(SitePermissionManager::class)->syncAllSitePermissions();

$rows = $connection->fetchAllAssociative(sprintf(
    'SELECT user_id, site_id, role FROM site_permission WHERE user_id IN (%s) AND site_id IN (%s) ORDER BY user_id, site_id',
    $quotedUserIds,
    $quotedSiteIds
));

$expected = [];
foreach ($teamAUsers as $user) {
    $expected[$user->getId() . ':' . $siteA->id()] = 'admin';
}
foreach ($teamBUsers as $user) {
    $expected[$user->getId() . ':' . $siteB->id()] = 'viewer';
}

$actual = [];
foreach ($rows as $row) {
    $actual[$row['user_id'] . ':' . $row['site_id']] = $row['role'];
}

$assertSame(count($expected), count($actual), 'syncAllSitePermissions creates one site_permission row for every expected fixture user/site pair');
foreach ($expected as $key => $role) {
    $assert(isset($actual[$key]), "syncAllSitePermissions created {$key}");
    if (isset($actual[$key])) {
        $assertSame($role, $actual[$key], "syncAllSitePermissions set {$key} to {$role}");
    }
}
foreach ($actual as $key => $role) {
    $assert(isset($expected[$key]), "syncAllSitePermissions did not create unexpected {$key} => {$role}");
}

// Reproduce the "team update form" path: this drives Api\Manager::update('team', ...)
// exactly as Teams\Controller\UpdateController::teamUpdateAction() does, rather than
// creating team-site/team-user rows directly through their own adapters. This is the
// path that regressed when TeamAdapter::update()'s before/after diff read a stale,
// already-loaded Doctrine collection instead of re-querying the database.
$teamC = $api->create('team', [
    'o:name' => $runId . '-team-c',
    'o:description' => 'Integration team C (team-update-form scenario)',
])->getContent();

$teamCUsers = [
    createFixtureUser($entityManager, $runId . '-c1@example.com', $runId . '-c1', 'site_admin'),
    createFixtureUser($entityManager, $runId . '-c2@example.com', $runId . '-c2', 'site_admin'),
];
foreach ($teamCUsers as $user) {
    $api->create('team-user', [
        'team' => $teamC->id(),
        'user' => $user->getId(),
        'role' => $managerRole->id(),
    ]);
}

$siteC = $api->create('sites', [
    'o:title' => $runId . ' Site C',
    'o:slug' => $runId . '-site-c',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();

$teamCUserIds = array_map(static fn(User $user): int => $user->getId(), $teamCUsers);

$permissionsForSite = static function (Connection $connection, array $userIds, int $siteId): array {
    $rows = $connection->fetchAllAssociative(sprintf(
        'SELECT user_id, role FROM site_permission WHERE user_id IN (%s) AND site_id = %d',
        implode(',', array_map('intval', $userIds)),
        $siteId
    ));
    $result = [];
    foreach ($rows as $row) {
        $result[(int) $row['user_id']] = $row['role'];
    }
    return $result;
};

// Sanity check: adding the site via the team update form should not have
// created permissions yet, since teamC and siteC are not yet associated.
$beforeAdd = $permissionsForSite($connection, $teamCUserIds, $siteC->id());
$assertSame(0, count($beforeAdd), 'No site_permission rows exist for team C users on site C before the site is added to the team');

// Every real submission of the team update form resends the FULL current
// o:team_users list alongside o:team_sites (see
// Teams\Controller\UpdateController::teamUpdateAction(), which always sends
// both keys, defaulting to an empty array only when the corresponding form
// section legitimately has nothing in it). A request that omits o:team_users
// entirely is not representative of that form and must not be used here:
// TeamAdapter::hydrate()'s o:team_users block always runs on a full (non-
// partial) update and treats a missing key as "no users are desired",
// deleting every existing team_user row. This helper keeps every simulated
// team-update-form submission below internally consistent with that
// contract.
$teamCUserPayload = static function (array $users, $role): array {
    return array_map(static fn(User $user): array => [
        'o:user' => ['o:id' => $user->getId()],
        'o:team_role' => ['o:id' => $role->id()],
    ], $users);
};

// Add site C to team C via the team API update, exactly as the team update
// form's "team_sites" field does, while resending the team's existing users
// unchanged (as the real form does).
$api->update('team', $teamC->id(), [
    'o:name' => $teamC->name(),
    'o:description' => $teamC->description(),
    'o:team_sites' => [$siteC->id()],
    'o:team_users' => $teamCUserPayload($teamCUsers, $managerRole),
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$afterAdd = $permissionsForSite($connection, $teamCUserIds, $siteC->id());
$assertSame(count($teamCUserIds), count($afterAdd), 'Adding a site to a team via the team update form creates a site_permission row for every team member');
foreach ($teamCUserIds as $userId) {
    $assert(isset($afterAdd[$userId]) && $afterAdd[$userId] === 'admin', "Team update form grants userId={$userId} the correct role on the newly-added site");
}

$tu = $entityManager->getRepository(TeamUser::class)->findBy(['team' => $teamC->id()]);
$assertSame(count($teamCUsers), count($tu), 'Team C still has all of its team_user rows after a site-only-looking update resends the existing user list');

// Remove site C from team C via the team API update, exactly as the team
// update form does when a site is unchecked, again resending the team's
// existing users unchanged.
$api->update('team', $teamC->id(), [
    'o:name' => $teamC->name(),
    'o:description' => $teamC->description(),
    'o:team_sites' => [],
    'o:team_users' => $teamCUserPayload($teamCUsers, $managerRole),
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$afterRemove = $permissionsForSite($connection, $teamCUserIds, $siteC->id());
$assertSame(0, count($afterRemove), 'Removing a site from a team via the team update form removes the site_permission rows it granted');

// Reproduce the team-update-form user scenario too: adding/removing a
// o:team_users entry should sync/revoke permissions on the team's existing
// sites, going through the same before/after diff in TeamAdapter::update().
$api->update('team', $teamC->id(), [
    'o:name' => $teamC->name(),
    'o:description' => $teamC->description(),
    'o:team_sites' => [$siteC->id()],
    'o:team_users' => $teamCUserPayload($teamCUsers, $managerRole),
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$teamCUserFixture = createFixtureUser($entityManager, $runId . '-c3@example.com', $runId . '-c3', 'site_admin');
$api->update('team', $teamC->id(), [
    'o:name' => $teamC->name(),
    'o:description' => $teamC->description(),
    'o:team_sites' => [$siteC->id()],
    'o:team_users' => array_merge(
        $teamCUserPayload($teamCUsers, $managerRole),
        [[
            'o:user' => ['o:id' => $teamCUserFixture->getId()],
            'o:team_role' => ['o:id' => $viewerRole->id()],
        ]]
    ),
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$afterUserAdd = $permissionsForSite($connection, [$teamCUserFixture->getId()], $siteC->id());
$assertSame('viewer', $afterUserAdd[$teamCUserFixture->getId()] ?? null, 'Adding a user to a team via the team update form creates a site_permission row on the team\'s existing site');

$stillPresent = $permissionsForSite($connection, $teamCUserIds, $siteC->id());
$assertSame(count($teamCUserIds), count($stillPresent), 'Adding a new user to a team via the team update form does not disturb the site permissions of the team\'s existing members');

$api->update('team', $teamC->id(), [
    'o:name' => $teamC->name(),
    'o:description' => $teamC->description(),
    'o:team_sites' => [$siteC->id()],
    'o:team_users' => $teamCUserPayload($teamCUsers, $managerRole),
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$afterUserRemove = $permissionsForSite($connection, [$teamCUserFixture->getId()], $siteC->id());
$assertSame(0, count($afterUserRemove), 'Removing a user from a team via the team update form removes the site_permission row it granted');

if ($failures > 0) {
    fwrite(STDERR, sprintf("\nIntegration test failed with %d assertion(s).\n", $failures));
    exit(1);
}

fwrite(STDOUT, "\nIntegration test completed successfully.\n");
exit(0);

function authenticateAdmin(EntityManager $entityManager, AuthenticationService $auth, string $email, string $password): void
{
    $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$admin instanceof User) {
        throw new RuntimeException(sprintf('Admin user %s not found.', $email));
    }

    $adapter = $auth->getAdapter();
    if (method_exists($adapter, 'setIdentity')) {
        $adapter->setIdentity($email);
    }
    if (method_exists($adapter, 'setCredential')) {
        $adapter->setCredential($password);
    }
    $result = $auth->authenticate();
    if (!$result->isValid()) {
        throw new RuntimeException(sprintf('Authentication failed for %s.', $email));
    }
}

function createFixtureUser(EntityManager $entityManager, string $email, string $name, string $role): User
{
    $user = new User();
    $user->setEmail($email);
    $user->setName($name);
    $user->setRole($role);
    $user->setIsActive(true);
    $user->setPassword('FixtureUserPass123!');
    $entityManager->persist($user);
    $entityManager->flush();
    return $user;
}
