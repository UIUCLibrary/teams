<?php


namespace Teams\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Omeka\Service\Form\Element\SiteSelectFactory;
use Teams\Form\Element\AllSiteSelectOrdered;
use Teams\Form\Element\AllSiteSelectOrderedByTeam;

class AllSiteSelectOrderedFactory extends SiteSelectFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new AllSiteSelectOrdered(null, $options ?? []);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
