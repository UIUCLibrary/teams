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
     * @return array<int, string[]>  userId => list of team names, or empty array on error
     */
    public function __invoke(int $siteId): array
    {
        try {
            $teamManagedUsers = [];
            $teamSites = $this->entityManager
                ->getRepository('Teams\Entity\TeamSite')
                ->findBy(['site' => $siteId]);

            foreach ($teamSites as $teamSite) {
                $team = $teamSite->getTeam();
                $teamUsers = $this->entityManager
                    ->getRepository('Teams\Entity\TeamUser')
                    ->findBy(['team' => $team->getId()]);

                foreach ($teamUsers as $teamUser) {
                    $userId = $teamUser->getUser()->getId();
                    $teamManagedUsers[$userId][] = $team->getName();
                }
            }

            return $teamManagedUsers;
        } catch (\Exception $e) {
            return [];
        }
    }
}
