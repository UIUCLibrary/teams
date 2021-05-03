<?php
namespace Teams\Service;

use Teams\Controller\IndexController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $indexController = new IndexController($services->get('Omeka\EntityManager'));
        return $indexController;
    }
}
