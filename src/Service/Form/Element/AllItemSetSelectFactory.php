<?php


namespace Teams\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Teams\Form\Element\AllItemSetSelect;

class AllItemSetSelectFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new AllItemSetSelect(null, $options ?? []);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setUrlHelper($services->get('ViewHelperManager')->get('Url'));
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
