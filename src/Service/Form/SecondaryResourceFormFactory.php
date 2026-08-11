<?php

namespace Teams\Service\Form;

use Interop\Container\ContainerInterface;
use Teams\Form\SecondaryResourcesForm;

class SecondaryResourceFormFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $form = new SecondaryResourcesForm(null, $options);

        $form->setApiManager($services->get('Omeka\ApiManager'));
        $form->setAuthService($services->get('Omeka\AuthenticationService'));
        $form->setEntityManager($services->get('Omeka\EntityManager'));
        $form->setSettings($services->get('Omeka\Settings'));
        return $form;
    }
}