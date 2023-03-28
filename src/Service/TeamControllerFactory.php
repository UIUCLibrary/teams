<?php

namespace Teams\Service;

use Interop\Container\Containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Teams\Controller\TeamController;

class TeamControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $teamController = new TeamController();
        return $teamController;
        // TODO: Implement __invoke() method.
    }

}