<?php
namespace Teams\Service;

use Teams\Controller\DeleteController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DeleteControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $deleteController = new DeleteController($serviceLocator->get('Omeka\EntityManager'));
        return $deleteController;
    }
}