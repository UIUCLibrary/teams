<?php
namespace Teams\Acl;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Role\RoleInterface;
use Laminas\Authentication\AuthenticationService;
use Doctrine\ORM\EntityManager;
use Omeka\Api\Exception;
use Omeka\Entity\EntityInterface;
use Omeka\Mvc\Status;
use Teams\Entity\TeamUser;

/**
 * ACL assertion for team-based authorization.
 * 
 * This assertion enforces that users can only access resources that belong to their current team,
 * and that their role within that team permits the requested action. It handles various resource
 * types including Items, Sites, Media, and Team-specific entities.
 * 
 * The assertion is used with AssertionNegation in ACL deny rules, creating a pattern where
 * access is denied unless the assertion grants permission (double-negative pattern).
 */
class TeamRolePermissionAssertion implements AssertionInterface
{
    /**
     * @var AuthenticationService
     */
    protected AuthenticationService $auth;

    /**
     * @var EntityManager
     */
    protected EntityManager $entityManager;

    /**
     * @var Status
     */
    protected Status $status;

    /**
     * @param AuthenticationService $auth
     * @param EntityManager $entityManager
     * @param Status $status
     */
    public function __construct(
        AuthenticationService $auth,
        EntityManager $entityManager,
        Status $status
    ) {
        $this->auth = $auth;
        $this->entityManager = $entityManager;
        $this->status = $status;
    }

    /**
     * Assert whether the current user has permission based on team membership and role.
     *
     * @param Acl $acl
     * @param RoleInterface|null $role
     * @param ResourceInterface|null $resource
     * @param string|null $privilege
     * @return bool
     */
    public function assert(
        Acl $acl,
        RoleInterface $role = null,
        ResourceInterface $resource = null,
        $privilege = null
    ): bool
    {
        $resourceDomains = [
            \Omeka\Entity\Item::class, \Omeka\Entity\ItemSet::class, \Omeka\Entity\Media::class,
            \Omeka\Entity\Asset::class, \Omeka\Entity\ResourceTemplate::class,
            \Teams\Entity\TeamResource::class, \Teams\Entity\TeamAsset::class, \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class, \Omeka\Api\Adapter\ResourceTemplateAdapter::class
        ];

        $user = $this->auth->getIdentity();
        if (!$user) {
            return false;
        }

        $teamUser = $this->entityManager->getRepository(TeamUser::class)
            ->findOneBy(['is_current' => true, 'user' => $user->getId()]);

        if (!$teamUser) {
            // User has no active team, so they cannot perform any team-restricted actions.
            return false;
        }

        $teamUserRole = $teamUser->getRole();
        $resourceClass = $this->getResourceClass($resource);
        
        // For 'create' actions, we only check if the user's role has permission,
        // as the resource doesn't belong to a team yet.
        if ($privilege == 'create') {
            if ($resourceClass == \Omeka\Entity\Site::class || $resourceClass == \Omeka\Entity\SitePage::class) {
                return (bool)$teamUserRole->getCanAddSitePages();
            } elseif (in_array($resourceClass, $resourceDomains)) {
                return (bool)$teamUserRole->getCanAddItems();
            } else {
                // Other resources are not part of this scope
                return false;
            }
        }

        if (in_array($privilege, ['batch_delete', 'batch_delete_all'])) {
            return (bool)$teamUserRole->getCanDeleteResources();
        }
        if (in_array($privilege, ['batch_update', 'batch_update_all'])) {
            return (bool)$teamUserRole->getCanModifyResources();
        }

        // For all other actions, first check if the resource is in the user's current team.
        if (!$this->isResourceInTeam($resource, $teamUser)) {
            return false;
        }

        // The resource is in the team. Now check if the team role grants the specific privilege.
        $isAuthorized = false;

        if (in_array($resourceClass, $resourceDomains)) {
            switch ($privilege) {
                case 'delete':
                case 'batch_delete':
                    $isAuthorized = (bool)$teamUserRole->getCanDeleteResources();
                    break;
                case 'update':
                    $isAuthorized = (bool)$teamUserRole->getCanModifyResources();
                    break;
                case 'read':
                case 'search':
                    $isAuthorized = true; // If it's in the team, they can read it.
                    break;
            }
        } elseif ($resourceClass == \Omeka\Entity\Site::class || $resourceClass == \Omeka\Entity\SitePage::class) {
            switch ($privilege) {
                case 'delete':
                case 'batch_delete':
                case 'update':
                    $isAuthorized = (bool)$teamUserRole->getCanAddSitePages();
                    break;
                case 'read':
                    $isAuthorized = true;
                    break;
            }
        } elseif ($resourceClass == \Teams\Entity\Team::class || $resourceClass == \Teams\Entity\TeamRole::class) {
            switch ($privilege) {
                case 'create':
                case 'delete':
                case 'batch_delete':
                    $isAuthorized = false; // Only global admins can do this.
                    break;
                case 'update':
                    $isAuthorized = (bool)$teamUserRole->getCanAddUsers();
                    break;
                case 'read':
                    $isAuthorized = true;
                    break;
            }
        } elseif ($resourceClass == \Teams\Entity\TeamSite::class) {
            $isAuthorized = ($privilege == 'read') ? true : (bool)$teamUserRole->getCanAddSitePages();
        } elseif ($resourceClass == TeamUser::class) {
            switch ($privilege) {
                case 'create':
                case 'delete':
                    $isAuthorized = (bool)$teamUserRole->getCanAddUsers();
                    break;
                case 'read':
                    $isAuthorized = true;
                    break;
                default:
                    $isAuthorized = false;
            }
        } else {

            // If it's a resource we don't explicitly police (e.g., Job, Property),
            // this assertion does not authorize it.
            $isAuthorized = false;
        }

        return $isAuthorized;
    }

