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

// Regression test: submitting the site edit form with every team checkbox
// unchecked sends a 'team' key whose value is null (not an empty array),
// since an HTML multi-select with nothing selected submits no value at all.
// This previously crashed with "array_diff(): Argument #1 ($array) must be
// of type array, null given" in Module::siteUpdate().
$teamH = $api->create('team', [
    'o:name' => $runId . ' Team H',
    'o:description' => 'Fixture team for the site update null-teams regression test',
])->getContent();
$siteH = $api->create('sites', [
    'o:title' => $runId . ' Site H',
    'o:slug' => $runId . '-site-h',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [$teamH->id()],
])->getContent();
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_site WHERE site_id = ? AND team_id = ?',
    [$siteH->id(), $teamH->id()]
), 'Site H is associated with team H before the null-teams update');

try {
    $api->update('sites', $siteH->id(), [
        'o:title' => $siteH->title(),
        'o:slug' => $siteH->slug(),
        'o:theme' => 'default',
        'o:is_public' => false,
        'team' => null,
    ]);
    $entityManager->clear();
    authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
    $assert(true, 'Updating a site with a null "team" value (all checkboxes unchecked) does not throw');
} catch (\Throwable $e) {
    $assert(false, sprintf('Updating a site with a null "team" value does not throw (threw %s: %s)', get_class($e), $e->getMessage()));
}

$assertSame(0, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_site WHERE site_id = ? AND team_id = ?',
    [$siteH->id(), $teamH->id()]
), 'A null "team" value removes all of a site\'s existing team associations');

// Module::resourceTemplateUpdate() uses the shared diffTeamIds() helper
// (also used by assetUpdate()/siteUpdate()) to only add/remove the
// team_resource_template rows that actually changed, leaving unchanged
// rows in place.
$teamRTX = $api->create('team', [
    'o:name' => $runId . ' Team RT-X',
    'o:description' => 'Fixture team for the resource template diff regression test',
])->getContent();
$teamRTY = $api->create('team', [
    'o:name' => $runId . ' Team RT-Y',
    'o:description' => 'Fixture team for the resource template diff regression test',
])->getContent();
$resourceTemplateX = $api->create('resource_templates', [
    'o:label' => $runId . ' Resource Template X',
    'o-module-teams:Team' => [$teamRTX->id()],
])->getContent();

$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_resource_template WHERE resource_template_id = ? AND team_id = ?',
    [$resourceTemplateX->id(), $teamRTX->id()]
), 'Resource template X is associated with team RT-X after creation');

$api->update('resource_templates', $resourceTemplateX->id(), [
    'o:label' => $resourceTemplateX->label(),
    'o-module-teams:Team' => [$teamRTX->id(), $teamRTY->id()],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_resource_template WHERE resource_template_id = ? AND team_id = ?',
    [$resourceTemplateX->id(), $teamRTY->id()]
), 'Adding team RT-Y via the resource template update form creates a new team_resource_template row');
$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_resource_template WHERE resource_template_id = ? AND team_id = ?',
    [$resourceTemplateX->id(), $teamRTX->id()]
), 'Team RT-X\'s team_resource_template row is left in place (not deleted/recreated) when only RT-Y is added');

$api->update('resource_templates', $resourceTemplateX->id(), [
    'o:label' => $resourceTemplateX->label(),
    'o-module-teams:Team' => [$teamRTY->id()],
]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(0, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_resource_template WHERE resource_template_id = ? AND team_id = ?',
    [$resourceTemplateX->id(), $teamRTX->id()]
), 'Removing team RT-X via the resource template update form removes only its team_resource_template row');
$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_resource_template WHERE resource_template_id = ? AND team_id = ?',
    [$resourceTemplateX->id(), $teamRTY->id()]
), 'Team RT-Y\'s team_resource_template row remains after removing RT-X');

