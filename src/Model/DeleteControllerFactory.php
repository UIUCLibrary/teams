<?php
namespace Teams\Model;

use Teams\Controller\DeleteController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DeleteControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $deleteController = new DeleteController($serviceLocator->get('Omeka\EntityManager'));
        return $deleteController;
    }
}