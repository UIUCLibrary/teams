<?php
namespace Teams\Service;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
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
 * Following the pattern established by AclRuleManager, business logic is extracted
 * from the monolithic Module.php into this injectable, testable service class.
 * Module.php and UpdateController remain thin coordinators that delegate here.
 */
class SitePermissionManager
{
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
    protected $logger;

    /**
     * @param EntityManager $entityManager
     * @param UserSettings $userSettings
     * @param Logger $logger
     */

    public function __construct(EntityManager $entityManager, UserSettings $userSettings, Logger $logger)
    {
        $this->entityManager = $entityManager;
        $this->userSettings = $userSettings;
        $this->logger = $logger;
    }

    /**
     * Sync Omeka site permissions for a user in a specific team.
     *
     * Assigns ROLE_ADMIN if the team role has `can_add_site_pages`, otherwise ROLE_VIEWER,
     * for every site associated with the team. The role is set unconditionally so that
     * any change to a team role is immediately reflected in the site permission.
     *
     * @param int $userId
     * @param int $teamId
     */
    public function syncSitePermissionsForUser(int $userId, int $teamId): void
    {
        $em = $this->entityManager;

        $teamUser = $em->find('Teams\Entity\TeamUser', ['team' => $teamId, 'user' => $userId]);
        if (!$teamUser) {
            return;
        }

        // Refresh to ensure the role reflects the latest persisted state,
        // not a potentially stale identity-map proxy.
        $em->refresh($teamUser);

        $user = $teamUser->getUser();
        $teamRole = $teamUser->getRole();
        $canAddSitePages = $teamRole->getCanAddSitePages();
        $omekaSiteRole = $canAddSitePages
            ? SitePermission::ROLE_ADMIN
            : SitePermission::ROLE_VIEWER;
        $this->logger->info(sprintf(
            '[SitePermissionManager] syncSitePermissionsForUser: userId=%d, teamId=%d, teamRoleId=%d, teamRoleName="%s", canAddSitePages=%s => omekaSiteRole="%s", rawCanAddSitePages=%s',
            $userId,
            $teamId,
            $teamRole->getId(),
            $teamRole->getName(),
            $canAddSitePages ? 'true' : 'false',
            $omekaSiteRole,
            $canAddSitePages
        ));

        $teamSites = $em->getRepository('Teams\Entity\TeamSite')->findBy(['team' => $teamId]);

        foreach ($teamSites as $teamSite) {
            $site = $teamSite->getSite();
            $sitePermissions = $site->getSitePermissions();

            $criteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));
            $existingPermission = $sitePermissions->matching($criteria)->first();