    /**
     * Check to see if the user and the object they are attempting to access or change are part of the same team.
     *
     * @param  ResourceInterface $resource
     * @param TeamUser $team_user
     * @return bool
     */
    private function isResourceInTeam(ResourceInterface $resource, TeamUser $team_user): bool
    {
        $resource_domains = ['Omeka\Entity\Item', 'Omeka\Entity\ItemSet', 'Omeka\Entity\Media'];
        $fk_id = $resource->getId();
        $team = $team_user->getTeam();
        $user = $team_user->getUser();
        $res_class = $this->getResourceClass($resource);

        if (in_array($res_class, $resource_domains)) {
            $teamsRepo = 'Teams\Entity\TeamResource';
            $fk = 'resource';
            $criteria = ['team' => $team->getId(), $fk => $fk_id];
        } elseif ($res_class == 'Omeka\Entity\Site') {
            $teamsRepo = 'Teams\Entity\TeamSite';
            $fk = 'site';
            $criteria = ['team' => $team->getId(), $fk => $fk_id];
        } elseif ($res_class == 'Omeka\Entity\SitePage') {
            $teamsRepo = 'Teams\Entity\TeamSite';
            $fk = 'site';
            $fk_id = $resource->getSite()->getId();
            $criteria = ['team' => $team->getId(), $fk => $fk_id];
        } elseif ($res_class == 'Omeka\Entity\ResourceTemplate') {
            $teamsRepo = 'Teams\Entity\TeamResourceTemplate';
            $fk = 'resource_template';
            $criteria = ['team' => $team->getId(), $fk => $fk_id];
        } elseif ($res_class == 'Teams\Entity\Team') {
            $teamsRepo = 'Teams\Entity\TeamUser';
            $fk = 'user';
            $criteria = ['team' => $team->getId(), $fk => $user->getId()];
        } elseif ($res_class == 'Teams\Entity\TeamSite') {
            $teamsRepo = 'Teams\Entity\TeamUser';
            $fk = 'user';
            $criteria = ['team' => $resource->getTeam()->getId(), $fk => $user->getId()];
        } elseif ($res_class == 'Teams\Entity\TeamResource') {
            $teamsRepo = 'Teams\Entity\TeamResource';
            $fk = 'resource';
            $fk_id = $resource->getResource()->getId();
            $criteria = ['team' => $team->getId(), $fk => $fk_id];
        } else {
            /*
             * TeamRole is only accessible by global admin so doesn't need to be checked.
             * Other classes should be controlled by their respective modules and components
             * and should not be using this assertion.
             */
            return false;
        }
        $team_resource = $this->entityManager->getRepository($teamsRepo)
            ->findOneBy($criteria);
        
        return (bool)$team_resource;
    }

    /**
     * Returns the class name of a resource, handling both entity instances and ACL resources.
     * 
     * When the resource implements ResourceInterface (e.g., GenericResource), uses getResourceId()
     * to get the resource identifier (typically a class name).
     * For entity instances, returns the fully qualified class name.
     *
     * @param mixed $resource
     * @return string The class name or resource identifier
     */
    private function getResourceClass($resource): string
    {
        // If it's an ACL resource (like GenericResource), use its resource ID
        if ($resource instanceof ResourceInterface) {
            return $resource->getResourceId();
        }
        
        // Otherwise, get the class name directly
        return get_class($resource);
    }
}
