<?php
namespace Teams\View\Helper;

use Doctrine\ORM\EntityManager;
use Laminas\View\Helper\AbstractHelper;

/**
 * Returns a map of userId => string[] (team names) for users whose site role
 * on the given site is managed through a Teams assignment.
 *
 * Extracted from the inline service-locator call in
 * view/omeka/site-admin/index/users.phtml so that the entity manager is
 * properly injected (avoiding the deprecated getServiceLocator() call).
 */
class TeamSiteUsers extends AbstractHelper
{
    /**
     * @var EntityManager
     */
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param  int $siteId
     * @return array<int, string[]>  userId => list of team names granting the highest role, or empty array on error
     */
    public function __invoke(int $siteId): array
    {
        try {
            $teamSites = $this->entityManager
                ->getRepository('Teams\Entity\TeamSite')
                ->findBy(['site' => $siteId]);

            // Collect per-user: highest role flag and which team(s) grant it.
            // Role priority: admin (can_add_site_pages=true) > viewer.
            $userHighestCanManage = []; // userId => bool
            $userTeamsByRole = []; // userId => ['admin' => [], 'viewer' => []]

            foreach ($teamSites as $teamSite) {
                $team = $teamSite->getTeam();
                $teamUsers = $this->entityManager
                    ->getRepository('Teams\Entity\TeamUser')
                    ->findBy(['team' => $team->getId()]);

                foreach ($teamUsers as $teamUser) {
                    $userId = $teamUser->getUser()->getId();
                    $canManage = (bool) $teamUser->getRole()->getCanAddSitePages();

                    if (!isset($userHighestCanManage[$userId])) {
                        $userHighestCanManage[$userId] = false;
                        $userTeamsByRole[$userId] = ['admin' => [], 'viewer' => []];
                    }

                    if ($canManage) {
                        $userHighestCanManage[$userId] = true;
                        $userTeamsByRole[$userId]['admin'][] = $team->getName();
                    } else {
                        $userTeamsByRole[$userId]['viewer'][] = $team->getName();
                    }
                }
            }

            // Return only the team(s) that grant the user's highest role.
            $teamManagedUsers = [];
            foreach ($userHighestCanManage as $userId => $canManage) {
                $teamManagedUsers[$userId] = $canManage
                    ? $userTeamsByRole[$userId]['admin']
                    : $userTeamsByRole[$userId]['viewer'];
            }

            return $teamManagedUsers;
        } catch (\Exception $e) {
            return [];
        }
    }
}
