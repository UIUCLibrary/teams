<?php


namespace Teams\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Teams\Form\Element\AllTeamSelect;
use Teams\Form\Element\UserSelect;

class UserSelectFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new UserSelect(null, $options ?? []);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setUrlHelper($services->get('ViewHelperManager')->get('Url'));
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
