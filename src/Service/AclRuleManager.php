<?php
namespace Teams\Service;

use Laminas\Permissions\Acl\Acl;
use Teams\Acl\TeamRolePermissionAssertion;
use Omeka\Permissions\Assertion\AssertionNegation;

class AclRuleManager
{
    /**
     * Roles that should have team-based access control applied
     */
    private const ROLES_TO_CONTROL = ['site_admin', 'editor', 'author'];

    /**
     * Privileges that should be controlled by team permissions
     */
    private const PRIVILEGES_TO_CONTROL = [
        'update', 'edit',
        'delete', 'delete-confirm',
        'create', 'add',
        'batch-delete', 'batch_delete', 'batch_delete_all',
        'batch-update', 'batch_update_all',
        'batch-edit', 'batch-edit-all',
    ];

    /**
     * @var TeamRolePermissionAssertion
     */
    private $assertion;

    /**
     * @var array|null Cached list of Omeka resources
     */
    private $omekaResources;

    public function __construct(TeamRolePermissionAssertion $assertion)
    {
        $this->assertion = $assertion;
    }

    public function applyRules(Acl $acl)
    {
        // Use constants from assertion class to ensure synchronization between ACL rules and assertion logic
        // Cache the merged resources to avoid repeated operations
        if ($this->omekaResources === null) {
            $this->omekaResources = [
                ...TeamRolePermissionAssertion::RESOURCE_ENTITIES_FOR_ACL,
                ...TeamRolePermissionAssertion::SITE_ENTITIES_FOR_ACL
            ];
        }
        
        $denyAssertion = new AssertionNegation($this->assertion);

        foreach (self::ROLES_TO_CONTROL as $role) {
            $acl->deny($role, $this->omekaResources, self::PRIVILEGES_TO_CONTROL, $denyAssertion);
        }
    }
}