// Module::userUpdate() uses diffTeamIds() to only add/remove the team_user
// rows that actually changed, and updates (rather than recreates) a kept
// team's role when it differs.
$teamUX = $api->create('team', [
    'o:name' => $runId . ' Team U-X',
    'o:description' => 'Fixture team for the user update diff regression test',
])->getContent();
$teamUY = $api->create('team', [
    'o:name' => $runId . ' Team U-Y',
    'o:description' => 'Fixture team for the user update diff regression test',
])->getContent();
$siteUX = $api->create('sites', [
    'o:title' => $runId . ' Site U-X',
    'o:slug' => $runId . '-site-ux',
    'o:theme' => 'default',
    'o:is_public' => false,
    'team' => [$teamUX->id()],
])->getContent();
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$userU = createFixtureUser($entityManager, $runId . '-u@example.com', $runId . '-u', 'site_admin');
$teamUserU = $api->create('team-user', [
    'team' => $teamUX->id(),
    'user' => $userU->getId(),
    'role' => $managerRole->id(),
])->getContent();
$entityManager->getRepository(TeamUser::class)
    ->findOneBy(['team' => $teamUX->id(), 'user' => $userU->getId()])
    ->setCurrent(true);
$entityManager->flush();
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$teamUXRowBefore = $connection->fetchAssociative(
    'SELECT id, role_id FROM team_user WHERE team_id = ? AND user_id = ?',
    [$teamUX->id(), $userU->getId()]
);
$assertSame($managerRole->id(), $teamUXRowBefore['role_id'], 'User U starts as a manager of team U-X');
$assertSame('admin', $connection->fetchOne(
    'SELECT role FROM site_permission WHERE site_id = ? AND user_id = ?',
    [$siteUX->id(), $userU->getId()]
), 'User U starts with the admin site permission on site U-X (granted by the manager role)');

// Add team U-Y while keeping team U-X, with team U-X's role unchanged.
$api->update('users', $userU->getId(), [
    'o-module-teams:Team' => [$teamUX->id(), $teamUY->id()],
    'o-module-teams:TeamRole' => [
        $teamUX->id() => $managerRole->id(),
        $teamUY->id() => $viewerRole->id(),
    ],
], [], ['isPartial' => true]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$teamUXRowAfterAdd = $connection->fetchAssociative(
    'SELECT id, role_id, is_current FROM team_user WHERE team_id = ? AND user_id = ?',
    [$teamUX->id(), $userU->getId()]
);
$assertSame(
    $teamUXRowBefore['id'],
    $teamUXRowAfterAdd['id'],
    'Team U-X\'s existing team_user row is left in place (not deleted/recreated) when only U-Y is added'
);
$assertSame(1, (int) $teamUXRowAfterAdd['is_current'], 'Team U-X remains the user\'s current team after U-Y is added');
$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_user WHERE team_id = ? AND user_id = ?',
    [$teamUY->id(), $userU->getId()]
), 'Adding team U-Y via the user update form creates a new team_user row');

// Change team U-X's role (manager -> viewer) while keeping both teams.
$api->update('users', $userU->getId(), [
    'o-module-teams:Team' => [$teamUX->id(), $teamUY->id()],
    'o-module-teams:TeamRole' => [
        $teamUX->id() => $viewerRole->id(),
        $teamUY->id() => $viewerRole->id(),
    ],
], [], ['isPartial' => true]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$teamUXRowAfterRoleChange = $connection->fetchAssociative(
    'SELECT id, role_id FROM team_user WHERE team_id = ? AND user_id = ?',
    [$teamUX->id(), $userU->getId()]
);
$assertSame(
    $teamUXRowBefore['id'],
    $teamUXRowAfterRoleChange['id'],
    'Team U-X\'s team_user row is updated in place (not deleted/recreated) when its role changes'
);
$assertSame($viewerRole->id(), $teamUXRowAfterRoleChange['role_id'], 'Team U-X\'s role is updated to viewer');
$assertSame('viewer', $connection->fetchOne(
    'SELECT role FROM site_permission WHERE site_id = ? AND user_id = ?',
    [$siteUX->id(), $userU->getId()]
), 'User U\'s site permission on site U-X is re-synced to viewer after the role change');

// Remove team U-X entirely.
$api->update('users', $userU->getId(), [
    'o-module-teams:Team' => [$teamUY->id()],
    'o-module-teams:TeamRole' => [
        $teamUY->id() => $viewerRole->id(),
    ],
], [], ['isPartial' => true]);
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);