            if ($existingPermission) {
                $this->logger->info(sprintf(
                    '[SitePermissionManager] siteId=%d: updating existing SitePermission from "%s" to "%s" for userId=%d',
                    $site->getId(),
                    $existingPermission->getRole(),
                    $omekaSiteRole,
                    $userId
                ));
                $existingPermission->setRole($omekaSiteRole);
            } else {
                $this->logger->info(sprintf(
                    '[SitePermissionManager] siteId=%d: creating new SitePermission with role "%s" for userId=%d',
                    $site->getId(),
                    $omekaSiteRole,
                    $userId
                ));
                $sitePermission = new SitePermission();
                $sitePermission->setSite($site);
                $sitePermission->setUser($user);
                $sitePermission->setRole($omekaSiteRole);
                $em->persist($sitePermission);
                $sitePermissions->add($sitePermission);
            }
        }

        $em->flush();
    }

    /**
     * Sync or remove Omeka site permissions for a user leaving a team.
     *
     * If the user still belongs to other teams that share a given site, their
     * permission is recalculated across all team memberships so that the role
     * reflects the current state — which may be unchanged, lowered, or elevated.
     * If no team grants access to a site, the permission is removed entirely.
     *
     * @param int      $userId
     * @param int      $teamId       The team the user is being removed from.
     * @param int|null $siteId       If set, only process this one site (used when a
     *                               single site is removed from a team).
     */
    public function removeSitePermissionsForUser(int $userId, int $teamId, ?int $siteId = null): void
    {
        $em = $this->entityManager;

        $user = $em->find('Omeka\Entity\User', $userId);
        if (!$user) {
            return;
        }

        $criteria = ['team' => $teamId];
        if ($siteId !== null) {
            $criteria['site'] = $siteId;
        }
        $teamSites = $em->getRepository('Teams\Entity\TeamSite')->findBy($criteria);

        foreach ($teamSites as $teamSite) {
            $site = $teamSite->getSite();
            $sitePermissions = $site->getSitePermissions();

            $userCriteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));
            $existingPermission = $sitePermissions->matching($userCriteria)->first();

            if (!$existingPermission) {
                continue;
            }

            // Recalculate the role across all team memberships after the change.
            $syncedRole = $this->getHighestRoleFromTeams($userId, $site->getId());

            if ($syncedRole !== null) {
                // User still has access — sync to the recalculated role.
                $existingPermission->setRole($syncedRole);
            } else {
                // No team grants access to this site; remove the permission.
                $sitePermissions->removeElement($existingPermission);
                $em->remove($existingPermission);
            }
        }

        $em->flush();
    }

    /**
     * Sync Omeka site permissions for all users in a team when a site is added.
     *
     * @param int $teamId
     * @param int $siteId
     */
    public function syncSitePermissionsForTeamOnSiteAdded(int $teamId, int $siteId): void
    {
        $teamUsers = $this->entityManager
            ->getRepository('Teams\Entity\TeamUser')
            ->findBy(['team' => $teamId]);

        foreach ($teamUsers as $teamUser) {
            $this->syncSitePermissionsForUser($teamUser->getUser()->getId(), $teamId);
        }
    }

    /**
     * Remove Omeka site permissions for all users in a team when a site is removed.
     *
     * @param int $teamId
     * @param int $siteId
     */
    public function removeSitePermissionsForTeamOnSiteRemoved(int $teamId, int $siteId): void
    {
        $teamUsers = $this->entityManager
            ->getRepository('Teams\Entity\TeamUser')
            ->findBy(['team' => $teamId]);

        foreach ($teamUsers as $teamUser) {
            $this->removeSitePermissionsForUser($teamUser->getUser()->getId(), $teamId, $siteId);
        }
    }

    /**
     * Update the `default_item_sites` user setting so the user's item-creation form
     * defaults to the sites of their current active team.
     *
     * Extracted from Module::updateUserSites() to follow the service-layer pattern.
     *
     * @param int $userId
     */
    public function updateUserDefaultSites(int $userId): void
    {
        $activeTeam = $this->entityManager
            ->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['user' => $userId, 'is_current' => true]);

        if (!$activeTeam) {
            return;
        }

        $siteIds = [];
        foreach ($activeTeam->getTeam()->getTeamSites() as $teamSite) {
            $siteIds[] = $teamSite->getSite()->getId();
        }

        $this->userSettings->set('default_item_sites', $siteIds, $userId);
    }

    /**
     * Update `default_item_sites` for all users who have an active team.
     *
     * Extracted from Module::updateAllUserSites().
     */
    public function updateAllUserDefaultSites(): void
    {
        $activeTeamUsers = $this->entityManager
            ->getRepository('Teams\Entity\TeamUser')
            ->findBy(['is_current' => true]);

        foreach ($activeTeamUsers as $teamUser) {
            $this->updateUserDefaultSites($teamUser->getUser()->getId());
        }
    }

    /**
     * Sync Omeka site permissions for every team-user-site combination.
     *
     * Iterates all TeamUser records and calls syncSitePermissionsForUser for
     * each, ensuring every user has the correct SitePermission row for every
     * site their team is associated with. Used by the module upgrade routine
     * to bring existing installations into sync.
     */
    public function syncAllSitePermissions(): void
    {
        $teamUsers = $this->entityManager
            ->getRepository('Teams\Entity\TeamUser')
            ->findAll();

        foreach ($teamUsers as $teamUser) {
            $this->syncSitePermissionsForUser(
                $teamUser->getUser()->getId(),
                $teamUser->getTeam()->getId()
            );
        }
    }

    /**
     * Determine the Omeka site role a user should hold on a site based on all
     * of their current team memberships.
     *
     * Logic:
     * - If no team the user belongs to includes this site, returns null
     *   (the site permission should be removed entirely).
     * - If any team with this site has can_add_site_pages = true,
     *   returns ROLE_ADMIN.
     * - Otherwise returns ROLE_VIEWER.
     *
     * @param int $userId
     * @param int $siteId
     * @return string|null
     */
    private function getHighestRoleFromTeams(int $userId, int $siteId): ?string
    {
        $em = $this->entityManager;
        $teamUsers = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $userId]);

        $hasAnySite = false;
        $canManage = false;

        foreach ($teamUsers as $teamUser) {
            $teamSite = $em->getRepository('Teams\Entity\TeamSite')
                ->findOneBy(['team' => $teamUser->getTeam()->getId(), 'site' => $siteId]);

            if (!$teamSite) {
                continue;
            }

            $hasAnySite = true;
            if ($teamUser->getRole()->getCanAddSitePages()) {
                $canManage = true;
                break; // No need to check further — admin wins.
            }
        }

        if (!$hasAnySite) {
            return null;
        }

        return $canManage ? SitePermission::ROLE_ADMIN : SitePermission::ROLE_VIEWER;
    }
}
