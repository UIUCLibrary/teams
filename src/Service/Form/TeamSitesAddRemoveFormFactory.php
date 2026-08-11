<?php

namespace Teams\Service\Form;

use Interop\Container\ContainerInterface;
use Teams\Form\TeamSitesAddRemoveForm;

class TeamSitesAddRemoveFormFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $form = new TeamSitesAddRemoveForm(null, $options ?? []);
        $form->setApiManager($services->get('Omeka\ApiManager'));
        return $form;
    }
}
