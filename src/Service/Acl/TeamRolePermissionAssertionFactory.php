<?php
namespace Teams\Service\Acl;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Teams\Acl\TeamRolePermissionAssertion;

class TeamRolePermissionAssertionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TeamRolePermissionAssertion(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Status')
        );
    }
}
