<?php
namespace Teams\Service\Form;

use Interop\Container\ContainerInterface;
use Teams\Form\TeamForm;

/**
 * Factory for the consolidated TeamForm.
 *
 * Follows the Omeka S ResourceForm factory pattern: inject services into the
 * form object before the form manager calls init(), then return it.
 *
 * $options are passed straight through from the controller's
 *   $this->getForm(TeamForm::class, ['team_id' => $id])
 * call so that init() can pre-populate fields for an existing team.
 */
class TeamFormFactory
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): TeamForm
    {
        $form = new TeamForm(null, $options ?? []);
        $form->setApiManager($services->get('Omeka\ApiManager'));
        $form->setAuthService($services->get('Omeka\AuthenticationService'));
        $form->setSettings($services->get('Omeka\Settings'));
        return $form;
    }
}
