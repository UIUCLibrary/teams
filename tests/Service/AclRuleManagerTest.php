<?php
namespace Teams\Tests\Service;

use Teams\Service\AclRuleManager;
use Teams\Acl\TeamRolePermissionAssertion;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Role\GenericRole;
use Omeka\Entity\Item;
use Tests\TestCase;

class AclRuleManagerTest extends TestCase
{
    /** @var AclRuleManager */
    private $ruleManager;

    /** @var TeamRolePermissionAssertion|\PHPUnit\Framework\MockObject\MockObject */
    private $assertionMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->assertionMock = $this->createMock(TeamRolePermissionAssertion::class);
        $this->ruleManager = new AclRuleManager($this->assertionMock);
    }

    public function testApplyRulesDeniesPreviouslyAllowedPermission()
    {
        // Arrange
        $acl = new Acl;
        $role = new GenericRole('editor');
        $resource = new Item;
        $acl->addRole($role);
        $acl->addResource($resource);
        $acl->allow($role, $resource, 'update');
        $this->assertTrue($acl->isAllowed($role, $resource, 'update'));

        $this->assertionMock->method('assert')->willReturn(false);

        // Act
        $this->ruleManager->applyRules($acl);

        // Assert
        $this->assertFalse($acl->isAllowed($role, $resource, 'update'));
    }
}
