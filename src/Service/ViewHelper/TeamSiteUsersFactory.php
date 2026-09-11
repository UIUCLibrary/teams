<?php
namespace Teams\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Teams\View\Helper\TeamSiteUsers;

class TeamSiteUsersFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null): TeamSiteUsers
    {
        return new TeamSiteUsers($services->get('Omeka\EntityManager'));
    }
}
