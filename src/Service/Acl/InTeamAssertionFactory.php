<?php
namespace Teams\Service\Acl;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Teams\Acl\InTeamAssertion;

class InTeamAssertionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new InTeamAssertion(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Status')
        );
    }
}
