<?php
namespace Teams\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;

/**
 * Returns a map of userId => string[] (team names) for users whose site role
 * on the given site is managed through a Teams assignment.
 *
 * Extracted from the inline service-locator call in
 * view/omeka/site-admin/index/users.phtml so that the API manager is
 * properly injected (avoiding the deprecated getServiceLocator() call).
 * All reads go through the Omeka API, with bypass_team_filter set so that
 * this admin-only report is never restricted by the current user's active
 * team, matching the pattern used by Teams\Service\SitePermissionManager.
 */
class TeamSiteUsers extends AbstractHelper
{
    /**
     * @var ApiManager
     */
    private ApiManager $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * @param  int $siteId
     * @return array<int, string[]>  userId => list of team names granting the highest role, or empty array on error
     */
    public function __invoke(int $siteId): array
    {
        try {
            $teamSiteReps = $this->api->search('team-site', [
                'site' => $siteId,
                'bypass_team_filter' => true,
            ])->getContent();

            $teamNamesById = [];

            // Collect per-user: highest role flag and which team(s) grant it.
            // Role priority: admin (can_add_site_pages=true) > viewer.
            $userHighestCanManage = []; // userId => bool
            $userTeamsByRole = []; // userId => ['admin' => [], 'viewer' => []]

            foreach ($teamSiteReps as $teamSiteRep) {
                $teamId = $teamSiteRep->team();
                if (!isset($teamNamesById[$teamId])) {
                    $teamNamesById[$teamId] = $this->api->read('team', $teamId)->getContent()->name();
                }
                $teamName = $teamNamesById[$teamId];

                $teamUserReps = $this->api->search('team-user', [
                    'team' => $teamId,
                    'bypass_team_filter' => true,
                ])->getContent();

                foreach ($teamUserReps as $teamUserRep) {
                    $userId = $teamUserRep->user()->getId();
                    $canManage = (bool) $teamUserRep->role()->getCanAddSitePages();

                    if (!isset($userHighestCanManage[$userId])) {
                        $userHighestCanManage[$userId] = false;
                        $userTeamsByRole[$userId] = ['admin' => [], 'viewer' => []];
                    }

                    if ($canManage) {
                        $userHighestCanManage[$userId] = true;
                        $userTeamsByRole[$userId]['admin'][] = $teamName;
                    } else {
                        $userTeamsByRole[$userId]['viewer'][] = $teamName;
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
