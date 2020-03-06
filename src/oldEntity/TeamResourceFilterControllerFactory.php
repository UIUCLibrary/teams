<?php


namespace Teams\Model;

use Interop\Container\ContainerInterface;
use Teams\Controller\TeamResourceFilterController;
use Zend\ServiceManager\Factory\FactoryInterface;

class TeamResourceFilterControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TeamResourceFilterController(
            $services->get('Omeka\Media\Ingester\Manager'),
            $services->get('Omeka\EntityManager')
        );
    }

}