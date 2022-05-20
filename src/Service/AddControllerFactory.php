<?php
namespace Teams\Service;

use Teams\Controller\AddController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AddControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $addController = new AddController($services->get('Omeka\EntityManager'));
        return $addController;
    }
}
