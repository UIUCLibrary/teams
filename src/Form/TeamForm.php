<?php
namespace Teams\Form;

use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Form;
use Omeka\Api\Manager as ApiManager;
use Omeka\Form\Element as OmekaElement;
use Omeka\Settings\Settings;
use Teams\Form\Element\AllSiteSelect;
use Teams\Form\Element\ItemSetTeamSelect;
use Teams\Form\Element\ResourceTemplateTeamsSelect;

/**
 * Form for creating and editing a team.
 *
 * All fields are assembled by TeamFormFactory, which injects the required
 * services before the form manager calls init().
 *
 * Options:
 *   team_id (int): The id of the team being edited, or 0 / absent for a new team.
 */
class TeamForm extends Form
{
    /**
     * @var ApiManager
     */
    protected ApiManager $apiManager;

    /**
     * @var AuthenticationService
     */
    protected AuthenticationService $authService;

    /**
     * @var Settings
     */
    protected Settings $settings;

    public function init(): void
    {
        $teamId = (int) ($this->getOption('team_id') ?? 0);
        $isNewTeam = ($teamId === 0);

        // --- Team details ---

        $this->add([
            'name' => 'o:name',
            'type' => 'Text',
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'o:description',
            'type' => 'Text',
            'options' => [
                'label' => 'Description', // @translate
            ],
            'attributes' => [
                'id' => 'comment',
                'required' => false,
            ],
        ]);

        // --- Item management ---

        $this->add([
            'type' => 'radio',
            'name' => 'item_assignment_action',
            'options' => [
                'label' => 'Manage current items', // @translate
                'value_options' => [
                    'no_action' => 'Do nothing', // @translate
                    'add' => 'Add - keep existing items and assign items from a new search query', // @translate
                    'replace' => 'Replace - unassign all items and assign items from a new search query', // @translate
                    'remove' => 'Remove - unassign items from a new search query', // @translate
                    'remove_all' => 'Remove all - unassign all items', // @translate
                ],
            ],
            'attributes' => [
                'value' => 'no_action',
            ],
        ]);

        $this->add([
            'type' => 'checkbox',
            'name' => 'save_search',
        ]);

        $this->add([
            'type' => OmekaElement\Query::class,
            'name' => 'item_pool',
            'options' => [
                'label' => 'Search query', // @translate
                'query_resource_type' => 'items',
                'query_partial_excludelist' => [
                    'common/advanced-search/site',
                    'common/advanced-search/sort',
                ],
            ],
        ]);

        // --- Secondary resources ---

        $bypassRoles = $this->settings->get('teams_filter_bypass_roles');
        $showAllOptions = is_array($bypassRoles)
            ? in_array($this->authService->getIdentity()->getRole(), $bypassRoles)
            : $this->authService->getIdentity()->getRole() === 'global_admin';

        $this->add([
            'name' => 'recursive_item_sets',
            'type' => 'checkbox',
            'options' => [
                'label' => 'Recursively add items of selected item sets?', // @translate
                'info' => 'This will also add all of the items inside the itemset', // @translate
            ],
            'attributes' => [
                'id' => 'o-modules-team-recursive_item_sets',
                'value' => true,
            ],
        ]);

        $this->add([
            'name' => 'item_sets',
            'type' => ItemSetTeamSelect::class,
            'options' => [
                'disable_group_by' => $isNewTeam,
                'group_by' => ['team' => $teamId],
                'label' => 'Select Item Sets', // @translate
                'query' => ['bypass_team_filter' => true, 'all_user_teams' => true],
                'filter_resource_representations' => $showAllOptions ? '' : function ($itemsets) {
                    foreach ($itemsets as $index => $itemset) {
                        if (!$this->userAllowedToAssign($itemset)) {
                            unset($itemsets[$index]);
                        }
                    }
                    return $itemsets;
                },
            ],
            'attributes' => [
                'value' => $isNewTeam
                    ? []
                    : $this->apiManager->search('team-resource', ['team' => $teamId], ['returnScalar' => 'resource'])->getContent(),
                'id' => 'o-modules-team-item-sets',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'Select item sets', // @translate
            ],
        ]);

        $this->add([
            'name' => 'resource_templates',
            'type' => ResourceTemplateTeamsSelect::class,
            'options' => [
                'disable_group_by' => $isNewTeam,
                'group_by' => ['team' => $teamId],
                'label' => 'Select Resource Templates', // @translate
                'query' => ['bypass_team_filter' => true, 'all_user_teams' => true],
                'filter_resource_representations' => $showAllOptions ? '' : function ($templates) {
                    foreach ($templates as $index => $template) {
                        if (!$this->userAllowedToAssign($template)) {
                            unset($templates[$index]);
                        }
                    }
                    return $templates;
                },
            ],
            'attributes' => [
                'value' => $isNewTeam
                    ? []
                    : $this->apiManager->search('team-resource-template', ['team' => $teamId], ['returnScalar' => 'resource_template'])->getContent(),
                'class' => 'chosen-select',
                'multiple' => true,
                'id' => 'o-modules-team-resource-templates',
                'data-placeholder' => 'Select resource templates', // @translate
            ],
        ]);

        $this->add([
            'name' => 'team_sites',
            'type' => AllSiteSelect::class,
            'options' => [
                'label' => 'Sites', // @translate
                'info' => 'The sites that should belong to this team', // @translate
            ],
            'attributes' => [
                'id' => 'sites',
                'class' => 'chosen-select',
                'multiple' => true,
                'value' => $isNewTeam
                    ? []
                    : array_values($this->apiManager
                        ->search('team-site', ['team' => $teamId], ['returnScalar' => 'site'])
                        ->getContent()),
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add(['name' => 'item_assignment_action', 'allow_empty' => true]);
        $inputFilter->add(['name' => 'save_search', 'allow_empty' => true]);
        $inputFilter->add(['name' => 'team_sites', 'allow_empty' => true, 'required' => false]);


        parent::init();
    }

    /**
     * Returns true if the current user may assign the given resource to another team.
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation $resource
     */
    private function userAllowedToAssign($resource): bool
    {
        $user = $this->authService->getIdentity();
        $editableTeams = $this->apiManager
            ->search('team-user', ['user' => $user, 'can_delete_resources' => 1], ['returnScalar' => 'team'])
            ->getContent();

        if (str_contains($resource->getControllerName(), 'resource-template')) {
            $resourceType = 'resource-template';
        } elseif (str_contains($resource->getControllerName(), 'item') || str_contains($resource->getControllerName(), 'media')) {
            $resourceType = 'resource';
        } else {
            return false;
        }

        $resourceTeams = $this->apiManager
            ->search('team-' . $resourceType, [$resourceType => $resource->id()], ['returnScalar' => 'team'])
            ->getContent();

        return !empty(array_intersect($editableTeams, $resourceTeams));
    }

    public function setApiManager(ApiManager $apiManager): void
    {
        $this->apiManager = $apiManager;
    }

    public function setAuthService(AuthenticationService $authService): void
    {
        $this->authService = $authService;
    }

    public function setSettings(Settings $settings): void
    {
        $this->settings = $settings;
    }
}
