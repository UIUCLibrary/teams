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

        // Create a single AssertionNegation instance to reuse across all deny rules
        $denyAssertion = new AssertionNegation($this->assertion);

        // Apply entity-level ACL rules
        foreach ($this->entities as $resource) {
            foreach ($rolesToControl as $role) {
                foreach (self::ENTITY_PRIVILEGES as $privilege) {
                    $acl->deny($role, $resource, $privilege, $denyAssertion);
                }
            }
        }

        // Apply adapter-level ACL rules
        // There are some display adapters that use this permission to show add/edit links
        foreach ($this->adapters as $adapter) {
            foreach ($rolesToControl as $role) {
                foreach (self::ADAPTER_PRIVILEGES as $privilege) {
                    $acl->deny($role, $adapter, $privilege, $denyAssertion);
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
