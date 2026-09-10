<?php
namespace Teams\Service;

use Laminas\Permissions\Acl\Acl;
use Teams\Acl\TeamRolePermissionAssertion;
use Omeka\Permissions\Assertion\AssertionNegation;

class AclRuleManager
{
    /**
     * Entity-level privileges that should be controlled by team permissions
     */
    private const ENTITY_PRIVILEGES = [
        'update',
        'delete',
        'create',
        'batch-delete',
        'batch-update',
        'batch-create',
        'batch_delete',
        'batch-edit-all',
        'batch-update-all',
        'batch-edit',
        'batch-delete-all',
    ];

    /**
     * Adapter-level privileges that should be controlled by team permissions
     */
    private const ADAPTER_PRIVILEGES = [
        'create',
        'batch_delete',
        'batch_create',
        'batch_update',
        'batch_delete_all',
        'batch_update_all',
    ];

    /**
     * Core roles for which Omeka\Service\AclFactory registers a role-wide
     * catch-all `$acl->allow($role)` rule with no resource or privilege
     * (currently only "site_admin", via addRulesForSiteAdmin()).
     *
     * Laminas\Permissions\Acl treats a rule whose assertion returns false as
     * absent and keeps searching progressively broader resources for that
     * role/privilege, so for these roles a plain allow()-with-assertion rule
     * would eventually reach that catch-all and grant access even when the
     * assertion denies it. deny()-with-a-negated-assertion is used for them
     * instead: its "should deny" branch matches this exact resource and
     * privilege directly (so it never reaches the catch-all), while its
     * "should allow" branch falls through to that same catch-all, which
     * grants access, matching the assertion's own verdict. Roles without such
     * a catch-all rely on Laminas Acl's default deny instead, so they use a
     * plain allow()-with-assertion rule (see below), which can only ever
     * grant access or defer, never deny.
     */
    private const ROLES_WITH_DEFAULT_ALLOW = ['site_admin'];

    /**
     * Core roles for which Omeka\Service\AclFactory never grants create,
     * update, or delete on any resource type Teams controls (Item, ItemSet,
     * Media, Asset, ResourceTemplate, Site, SitePage), under any condition.
     * Currently only "researcher", which core restricts to read-only actions
     * everywhere.
     *
     * Teams is a gate in front of core, not a source of new grants: within a
     * team, it can only say whether a member may do something a role is
     * otherwise capable of, never make a role capable of something core
     * forbids it outright. For a role with no core-level create/update/delete
     * ability at all, Teams must leave core's own default deny in place
     * instead of registering a rule.
     */
    private const ROLES_WITHOUT_CORE_CREATE_UPDATE_DELETE = ['researcher'];

    /**
     * @var TeamRolePermissionAssertion
     */
    private $assertion;

    /**
     * @var array|null Cached list of resource entities for ACL rules
     */
    private $entities;

    /**
     * @var array|null Cached list of resource adapters for ACL rules
     */
    private $adapters;

    public function __construct(TeamRolePermissionAssertion $assertion)
    {
        $this->assertion = $assertion;
    }

    public function applyRules(Acl $acl)
    {
        // Use constants from assertion class to ensure synchronization between ACL rules and assertion logic
        // Lazy-load and cache the merged resources (instance-level cache, service is typically instantiated once per request)
        if ($this->entities === null) {
            $this->entities = array_merge(
                TeamRolePermissionAssertion::RESOURCE_ENTITIES_FOR_ACL,
                TeamRolePermissionAssertion::SITE_ENTITIES_FOR_ACL
            );
        }

        if ($this->adapters === null) {
            $this->adapters = array_merge(
                TeamRolePermissionAssertion::RESOURCE_ADAPTERS_FOR_ACL,
                TeamRolePermissionAssertion::SITE_ADAPTERS_FOR_ACL
            );
        }

        // Get all roles from ACL and exclude global_admin
        // This is better than hardcoding because it stays up to date with all roles
        $rolesToControl = $acl->getRoles();
        $rolesToControl = array_diff($rolesToControl, ["global_admin"]);

        $denyAssertion = new AssertionNegation($this->assertion);

        foreach ($this->entities as $resource) {
            foreach ($rolesToControl as $role) {
                foreach (self::ENTITY_PRIVILEGES as $privilege) {
                    if ($this->coreForbidsEntirely($role, $resource)) {
                        continue;
                    }
                    if (in_array($role, self::ROLES_WITH_DEFAULT_ALLOW, true)) {
                        $acl->deny($role, $resource, $privilege, $denyAssertion);
                    } else {
                        $acl->allow($role, $resource, $privilege, $this->assertion);
                    }
                }
            }
        }

        // There are some display adapters that use this permission to show add/edit links
        foreach ($this->adapters as $adapter) {
            foreach ($rolesToControl as $role) {
                foreach (self::ADAPTER_PRIVILEGES as $privilege) {
                    if ($this->coreForbidsEntirely($role, $adapter)) {
                        continue;
                    }
                    if (in_array($role, self::ROLES_WITH_DEFAULT_ALLOW, true)) {
                        $acl->deny($role, $adapter, $privilege, $denyAssertion);
                    } else {
                        $acl->allow($role, $adapter, $privilege, $this->assertion);
                    }
                }
            }
        }

        // Team-specific controls
        $acl->allow(null, 'Teams\Controller\Index', ['index', 'teamDetail', 'currentTeam']);
        $acl->allow('global_admin', [
            'Teams\Controller\Index',
            'Teams\Controller\Add',
            'Teams\Controller\Update',
        ]);
        $acl->allow('global_admin', 'Teams\Entity\TeamRole');
    }

    /**
     * Determine whether a role has no core-level ability to create, update,
     * or delete the given resource at all, regardless of team context.
     *
     * Resources owned by Teams itself (e.g. TeamResource, TeamAsset) have no
     * core-level rules to defer to, so the gate does not apply to them.
     *
     * @param string $role
     * @param string $resource Entity or adapter class name
     * @return bool
     */
    private function coreForbidsEntirely(string $role, string $resource): bool
    {
        if (!in_array($role, self::ROLES_WITHOUT_CORE_CREATE_UPDATE_DELETE, true)) {
            return false;
        }
        return strpos($resource, 'Teams\\') !== 0;
    }
}
