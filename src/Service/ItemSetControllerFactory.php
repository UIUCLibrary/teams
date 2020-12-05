<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Teams\Controller\ItemSetController;
use Zend\ServiceManager\Factory\FactoryInterface;

class ItemSetControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ItemSetController(
            $services->get('Omeka\EntityManager')
        );
    }
}
