<?php
namespace Teams\Acl;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TeamRolePermissionAssertionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TeamRolePermissionAssertion();
    }
}
