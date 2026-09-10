<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemSiteSyncManagerFactory implements FactoryInterface
{
    /**
     *
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return ItemSiteSyncManager
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ItemSiteSyncManager(
            $container->get('Omeka\ApiManager'),
            $container->get('Omeka\EntityManager')
        );
    }
}
