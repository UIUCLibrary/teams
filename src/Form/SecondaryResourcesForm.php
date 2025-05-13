<?php

namespace Teams\Form;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Laminas\Authentication\AuthenticationService;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Select;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\ResourceTemplateSelect;
use Laminas\Form\Form;
use Omeka\Settings\Settings;
use Teams\Form\Element\BlankTeamSelect;
use Teams\Form\Element\ItemSetTeamSelect;
use Teams\Form\Element\ResourceTemplateTeamsSelect;


class SecondaryResourcesForm extends Form
{

    protected $options;

    /**
     * @var EntityManager
     */
    protected EntityManager $entityManager;

    /**
     * @var ApiManager
     */
    protected ApiManager $apiManager;

    /**
     * @var AuthenticationService
     */
    protected AuthenticationService $authenticationService;

    /**
     * @var Settings
     */
    protected $settings;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name,  $options);
    }

    public function init()
    {
        //determine which roles can bypass team filter

        if (is_array($this->settings->get('teams_filter_bypass_roles'))) {
            $show_all_options = in_array($this->authenticationService->getIdentity()->getRole(), $this->settings->get('teams_filter_bypass_roles'));
        } else {
            $show_all_options = $this->authenticationService->getIdentity()->getRole() === 'global_admin';
        }

        $this->setAttribute('id', 'team-secondary-resources-form');

        if (array_key_exists('team_id', $this->options)){
            $teamId = $this->options['team_id'];
        } else {
            $teamId = 0;
        }
        if ($teamId === 0) {
            $disableGroupBy = true;
        } else {
            $disableGroupBy = false;
        }

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
                'disable_group_by' => $disableGroupBy,
                'group_by' => ['team' => $teamId],
                'label' => 'Select Item Sets',
                'query' => ['bypass_team_filter' => true],
                'filter_resource_representations' => $show_all_options ? "" : function ($itemsets) {
                    // The user must have permission to assign items to the site.
                    foreach ($itemsets as $index => $itemset) {
                        if (!$this->userAllowed($itemset)) {
                            unset($itemsets[$index]);
                        }
                    }
                    return $itemsets;
                },

            ],
            'attributes' => [
                'value' =>  $teamId ? $this->apiManager->search('team-resource', ['team'=>$teamId], ['returnScalar' => 'resource'])->getContent():[],
                'id' => 'o-modules-team-item-sets',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'Select item sets', // @translate

            ]
        ]);
        $this->add([
            'name' => 'resource_templates',
            'type' => ResourceTemplateTeamsSelect::class,
            'options' => [
                'disable_group_by' => $disableGroupBy,
                'group_by' => ['team' => $teamId],
                'label' => 'Select Resource Templates',
                'query' => ['bypass_team_filter' => true], //get all the responses, then filter below as appropriate
                'class' => 'chosen-select',
                'filter_resource_representations' => $show_all_options ? "" : function ($templates) {
                    // The user must have permission to assign items to the site.
                    foreach ($templates as $index => $template) {
                        if (!$this->userAllowed($template)) {
                            unset($templates[$index]);
                        }
                    }
                    return $templates;
                },
            ],
            'attributes' => [
                'value' =>  $teamId ? $this->apiManager->search('team-resource-template', ['team'=>$teamId], ['returnScalar' => 'resource_template'])->getContent():[],
                'class' => 'chosen-select',
                'multiple' => true,
                'id' => 'o-modules-team-resource-templates',
                'data-placeholder' => 'Select resource templates', // @translate

            ]
        ]);
        $this->add([
            'name' => 'remove_item_sets',
            'type' => Select::class,
            'options' => [
                'label' => 'Item Sets to be removed',
            ],
            'attributes' => [
                'id' => 'o-modules-team-remove-item-sets',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'None', // @translate

            ]
        ]);
        $this->add([
            'name' => 'remove_resource_templates',
            'type' => Select::class,
            'options' => [
                'label' => 'Resource Templates to be removed',
            ],
            'attributes' => [
                'id' => 'o-modules-team-remove-resource-templates',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'None', // @translate
            ]
        ]);



    }

    /**
     * Determines if the user should be able to add this resource to other teams by checking to see if the user has
     * resource editing privileges in any team where the resource is a team_resource. I.e., is there any context where
     * the user can edit this item? If so, then they can edit which teams it belongs in.
     *
     * @param AbstractResourceRepresentation $resource
     * @return bool
     *
     */
    private function userAllowed(AbstractResourceRepresentation $resource): bool
    {
        $user = $this->authenticationService->getIdentity();
        $users_edit_roles = $this->apiManager->search('team-user', ['user'=> $user, 'can_delete_resources'=>1], ['returnScalar' => 'team'] )->getContent();
        if (str_contains('resource-template', $resource->getControllerName())) {
            $resource_type = 'resource-template';
            $table_name = 'resource_template';
        } elseif(str_contains($resource->getControllerName(), 'item') || str_contains('media', $resource->getControllerName())) {
            $resource_type = 'resource';
            $table_name = 'resource';

        } else
        {
            throw new InvalidArgumentException(
                sprintf(
                    'Cant create query for "%1$s" type resource.',
                    $resource->getControllerName()
                )
            );
        }

        $resources_teams = $this->apiManager->search('team-' . $resource_type, [$table_name => $resource->id()], ['returnScalar'=>'team'])->getContent();

        if (!empty(array_intersect($users_edit_roles, $resources_teams))){
            return true;
        } else {
            return false;
        }

    }


    /**
     * @param ApiManager $apiManager
     * @return void
     */
    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    /**
     * @param AuthenticationService $authenticationService
     * @return void
     */
    public function setAuthService(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }


    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Settings $settings
     */
    public function setSettings(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return Settings
     */
    public function getSettings(): Settings
    {
        return $this->settings;
    }

}