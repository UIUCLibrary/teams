<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SitePermissionManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SitePermissionManager(
            $container->get('Omeka\EntityManager'),
            $container->get('Omeka\Settings\User')
        );
    }
}
