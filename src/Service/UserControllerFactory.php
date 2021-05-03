<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Teams\Controller\UserController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new UserController(
            $services->get('Omeka\EntityManager')
        );
    }
}
