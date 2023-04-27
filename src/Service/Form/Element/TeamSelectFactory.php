<?php


namespace Teams\Service\Form\Element;

use Teams\Form\Element\TeamSelect;
use Interop\Container\ContainerInterface;

class TeamSelectFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new TeamSelect(null, $options ?? []);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setUrlHelper($services->get('ViewHelperManager')->get('Url'));
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        $element->setAuthService($services->get('Omeka\AuthenticationService'));
        return $element;
    }
}
