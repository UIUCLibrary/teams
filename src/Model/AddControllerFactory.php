<?php
namespace Teams\Model;

use Teams\Controller\AddController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;


class AddControllerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $addController = new AddController($services->get('Omeka\EntityManager'));
        return $addController;
    }
}