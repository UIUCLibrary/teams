<?php


namespace Teams\Service;


use Interop\Container\ContainerInterface;
use Teams\Controller\ChangeTeamController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ChangeTeamControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ChangeTeamController(
            $services->get('Omeka\EntityManager')
        );
    }

}