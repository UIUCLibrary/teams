<?php

namespace Teams\Service\Form\Element;

use Interop\Container\Containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Teams\Form\Element\ResourceTemplateTeamsSelect;

class ResourceTemplateTeamsSelectFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new ResourceTemplateTeamsSelect;
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}