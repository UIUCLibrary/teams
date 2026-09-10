<?php
namespace Teams\Service;

use Laminas\Permissions\Acl\Acl;
use Teams\Acl\TeamRolePermissionAssertion;

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

        // Replace each non-admin role's blanket core allow rule for these
        // resources with a conditional allow gated by team membership and
        // team role capability. Laminas\Permissions\Acl looks up rules by
        // the exact (role, resource, privilege) triple and the *last*
        // allow()/deny() call for a given triple replaces any earlier one
        // (see Acl::setRule()), so this intentionally replaces Omeka core's
        // unconditional "editor/author/etc. can update/delete/create this
        // resource" rule rather than adding a rule alongside it.
        //
        // This must use allow() with the assertion itself, not deny() with
        // a negated assertion: when Acl::getRuleType() evaluates a rule
        // whose assertion returns false, it treats the rule as absent (not
        // as "the opposite verdict") and continues searching the role/
        // resource hierarchy for another applicable rule -- it does not
        // fall back to whatever rule was just replaced. A deny() rule can
        // therefore only ever deny (when its assertion is true) or become
        // invisible (when its assertion is false, deferring to a default
        // that is deny once the original core allow rule is gone); it can
        // never grant access, which would make every non-admin role unable
        // to create/update/delete any of these resources at all, even when
        // fully authorized by their team role. allow() with the assertion
        // (unnegated) grants access exactly when the assertion is true and
        // defers to the same "no other rule applies" default deny when it
        // is false, which is the intended behavior.
        foreach ($this->entities as $resource) {
            foreach ($rolesToControl as $role) {
                foreach (self::ENTITY_PRIVILEGES as $privilege) {
                    $acl->allow($role, $resource, $privilege, $this->assertion);
                }
            }
        }

        // Apply adapter-level ACL rules
        // There are some display adapters that use this permission to show add/edit links
        foreach ($this->adapters as $adapter) {
            foreach ($rolesToControl as $role) {
                foreach (self::ADAPTER_PRIVILEGES as $privilege) {
                    $acl->allow($role, $adapter, $privilege, $this->assertion);
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
}
