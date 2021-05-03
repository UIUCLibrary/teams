<?php
namespace Teams\Service\ViewHelper;


use Teams\View\Helper\RoleAuth;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class RoleAuthFactory implements FactoryInterface

{
    /**
     *
     * @param ContainerInterface $services
     * @param $requestedName
     * @param array|null $options
     * @return RoleAuth
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new RoleAuth($services->get('Omeka\EntityManager'));
    }
}





