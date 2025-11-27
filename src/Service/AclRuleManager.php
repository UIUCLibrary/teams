<?php
namespace Teams\Service;

use Laminas\Permissions\Acl\Acl;
use Teams\Acl\TeamRolePermissionAssertion;
use Omeka\Permissions\Assertion\AssertionNegation;

class AclRuleManager
{
    /**
     * @var TeamRolePermissionAssertion
     */
    private $assertion;

    public function __construct(TeamRolePermissionAssertion $assertion)
    {
        $this->assertion = $assertion;
    }

    public function applyRules(Acl $acl)
    {
        $omekaResources = [
            \Omeka\Entity\Item::class,
            \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Media::class,
            \Omeka\Entity\Site::class,
            \Omeka\Entity\SitePage::class,
            \Omeka\Entity\ResourceTemplate::class,
            \Omeka\Entity\Asset::class,
        ];
        
        $rolesToControl = ['site_admin', 'editor', 'author'];

        $privilegesToControl =[
            'update', 'edit',
            'delete', 'delete-confirm',
            'create', 'add',
            'batch-delete', 'batch_delete', 'batch_delete_all',
            'batch-update', 'batch_update_all',
            'batch-edit', 'batch-edit-all',
        ];
        
        $denyAssertion = new AssertionNegation($this->assertion);

        foreach ($rolesToControl as $role) {
            $acl->deny($role, $omekaResources, $privilegesToControl, $denyAssertion);
        }
    }
}
