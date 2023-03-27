<?php


namespace Teams\Service\Form\Element;

use Teams\Form\Element\RoleName;
use Interop\Container\ContainerInterface;

class RoleNameFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new RoleName(null, $options ?? []);
        $element->setEntityManager($services->get('Omeka\EntityManager'));
        return $element;
    }
}
