<?php


namespace Teams\Service;


use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Teams\Controller\ResourceTemplateController;

class ResourceTemplateControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ResourceTemplateController($services->get('Omeka\DataTypeManager'), $services->get('Omeka\EntityManager'));
    }

}