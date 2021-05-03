<?php
namespace Teams\Service;

use Interop\Container\ContainerInterface;
use Teams\Controller\SiteAdmin\IndexController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteIndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new IndexController(
            $services->get('Omeka\Site\ThemeManager'),
            $services->get('Omeka\Site\NavigationLinkManager'),
            $services->get('Omeka\Site\NavigationTranslator'),
            $services->get('Omeka\EntityManager')
        );
    }
}