$assertSame(0, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_user WHERE team_id = ? AND user_id = ?',
    [$teamUX->id(), $userU->getId()]
), 'Removing team U-X via the user update form deletes its team_user row');
$assertSame(0, $connection->fetchOne(
    'SELECT COUNT(*) FROM site_permission WHERE site_id = ? AND user_id = ?',
    [$siteUX->id(), $userU->getId()]
), 'Removing team U-X via the user update form also removes the site permission it granted');
$assertSame(1, $connection->fetchOne(
    'SELECT COUNT(*) FROM team_user WHERE team_id = ? AND user_id = ?',
    [$teamUY->id(), $userU->getId()]
), 'Team U-Y\'s team_user row remains after removing U-X');

// ACL: team-granted create/update/delete permissions must be honored for
// every core Omeka role that can otherwise perform those actions at all
// (e.g. editor, site_admin), and denied when the team role or team
// membership does not grant them, regardless of which of those core roles
// the user holds.
$teamAcl = $api->create('team', [
    'o:name' => $runId . ' Team ACL',
    'o:description' => 'Fixture team for the ACL allow/deny regression test',
])->getContent();
$teamAclOther = $api->create('team', [
    'o:name' => $runId . ' Team ACL Other',
    'o:description' => 'Fixture team unrelated to the item set under test',
])->getContent();
$itemSetAcl = $api->create('item_sets', ['add_team' => [$teamAcl->id()]])->getContent();

// Module::itemSetUpdate() must tolerate an update request that carries no
// add_team/remove_team keys at all (e.g. an update that only changes
// o:is_public), since those keys are only present when the item set edit
// form actually submits team changes.
set_error_handler(static function (int $errno, string $errstr) {
    throw new \ErrorException($errstr, 0, $errno);
}, E_WARNING);
try {
    $api->update('item_sets', $itemSetAcl->id(), ['o:is_public' => true], [], ['isPartial' => true]);
    $noWarningOnTeamlessItemSetUpdate = true;
} catch (\ErrorException $e) {
    $noWarningOnTeamlessItemSetUpdate = false;
} finally {
    restore_error_handler();
}
$assert(
    $noWarningOnTeamlessItemSetUpdate,
    'Updating an item set without add_team/remove_team keys in the request does not raise a warning'
);


$makeCurrentTeamMember = static function (
    EntityManager $entityManager,
    \Omeka\Api\Manager $api,
    string $email,
    string $coreRole,
    $team,
    $teamRole
): User {
    $user = createFixtureUser($entityManager, $email, $email, $coreRole);
    $api->create('team-user', [
        'team' => $team->id(),
        'user' => $user->getId(),
        'role' => $teamRole->id(),
    ]);
    $entityManager->getRepository(TeamUser::class)
        ->findOneBy(['team' => $team->id(), 'user' => $user->getId()])
        ->setCurrent(true);
    $entityManager->flush();
    $entityManager->clear();
    return $user;
};

