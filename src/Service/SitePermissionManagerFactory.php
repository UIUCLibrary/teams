<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SitePermissionManagerFactory implements FactoryInterface
{
    /**
     *
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return SitePermissionManager
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $logger = $container->get('Omeka\Logger');

        return new SitePermissionManager(
            $container->get('Omeka\EntityManager'),
            $container->get('Omeka\Settings\User'),
            $container->get('Omeka\Logger')
        );
    }
}
