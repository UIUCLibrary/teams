<?php


namespace Teams\Service;


use Interop\Container\ContainerInterface;
use Teams\Controller\MediaController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaControllerFactory implements FactoryInterface
{


    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MediaController(
            $services->get('Omeka\EntityManager')
        );
    }


}