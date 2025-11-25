<?php
namespace Teams\Acl;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Assertion\AssertionInterface;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Laminas\Permissions\Acl\Resource\GenericResource;
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
     * Item-like entities that map to TeamResource repository (Item, ItemSet, Media only)
     */
    private const ITEM_ENTITIES = [
        \Omeka\Entity\Item::class,
        \Omeka\Entity\ItemSet::class,
        \Omeka\Entity\Media::class,
    ];
    
    /**
     * Entity classes controlled by item/resource permissions.
     * Used by Module.php for configuring entity-level ACL rules.
     */
    public const ITEM_ENTITIES_FOR_ACL = [
        \Omeka\Entity\Item::class,
        \Omeka\Entity\ItemSet::class,
        \Omeka\Entity\Media::class,
        \Omeka\Entity\Asset::class,
        \Omeka\Entity\ResourceTemplate::class,
        \Teams\Entity\TeamResource::class,
        \Teams\Entity\TeamAsset::class,
    ];
    
    /**
     * API adapter classes controlled by item/resource permissions.
     * Used by Module.php for configuring adapter-level ACL rules.
     */
    public const ITEM_ADAPTERS_FOR_ACL = [
        \Omeka\Api\Adapter\ItemAdapter::class,
        \Omeka\Api\Adapter\ItemSetAdapter::class,
        \Omeka\Api\Adapter\MediaAdapter::class,
        \Omeka\Api\Adapter\AssetAdapter::class,
        \Omeka\Api\Adapter\ResourceTemplateAdapter::class,
    ];
    
    /**
     * Site entity classes controlled by site permissions.
     * Used by Module.php for configuring entity-level ACL rules.
     */
    public const SITE_ENTITIES_FOR_ACL = [
        \Omeka\Entity\Site::class,
        \Omeka\Entity\SitePage::class,
    ];
    
    /**
     * Site API adapter classes controlled by site permissions.
     * Used by Module.php for configuring adapter-level ACL rules.
     */
    public const SITE_ADAPTERS_FOR_ACL = [
        \Omeka\Api\Adapter\SiteAdapter::class,
        \Omeka\Api\Adapter\SitePageAdapter::class,
    ];

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
        
        // Get combined resource domains dynamically
        $itemResourceDomains = array_merge(self::ITEM_ENTITIES_FOR_ACL, self::ITEM_ADAPTERS_FOR_ACL);
        $siteResourceDomains = array_merge(self::SITE_ENTITIES_FOR_ACL, self::SITE_ADAPTERS_FOR_ACL);
        
        // For 'create' actions, we only check if the user's role has permission,
        // as the resource doesn't belong to a team yet.
        if ($privilege == 'create') {
            if (in_array($resourceClass, $siteResourceDomains)) {
                return (bool)$teamUserRole->getCanAddSitePages();
            } elseif (in_array($resourceClass, $itemResourceDomains)) {
                return (bool)$teamUserRole->getCanAddItems();
            } else {
                // Other resources are not part of this scope
                return false;
            }
        }

        //In practice, batch operations are handled as a series of individual operations,
        //but the batch privileges are checked first and used to control certain form controls
        if (in_array($privilege, ['batch_delete', 'batch_delete_all'])) {
            return (bool)$teamUserRole->getCanDeleteResources();
        }
        if (in_array($privilege, ['batch_update', 'batch_update_all'])) {
            return (bool)$teamUserRole->getCanModifyResources();
        }

        // For all other actions, check if the resource is in the user's current team
        if (!$this->isResourceInTeam($resource, $teamUser)) {
            return false;
        }

        // The resource is in the team. Now check if the team role grants the specific privilege.
        $isAuthorized = false;

        if (in_array($resourceClass, $itemResourceDomains)) {
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
        } elseif (in_array($resourceClass, $siteResourceDomains)) {
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
     * Check if a resource belongs to the user's current team.
     * 
     * Returns false if:
     * - Resource is a GenericResource (only has class identifier, no entity data)
     * - Resource is not an EntityInterface (cannot access entity methods)
     * - Resource is not found in the team's resource associations
     * 
     * Requires an entity instance to verify team membership because it needs to access
     * entity-specific methods like getId(), getSite(), getTeam(), and getResource().
     *
     * @param mixed $resource The resource to check (accepts ResourceInterface or entity instances)
     * @param TeamUser $team_user The user's team membership record
     * @return bool True if the resource belongs to the user's team, false otherwise
     */
    private function isResourceInTeam(mixed $resource, TeamUser $team_user): bool
    {
        // Can't verify team membership without an actual entity instance
        if ($resource instanceof GenericResource) {
            return false;
        }
        
        // Ensure we have an entity instance with required methods
        if (!$resource instanceof EntityInterface) {
            return false;
        }
        
        $fk_id = $resource->getId();
        $team = $team_user->getTeam();
        $user = $team_user->getUser();
        $res_class = $this->getResourceClass($resource);

        if (in_array($res_class, self::ITEM_ENTITIES)) {
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
