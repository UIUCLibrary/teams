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

class InTeamAssertion implements AssertionInterface
{
    /**
     * @var AuthenticationService
     */
    protected $auth;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var \Omeka\Mvc\Status
     */
    protected $status;

    /**
     * @param AuthenticationService $auth
     * @param EntityManager $entityManager
     * @param \Omeka\Mvc\Status $status
     */
    public function __construct(
        AuthenticationService $auth,
        EntityManager $entityManager,
        $status
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
    ) {
        $user = $this->auth->getIdentity();

        /*
         * First go through a couple of common cases where we don't need to judge permissions
         */

        // If the user isn't logged in (e.g., the public), use the default settings
        if (!$user) {
            return true;
        }

        // If it isn't on the backend, let the public vs private rules take over
        if ($this->status->isSiteRequest()) {
            return true;
        }

        $is_glob_admin = ($user->getRole() === 'global_admin');

        // If it is the global admin, bypass any team controls
        if ($is_glob_admin) {
            return true;
        }

        $authorized = false;

        // Determine if resource is an entity instance or a class name
        $isEntityInstance = $resource instanceof EntityInterface;
        
        // Get the resource class name
        if ($isEntityInstance) {
            $res_class = $this->getResourceClass($resource);
        } elseif (is_string($resource)) {
            // Resource is a class name string (e.g., during create operations)
            $res_class = $resource;
        } else {
            // Unknown resource type, deny access
            return false;
        }

        $user_id = $user->getId();
        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current' => true, 'user' => $user_id]);

        // If user doesn't have a current team, deny access
        if (!$team_user) {
            return false;
        }

        $team = $team_user->getTeam();
        $team_user_role = $team_user->getRole();

        // Only check team membership if resource is an actual entity instance
        // For create operations, resource is just a class name, so skip team membership check
        if ($isEntityInstance && $privilege != 'create') {
            // If resource not part of user's current team, no action at all
            if (!$this->inTeam($resource, $team_user)) {
                return false;
            }
        }

        $resource_domains = [
            'Omeka\Entity\Item',
            'Omeka\Entity\ItemSet',
            'Omeka\Entity\Media',
            'Omeka\Entity\Asset',
            'Omeka\Entity\ResourceTemplate',
            'Teams\Entity\TeamResource',
            'Teams\Entity\TeamAsset',
        ];

        if (in_array($res_class, $resource_domains)) {
            if ($privilege == 'create') {
                $authorized = $team_user_role->getCanAddItems();
            } elseif ($privilege == 'delete' || $privilege == 'batch_delete') {
                $authorized = $team_user_role->getCanDeleteResources();
            } elseif ($privilege == 'update') {
                $authorized = $team_user_role->getCanModifyResources();
            } elseif ($privilege == 'read' || $privilege == 'search') {
                $authorized = true;
            }
        } elseif ($res_class == 'Omeka\Entity\Site') {
            if ($privilege == 'create') {
                // Site creation permissions are handled separately in addAclRules
                return true;
            } elseif ($privilege == 'delete' || $privilege == 'batch_delete') {
                $authorized = $team_user_role->getCanAddSitePages();
            } elseif ($privilege == 'update') {
                $authorized = $team_user_role->getCanAddSitePages();
            } elseif ($privilege == 'read') {
                $authorized = true;
            }
        } elseif ($res_class == 'Omeka\Entity\SitePage') {
            if ($privilege == 'create') {
                $authorized = $team_user_role->getCanAddSitePages();
            } elseif ($privilege == 'delete' || $privilege == 'batch_delete') {
                $authorized = $team_user_role->getCanAddSitePages();
            } elseif ($privilege == 'update') {
                $authorized = $team_user_role->getCanAddSitePages();
            } elseif ($privilege == 'read') {
                $authorized = true;
            }
        } elseif ($res_class == 'Teams\Entity\Team' || $res_class == 'Teams\Entity\TeamRole') {
            if ($privilege == 'create') {
                $authorized = $is_glob_admin;
            } elseif ($privilege == 'delete' || $privilege == 'batch_delete') {
                $authorized = $is_glob_admin;
            } elseif ($privilege == 'update') {
                $authorized = $team_user_role->getCanAddUsers();
            } elseif ($privilege == 'read') {
                $authorized = true;
            }
        } elseif ($res_class == 'Teams\Entity\TeamSite') {
            if ($privilege == 'read') {
                $authorized = true;
            } else {
                // Adding or removing sites from a team
                $authorized = $team_user_role->getCanAddSitePages();
            }
        } elseif ($res_class == 'Teams\Entity\TeamUser') {
            if ($privilege == 'read') {
                $authorized = true;
            } elseif ($privilege == 'create') {
                $authorized = $team_user_role->getCanAddUsers();
            } elseif ($privilege == 'delete') {
                $authorized = $team_user_role->getCanAddUsers();
            } else {
                $authorized = $is_glob_admin;
            }
        } elseif ($res_class == 'Omeka\Entity\User') {
            return true;
        } elseif ($res_class == 'Omeka\Entity\Job') {
            return true;
        } elseif ($res_class == 'Omeka\Entity\Property') {
            return true;
        } elseif (strpos($res_class, 'Omeka\Entity') !== 0) {
            // Don't police other modules by default (not an Omeka entity)
            return true;
        }

        return $authorized;
    }

    /**
     * Check to see if the user and the object they are attempting to access or change are part of the same team.
     *
     * @param EntityInterface $resource
     * @param \Teams\Entity\TeamUser $team_user
     * @return bool
     */
    private function inTeam($resource, $team_user)
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
             * Other classes should be controlled by their respective modules and components.
             */
            return true;
        }

        if ($team_resource = $this->entityManager->getRepository($teamsRepo)
            ->findOneBy($criteria)) {
            $in_team = true;
        } else {
            $in_team = false;
        }

        return $in_team;
    }

    /**
     * Returns the expected string for proxied resource class in cases where the class returned is the doctrine proxy.
     *
     * @param mixed $resource
     * @return string
     */
    private function getResourceClass($resource)
    {
        $doctrine_ent = 'DoctrineProxies\__CG__';
        $doctrine_test = strpos(get_class($resource), $doctrine_ent);
        if ($doctrine_test === false) {
            $res_class = get_class($resource);
        } else {
            // Skip the doctrine proxy prefix and the backslash separator
            $res_class = substr(get_class($resource), strlen($doctrine_ent) + 1);
        }
        return $res_class;
    }
}
