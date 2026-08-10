<?php
namespace Teams\Service;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Omeka\Entity\SitePermission;
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
     * Role priority for resolving conflicts when a user belongs to multiple teams
     * that share a site. Higher number = higher privilege.
     */
    private const ROLE_PRIORITY = [
        SitePermission::ROLE_ADMIN => 2,
        SitePermission::ROLE_EDITOR => 1,
        SitePermission::ROLE_VIEWER => 0,
    ];

    /**
     * @var EntityManager
     */
    private EntityManager $entityManager;

    /**
     * @var UserSettings
     */
    private UserSettings $userSettings;

    public function __construct(EntityManager $entityManager, UserSettings $userSettings)
    {
        $this->entityManager = $entityManager;
        $this->userSettings = $userSettings;
    }

    /**
     * Sync Omeka site permissions for a user in a specific team.
     *
     * Assigns ROLE_ADMIN if the team role has `can_add_site_pages`, otherwise ROLE_VIEWER,
     * for every site associated with the team.
     *
     * When the user already has a permission on a site (e.g. from another team), the
     * higher of the two roles is kept, so no privilege is silently downgraded.
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

        $user = $teamUser->getUser();
        $omekaRole = $teamUser->getRole()->getCanAddSitePages()
            ? SitePermission::ROLE_ADMIN
            : SitePermission::ROLE_VIEWER;

        $teamSites = $em->getRepository('Teams\Entity\TeamSite')->findBy(['team' => $teamId]);

        foreach ($teamSites as $teamSite) {
            $site = $teamSite->getSite();
            $sitePermissions = $site->getSitePermissions();

            $criteria = Criteria::create()->where(Criteria::expr()->eq('user', $user));
            $existingPermission = $sitePermissions->matching($criteria)->first();

            if ($existingPermission) {
                // Keep the higher of the existing and new role (multi-team safety).
                $existingPermission->setRole(
                    $this->resolveHighestRole($existingPermission->getRole(), $omekaRole)
                );
            } else {
                $sitePermission = new SitePermission();
                $sitePermission->setSite($site);
                $sitePermission->setUser($user);
                $sitePermission->setRole($omekaRole);
                $em->persist($sitePermission);
                $sitePermissions->add($sitePermission);
            }
        }

        $em->flush();
    }

    /**
     * Remove or recalculate Omeka site permissions for a user leaving a team.
     *
     * If the user still belongs to other teams that share a given site, their
     * permission is recalculated from those remaining memberships rather than
     * simply removed, so a shared-site privilege is never accidentally revoked.
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

            // Check whether any other team still grants this user access to this site.
            $remainingRole = $this->getHighestRoleFromOtherTeams($userId, $teamId, $site->getId());

            if ($remainingRole !== null) {
                // User retains access via another team — update the role accordingly.
                $existingPermission->setRole($remainingRole);
            } else {
                // No other team grants access; remove the permission entirely.
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
     * Determine the highest Omeka site role granted to a user by any team *other than*
     * the one being removed. Returns null if no other team grants access to that site.
     *
     * @param int $userId
     * @param int $excludeTeamId The team being removed, which should be ignored.
     * @param int $siteId
     * @return string|null
     */
    private function getHighestRoleFromOtherTeams(int $userId, int $excludeTeamId, int $siteId): ?string
    {
        $em = $this->entityManager;
        $teamUsers = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $userId]);

        $highestRole = null;
        foreach ($teamUsers as $teamUser) {
            if ($teamUser->getTeam()->getId() === $excludeTeamId) {
                continue;
            }

            $teamSite = $em->getRepository('Teams\Entity\TeamSite')
                ->findOneBy(['team' => $teamUser->getTeam()->getId(), 'site' => $siteId]);

            if (!$teamSite) {
                continue;
            }

            $role = $teamUser->getRole()->getCanAddSitePages()
                ? SitePermission::ROLE_ADMIN
                : SitePermission::ROLE_VIEWER;

            $highestRole = $this->resolveHighestRole($highestRole, $role);
        }

        return $highestRole;
    }

    /**
     * Return whichever of two Omeka site permission roles conveys higher privilege.
     *
     * @param string|null $current
     * @param string      $new
     * @return string
     */
    private function resolveHighestRole(?string $current, string $new): string
    {
        if ($current === null) {
            return $new;
        }

        $currentPriority = self::ROLE_PRIORITY[$current] ?? -1;
        $newPriority = self::ROLE_PRIORITY[$new] ?? -1;

        return $newPriority > $currentPriority ? $new : $current;
    }
}
