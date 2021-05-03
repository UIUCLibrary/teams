<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Teams\Controller\ItemController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ItemController(
            $services->get('Omeka\Media\Ingester\Manager'),
            $services->get('Omeka\EntityManager')
        );
    }
}
