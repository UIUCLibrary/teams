#!/usr/bin/env php
<?php
declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\Authentication\AuthenticationService;
use Laminas\Uri\Http;
use Omeka\Api\Response;
use Omeka\Entity\Module as ModuleEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\User;
use Omeka\Mvc\Application;
use Teams\Entity\Team;
use Teams\Entity\TeamResource;
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

// Reproduce the "add a team to a site" path via the site edit form
// (Teams\Module::siteUpdate(), triggered by PUT /api/sites/{id} with a
// 'team' key), which is responsible for syncing every item belonging to the
// team's TeamResource associations into the site's item_site membership.
// This previously flushed the entity manager once per item inside the sync
// loop (Module::updateItemSites()), and Doctrine recomputes change sets for
// every managed entity on every flush(), so the cost grew quadratically with
// the team's item count and could exceed PHP's execution time limit for
// teams with a few hundred items or more.
$teamD = $api->create('team', [
    'o:name' => $runId . '-team-d',
    'o:description' => 'Integration team D (site-update-form item-sync scenario)',
])->getContent();
$siteD = $api->create('sites', [
    'o:title' => $runId . ' Site D',
    'o:slug' => $runId . '-site-d',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();

$teamDItemCount = 20;
$teamDItemIds = [];
for ($i = 0; $i < $teamDItemCount; $i++) {
    $teamDItemIds[] = $api->create('items', [])->getContent()->id();
}
$teamDEntity = $entityManager->getRepository(Team::class)->find($teamD->id());
foreach ($teamDItemIds as $itemId) {
    $resource = $entityManager->getRepository(Resource::class)->find($itemId);
    $entityManager->persist(new TeamResource($teamDEntity, $resource));
}
$entityManager->flush();
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$itemSiteCount = static function (Connection $connection, array $itemIds, int $siteId): int {
    return (int) $connection->fetchOne(sprintf(
        'SELECT COUNT(*) FROM item_site WHERE item_id IN (%s) AND site_id = %d',
        implode(',', array_map('intval', $itemIds)),
        $siteId
    ));
};

$beforeItemSiteAdd = $itemSiteCount($connection, $teamDItemIds, $siteD->id());
$assertSame(0, $beforeItemSiteAdd, 'No item_site rows exist for team D\'s items on site D before the team is added to the site');

$start = microtime(true);
$api->update('sites', $siteD->id(), [
    'o:title' => $siteD->title(),
    'o:slug' => $siteD->slug(),
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [$teamD->id()],
]);
$elapsed = microtime(true) - $start;
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$afterItemSiteAdd = $itemSiteCount($connection, $teamDItemIds, $siteD->id());
$assertSame($teamDItemCount, $afterItemSiteAdd, 'Adding a team to a site via the site update form adds every one of the team\'s items to the site');
$assert($elapsed < 15.0, sprintf('Adding a team with %d items to a site via the site update form completes well within the default PHP execution time limit (took %.2fs)', $teamDItemCount, $elapsed));

$api->update('sites', $siteD->id(), [
    'o:title' => $siteD->title(),
    'o:slug' => $siteD->slug(),
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$afterItemSiteRemove = $itemSiteCount($connection, $teamDItemIds, $siteD->id());
$assertSame(0, $afterItemSiteRemove, 'Removing a team from a site via the site update form removes the team\'s items from the site');

// Reproduce the "add a site to a team" path via the TEAM update form
// (Teams\Api\Adapter\TeamAdapter::update(), triggered by PUT /api/teams/{id}
// with an 'o:team_sites' key). Until this fix, this path only synced Omeka
// site permissions and never touched item_site membership at all, so an
// item belonging to a team never actually appeared on a site added to that
// team via this form.
$teamE = $api->create('team', [
    'o:name' => $runId . '-team-e',
    'o:description' => 'Integration team E (team-update-form item-sync scenario)',
])->getContent();
$siteE = $api->create('sites', [
    'o:title' => $runId . ' Site E',
    'o:slug' => $runId . '-site-e',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();
$itemE = $api->create('items', [])->getContent();
$teamEEntity = $entityManager->getRepository(Team::class)->find($teamE->id());
$itemEResource = $entityManager->getRepository(Resource::class)->find($itemE->id());
$entityManager->persist(new TeamResource($teamEEntity, $itemEResource));
$entityManager->flush();
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(0, $itemSiteCount($connection, [$itemE->id()], $siteE->id()), 'No item_site row exists for team E\'s item on site E before the site is added to the team');

$api->update('team', $teamE->id(), [
    'o:name' => $teamE->name(),
    'o:description' => $teamE->description(),
    'o:team_sites' => [$siteE->id()],
    'o:team_users' => [],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(1, $itemSiteCount($connection, [$itemE->id()], $siteE->id()), 'Adding a site to a team via the team update form adds the team\'s item to the site');

$api->update('team', $teamE->id(), [
    'o:name' => $teamE->name(),
    'o:description' => $teamE->description(),
    'o:team_sites' => [],
    'o:team_users' => [],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(0, $itemSiteCount($connection, [$itemE->id()], $siteE->id()), 'Removing a site from a team via the team update form removes the team\'s item from the site');

// Overlap scenario: an item shared by two teams that both grant the same
// site must keep its site membership as long as ANY of its teams still
// grants that site, whether the site is removed from one team via the team
// update form or via the site update form.
$teamF = $api->create('team', [
    'o:name' => $runId . '-team-f',
    'o:description' => 'Integration team F (overlap scenario)',
])->getContent();
$teamG = $api->create('team', [
    'o:name' => $runId . '-team-g',
    'o:description' => 'Integration team G (overlap scenario)',
])->getContent();
$siteFG = $api->create('sites', [
    'o:title' => $runId . ' Site FG',
    'o:slug' => $runId . '-site-fg',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();
$sharedItem = $api->create('items', [])->getContent();

$teamFEntity = $entityManager->getRepository(Team::class)->find($teamF->id());
$teamGEntity = $entityManager->getRepository(Team::class)->find($teamG->id());
$sharedItemResource = $entityManager->getRepository(Resource::class)->find($sharedItem->id());
$entityManager->persist(new TeamResource($teamFEntity, $sharedItemResource));
$entityManager->persist(new TeamResource($teamGEntity, $sharedItemResource));
$entityManager->flush();
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

// Add the site to both teams via the team update form.
$api->update('team', $teamF->id(), [
    'o:name' => $teamF->name(),
    'o:description' => $teamF->description(),
    'o:team_sites' => [$siteFG->id()],
    'o:team_users' => [],
]);
$api->update('team', $teamG->id(), [
    'o:name' => $teamG->name(),
    'o:description' => $teamG->description(),
    'o:team_sites' => [$siteFG->id()],
    'o:team_users' => [],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(1, $itemSiteCount($connection, [$sharedItem->id()], $siteFG->id()), 'A shared item gains site membership once either of its two teams grants the site');

// Remove the site from team F only (via the team update form). Team G still
// grants the site, so the shared item must keep its membership.
$api->update('team', $teamF->id(), [
    'o:name' => $teamF->name(),
    'o:description' => $teamF->description(),
    'o:team_sites' => [],
    'o:team_users' => [],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(1, $itemSiteCount($connection, [$sharedItem->id()], $siteFG->id()), 'Removing the site from one of two overlapping teams (via the team update form) does not strip the shared item\'s site membership while the other team still grants it');

// Now remove the site from team G as well (via the site update form this
// time, to exercise that path too). No team grants the site any more, so
// the shared item must finally lose its membership.
$api->update('sites', $siteFG->id(), [
    'o:title' => $siteFG->title(),
    'o:slug' => $siteFG->slug(),
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(0, $itemSiteCount($connection, [$sharedItem->id()], $siteFG->id()), 'Removing the site from the last remaining team (via the site update form) finally strips the shared item\'s site membership');

// Regression test: browsing items scoped to a site that has no team at all
// must not crash (Module::filterByTeam() previously read an undefined
// $team_id variable in this case, causing "count(): Argument #1 ($value)
// must be of type Countable|array, null given").
$siteWithNoTeam = $api->create('sites', [
    'o:title' => $runId . ' Site With No Team',
    'o:slug' => $runId . '-site-no-team',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [],
])->getContent();
try {
    $noTeamSiteItems = $api->search('items', ['site_id' => $siteWithNoTeam->id()])->getContent();
    $assert(is_array($noTeamSiteItems), 'Browsing items scoped to a site with no associated team does not throw and returns a result set');
} catch (\Throwable $e) {
    $assert(false, sprintf('Browsing items scoped to a site with no associated team does not throw (threw %s: %s)', get_class($e), $e->getMessage()));
}

// Regression test: the site edit form's "Teams" field must not be required,
// since a site is not required to be associated with any team.
$formElementManager = $services->get('FormElementManager');
$siteForm = $formElementManager->get(\Omeka\Form\SiteForm::class);
$teamFormInput = $siteForm->getInputFilter()->get('team');
$assertSame(false, $teamFormInput->isRequired(), 'The site edit form\'s "Teams" field is not required');

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
