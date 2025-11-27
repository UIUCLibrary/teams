<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Teams\Acl\TeamRolePermissionAssertion;

class AclRuleManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $assertion = $container->get(TeamRolePermissionAssertion::class);
        return new AclRuleManager($assertion);
    }
}
