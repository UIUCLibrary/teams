<?php
namespace Teams\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Teams\Mvc\Controller\Plugin\TeamAuth;
use Laminas\ServiceManager\Factory\FactoryInterface;
use \Omeka\Entity\User;

class TeamAuthFactory implements FactoryInterface
{
    /**
     *
     * @param ContainerInterface $services
     * @param $requestedName
     * @param array|null $options
     * @return TeamAuth
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $user = new User();
        return new TeamAuth($services->get('Omeka\EntityManager'), $user);
    }
}
