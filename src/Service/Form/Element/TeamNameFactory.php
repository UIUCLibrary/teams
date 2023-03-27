<?php


namespace Teams\Service\Form\Element;

use Teams\Form\Element\TeamName;
use Interop\Container\ContainerInterface;

class TeamNameFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new TeamName( null, $options ?? []);
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
