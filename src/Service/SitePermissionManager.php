<?php
namespace Teams\Service;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\SitePermission;
use Laminas\Log\Logger;
use Omeka\Settings\UserSettings;

/**
 * Manages Omeka site user permissions in sync with Teams role assignments.
 *
 * This service is the authoritative place for all site-permission business logic:
 * - Keeping Omeka native site roles (viewer / admin) in sync when a user's team role
 *   changes, or when sites are added/removed from a team.
 * - Updating the `default_item_sites` user setting that controls which sites appear
 *   in the item-creation form for a given user.
 *
 * All Teams entity reads go through the Omeka API (with bypass_team_filter so admin
 * operations are never restricted by the current user's active team). The entity
 * manager is used only for SitePermission writes, since Omeka exposes no API
 * resource for individual site permissions.
 */
class SitePermissionManager
{
    /**
     * @var ApiManager
     */
    private ApiManager $api;

    /**
     * @var EntityManager
     */
    private EntityManager $entityManager;

    /**
     * @var UserSettings
     */
    private UserSettings $userSettings;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @param ApiManager    $api
     * @param EntityManager $entityManager
     * @param UserSettings  $userSettings
     * @param Logger        $logger
     */
    public function __construct(ApiManager $api, EntityManager $entityManager, UserSettings $userSettings, Logger $logger)
    {
        $this->api = $api;
        $this->entityManager = $entityManager;
        $this->userSettings = $userSettings;
        $this->logger = $logger;
    }

    /**
     * Sync Omeka site permissions for a user in a specific team.
     *
     * Assigns ROLE_ADMIN if the team role has `can_add_site_pages`, otherwise ROLE_VIEWER,
     * for every site associated with the team. The final role is the highest across all
     * of the user's team memberships so that a downgrade in one team never overrides a
     * higher privilege granted by another.
     *
     * @param int  $userId
     * @param int  $teamId
     * @param bool $flush Whether to flush the entity manager after syncing. Pass
     *                    false when calling in a loop and flush once afterward.
     */
    public function syncSitePermissionsForUser(int $userId, int $teamId, bool $flush = true): void
    {
        $em = $this->entityManager;

        $teamUserReps = $this->api->search('team-user', [
            'team' => $teamId,
            'user' => $userId,
            'bypass_team_filter' => true,
        ])->getContent();

        if (empty($teamUserReps)) {
            return;
        }

        $teamUserRep = $teamUserReps[0];
        $user = $teamUserRep->user();       // Omeka\Entity\User
        $teamRole = $teamUserRep->role();   // Teams\Entity\TeamRole
        $canAddSitePages = $teamRole->getCanAddSitePages();
        $omekaSiteRole = $canAddSitePages
            ? SitePermission::ROLE_ADMIN
            : SitePermission::ROLE_VIEWER;

        $this->logger->info(sprintf(
            '[SitePermissionManager] syncSitePermissionsForUser: userId=%d, teamId=%d, teamRoleId=%d, teamRoleName="%s", canAddSitePages=%s => omekaSiteRole="%s"',
            $userId,
            $teamId,
            $teamRole->getId(),
            $teamRole->getName(),
            $canAddSitePages ? 'true' : 'false',
            $omekaSiteRole
        ));

        $teamSiteReps = $this->api->search('team-site', [
            'team' => $teamId,
            'bypass_team_filter' => true,
        ])->getContent();

        foreach ($teamSiteReps as $teamSiteRep) {
            $site = $em->find('Omeka\Entity\Site', $teamSiteRep->resource());
            if (!$site) {
                continue;
            }

            // Use the highest role across all of the user's teams for this site.
            $finalRole = $this->getHighestRoleFromTeams($userId, $site->getId()) ?? $omekaSiteRole;

            $sitePermissions = $site->getSitePermissions();
            $criteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));
            $existingPermission = $sitePermissions->matching($criteria)->first();