foreach (['editor', 'site_admin'] as $coreRoleForAclTest) {
    $managerOnTeamAcl = $makeCurrentTeamMember(
        $entityManager,
        $api,
        $runId . '-acl-' . $coreRoleForAclTest . '-manager@example.com',
        $coreRoleForAclTest,
        $teamAcl,
        $managerRole
    );
    authenticateAdmin($entityManager, $auth, $managerOnTeamAcl->getEmail(), 'FixtureUserPass123!');
    $updateAllowed = true;
    try {
        $api->update('item_sets', $itemSetAcl->id(), ['o:is_public' => true], [], ['isPartial' => true]);
    } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
        $updateAllowed = false;
    }
    $entityManager->clear();
    authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
    $assert(
        $updateAllowed,
        "A {$coreRoleForAclTest} who is the current manager of the item set's own team can update it"
    );

    $viewerOnTeamAcl = $makeCurrentTeamMember(
        $entityManager,
        $api,
        $runId . '-acl-' . $coreRoleForAclTest . '-viewer@example.com',
        $coreRoleForAclTest,
        $teamAcl,
        $viewerRole
    );
    authenticateAdmin($entityManager, $auth, $viewerOnTeamAcl->getEmail(), 'FixtureUserPass123!');
    $updateDenied = false;
    try {
        $api->update('item_sets', $itemSetAcl->id(), ['o:is_public' => true], [], ['isPartial' => true]);
    } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
        $updateDenied = true;
    }
    $entityManager->clear();
    authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
    $assert(
        $updateDenied,
        "A {$coreRoleForAclTest} whose team role lacks modify permission cannot update the item set"
    );

    $managerOnOtherTeam = $makeCurrentTeamMember(
        $entityManager,
        $api,
        $runId . '-acl-' . $coreRoleForAclTest . '-otherteam@example.com',
        $coreRoleForAclTest,
        $teamAclOther,
        $managerRole
    );
    authenticateAdmin($entityManager, $auth, $managerOnOtherTeam->getEmail(), 'FixtureUserPass123!');
    $updateDeniedOtherTeam = false;
    try {
        $api->update('item_sets', $itemSetAcl->id(), ['o:is_public' => true], [], ['isPartial' => true]);
    } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
        $updateDeniedOtherTeam = true;
    }
    $entityManager->clear();
    authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
    $assert(
        $updateDeniedOtherTeam,
        "A {$coreRoleForAclTest} who manages an unrelated team cannot update another team's item set"
    );

    $noTeamUser = createFixtureUser(
        $entityManager,
        $runId . '-acl-' . $coreRoleForAclTest . '-noteam@example.com',
        $runId . '-acl-' . $coreRoleForAclTest . '-noteam',
        $coreRoleForAclTest
    );
    authenticateAdmin($entityManager, $auth, $noTeamUser->getEmail(), 'FixtureUserPass123!');
    $updateDeniedNoTeam = false;
    try {
        $api->update('item_sets', $itemSetAcl->id(), ['o:is_public' => true], [], ['isPartial' => true]);
    } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
        $updateDeniedNoTeam = true;
    }
    $entityManager->clear();
    authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
    $assert(
        $updateDeniedNoTeam,
        "A {$coreRoleForAclTest} with no current team cannot update the item set"
    );
}

// Teams is a gate in front of core, not a source of new grants: a user must
// have the ability both in their team (via team role permissions) and in
// core (via their core Omeka role) to perform an action. A researcher can
// never create item sets in core, regardless of team permissions, so team
// membership with full item permissions must not override that.
$researcherOnTeamAcl = $makeCurrentTeamMember(
    $entityManager,
    $api,
    $runId . '-acl-researcher-manager@example.com',
    'researcher',
    $teamAcl,
    $managerRole
);
authenticateAdmin($entityManager, $auth, $researcherOnTeamAcl->getEmail(), 'FixtureUserPass123!');
$researcherCreateDenied = false;
try {
    $api->create('item_sets', ['add_team' => [$teamAcl->id()]]);
} catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
    $researcherCreateDenied = true;
}
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
$assert(
    $researcherCreateDenied,
    'A researcher who is a team manager with full item permissions still cannot create an item set, '
        . 'because core never permits researchers to create item sets at all'
);

// The counterpart case: an editor who is a team manager with full item
// permissions, and whose core role can create item sets, is allowed to.
$editorOnTeamAcl = $makeCurrentTeamMember(
    $entityManager,
    $api,
    $runId . '-acl-editor-manager-create@example.com',
    'editor',
    $teamAcl,
    $managerRole
);
authenticateAdmin($entityManager, $auth, $editorOnTeamAcl->getEmail(), 'FixtureUserPass123!');
$editorCreateAllowed = true;
try {
    $api->create('item_sets', ['add_team' => [$teamAcl->id()]]);
} catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
    $editorCreateAllowed = false;
}
$entityManager->clear();
authenticateAdmin($entityManager, $auth, $adminEmail, $adminPassword);
$assert(
    $editorCreateAllowed,
    'An editor who is a team manager with full item permissions can create an item set, '
        . 'because both team and core permit it'
);

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
