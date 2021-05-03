<?php


namespace Teams\Service;


use Teams\Controller\TestController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;


class TestControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $testController = new TestController($services->get('Omeka\EntityManager'));
        return $testController;
    }

}