            if ($existingPermission) {
                $this->logger->info(sprintf(
                    '[SitePermissionManager] siteId=%d: updating existing SitePermission from "%s" to "%s" for userId=%d',
                    $site->getId(),
                    $existingPermission->getRole(),
                    $finalRole,
                    $userId
                ));
                $existingPermission->setRole($finalRole);
            } else {
                $this->logger->info(sprintf(
                    '[SitePermissionManager] siteId=%d: creating new SitePermission with role "%s" for userId=%d',
                    $site->getId(),
                    $finalRole,
                    $userId
                ));
                $sitePermission = new SitePermission();
                $sitePermission->setSite($site);
                $sitePermission->setUser($user);
                $sitePermission->setRole($finalRole);
                $em->persist($sitePermission);
                $sitePermissions->add($sitePermission);
            }
        }

        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Sync or remove Omeka site permissions for a user leaving a team.
     *
     * If the user still belongs to other teams that share a given site, their
     * permission is recalculated across all team memberships. If no team grants
     * access to a site, the permission is removed entirely.
     *
     * @param int      $userId
     * @param int      $teamId  The team the user is being removed from.
     * @param int|null $siteId  If set, only process this one site. When the TeamSite
     *                          row has already been deleted, the site entity is fetched
     *                          directly by ID rather than through TeamSite.
     * @param bool     $flush   Whether to flush the entity manager after syncing.
     */
    public function removeSitePermissionsForUser(int $userId, int $teamId, ?int $siteId = null, bool $flush = true): void
    {
        $em = $this->entityManager;

        $user = $em->find('Omeka\Entity\User', $userId);
        if (!$user) {
            return;
        }

        if ($siteId !== null) {
            // The TeamSite row has already been deleted by the time this method is
            // called. Fetch the site entity directly so permission cleanup still runs.
            $site = $em->find('Omeka\Entity\Site', $siteId);
            if (!$site) {
                if ($flush) {
                    $em->flush();
                }
                return;
            }
            $sites = [$site];
        } else {
            $teamSiteReps = $this->api->search('team-site', [
                'team' => $teamId,
                'bypass_team_filter' => true,
            ])->getContent();
            $sites = array_filter(array_map(
                fn($rep) => $em->find('Omeka\Entity\Site', $rep->resource()),
                $teamSiteReps
            ));
        }

        foreach ($sites as $site) {
            $sitePermissions = $site->getSitePermissions();
            $userCriteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));
            $existingPermission = $sitePermissions->matching($userCriteria)->first();

            if (!$existingPermission) {
                continue;
            }

            // Recalculate the role across all remaining team memberships.
            $syncedRole = $this->getHighestRoleFromTeams($userId, $site->getId());

            if ($syncedRole !== null) {
                $existingPermission->setRole($syncedRole);
            } else {
                $sitePermissions->removeElement($existingPermission);
                $em->remove($existingPermission);
            }
        }

        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Sync Omeka site permissions for all users in a team when a site is added.
     *
     * @param int  $teamId
     * @param int  $siteId
     * @param bool $flush  Whether to flush the entity manager after syncing.
     */
    public function syncSitePermissionsForTeamOnSiteAdded(int $teamId, int $siteId, bool $flush = true): void
    {
        $teamUserReps = $this->api->search('team-user', [
            'team' => $teamId,
            'bypass_team_filter' => true,
        ])->getContent();

        foreach ($teamUserReps as $teamUserRep) {
            $this->syncSitePermissionsForUser($teamUserRep->user()->getId(), $teamId, false);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Remove Omeka site permissions for all users in a team when a site is removed.
     *
     * @param int  $teamId
     * @param int  $siteId
     * @param bool $flush  Whether to flush the entity manager after syncing.
     */
    public function removeSitePermissionsForTeamOnSiteRemoved(int $teamId, int $siteId, bool $flush = true): void
    {
        $teamUserReps = $this->api->search('team-user', [
            'team' => $teamId,
            'bypass_team_filter' => true,
        ])->getContent();

        foreach ($teamUserReps as $teamUserRep) {
            $this->removeSitePermissionsForUser($teamUserRep->user()->getId(), $teamId, $siteId, false);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * Update the `default_item_sites` user setting so the user's item-creation form
     * defaults to the sites of their current active team.
     *
     * @param int $userId
     */
    public function updateUserDefaultSites(int $userId): void
    {
        $teamUserReps = $this->api->search('team-user', [
            'user' => $userId,
            'current' => true,
            'bypass_team_filter' => true,
        ])->getContent();

        if (empty($teamUserReps)) {
            return;
        }

        $activeTeamId = $teamUserReps[0]->team()->getId();
        $teamSiteReps = $this->api->search('team-site', [
            'team' => $activeTeamId,
            'bypass_team_filter' => true,
        ])->getContent();

        $siteIds = array_map(fn($rep) => $rep->resource(), $teamSiteReps);

        $this->userSettings->set('default_item_sites', $siteIds, $userId);
    }

    /**
     * Update `default_item_sites` for all users who have an active team.
     */
    public function updateAllUserDefaultSites(): void
    {
        $teamUserReps = $this->api->search('team-user', [
            'current' => true,
            'bypass_team_filter' => true,
        ])->getContent();

        foreach ($teamUserReps as $teamUserRep) {
            $this->updateUserDefaultSites($teamUserRep->user()->getId());
        }
    }

    /**
     * Sync Omeka site permissions for every team-user-site combination.
     *
     * Used by the module upgrade routine to bring existing installations into sync.
     */
    public function syncAllSitePermissions(): void
    {
        $teamUserReps = $this->api->search('team-user', [
            'bypass_team_filter' => true,
        ])->getContent();

        foreach ($teamUserReps as $teamUserRep) {
            $this->syncSitePermissionsForUser(
                $teamUserRep->user()->getId(),
                $teamUserRep->team()->getId(),
                false
            );
        }
        $this->entityManager->flush();
    }

    /**
     * Determine the Omeka site role a user should hold on a site based on all
     * of their current team memberships.
     *
     * Returns null if no team the user belongs to includes this site (the
     * permission should be removed entirely). Returns ROLE_ADMIN if any qualifying
     * team has can_add_site_pages = true, otherwise ROLE_VIEWER.
     *
     * @param int $userId
     * @param int $siteId
     * @return string|null
     */
    private function getHighestRoleFromTeams(int $userId, int $siteId): ?string
    {
        $teamUserReps = $this->api->search('team-user', [
            'user' => $userId,
            'bypass_team_filter' => true,
        ])->getContent();

        $hasAnySite = false;
        $canManage = false;

        foreach ($teamUserReps as $teamUserRep) {
            $teamSiteReps = $this->api->search('team-site', [
                'team' => $teamUserRep->team()->getId(),
                'site' => $siteId,
                'bypass_team_filter' => true,
            ])->getContent();

            if (empty($teamSiteReps)) {
                continue;
            }

            $hasAnySite = true;
            if ($teamUserRep->role()->getCanAddSitePages()) {
                $canManage = true;
                break; // Admin wins; no need to check further.
            }
        }

        if (!$hasAnySite) {
            return null;
        }

        return $canManage ? SitePermission::ROLE_ADMIN : SitePermission::ROLE_VIEWER;
    }
}
