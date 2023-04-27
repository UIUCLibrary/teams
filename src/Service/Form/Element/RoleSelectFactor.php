<?php


namespace Teams\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Teams\Form\Element\RoleSelect;

class RoleSelectFactor
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new RoleSelect(null, $options ?? []);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setUrlHelper($services->get('ViewHelperManager')->get('Url'));
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
