<?php
namespace Teams\Service;

use Teams\Controller\UpdateController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;


class UpdateControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new UpdateController(
            $services->get('Omeka\EntityManager'),
            $services->get(SitePermissionManager::class)
        );
    }
}

