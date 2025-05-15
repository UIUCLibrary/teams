<?php

namespace Teams\Form\Element;

use Laminas\Form\Element\Select;
use Omeka\Api\Manager as ApiManager;
use Omeka\Service\Exception\InvalidArgumentException;
use Omeka\Form\Element\AbstractGroupByOwnerSelect;

abstract class AbstractGroupBySelect extends Select
{
    /**
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * @param ApiManager $apiManager
     */
    public function setApiManager(ApiManager $apiManager)
    {
        $this->apiManager = $apiManager;
    }

    /**
     * @return ApiManager
     */
    public function getApiManager()
    {
        return $this->apiManager;
    }

    /**
     * Get the resource name.
     *
     * @return string
     */
    abstract public function getResourceName();

    /**
     * Get the team resource name.
     *
     * @return string
     */
    abstract public function getTeamResourceName();

    /**
     * Get the foreign key used for the team entity.
     *
     * @return string
     */
    abstract public function getFK();

    /**
     * Get the value label from a resource.
     *
     * @param $resource
     * @return string
     */
    abstract public function getValueLabel($resource);

    public function getValueOptions(): array
    {
        if ($this->getOption('group_by') == 'owner'){
            return parent::getValueOptionsValues();
        } else {
            $query = $this->getOption('query');
            if (!is_array($query)) {
                $query = [];
            }

            $resourceReps = $this->getApiManager()->search($this->getResourceName(), $query)->getContent();

            // Provide a way to filter the resource representations prior to
            // building the value options.
            $callback = $this->getOption('filter_resource_representations');
            if (is_callable($callback)) {
                $resourceReps = $callback($resourceReps);
            }

            if ($this->getOption('disable_group_by')) {
                // Group alphabetically by resource label without grouping by owner.
                $resources = [];
                foreach ($resourceReps as $resource) {
                    $resources[$this->getValueLabel($resource)][] = $resource->id();
                }
                ksort($resources);
                $valueOptions = [];
                foreach ($resources as $label => $ids) {
                    foreach ($ids as $id) {
                        $valueOptions[$id] = $label;
                    }
                }
            } elseif (is_array($this->getOption('group_by')) && array_key_exists('team', $this->getOption('group_by'))) {
                // Group in team or out of team
//                if (!is_int($this->getOption('group_by')['team'])){
//                    throw new InvalidArgumentException(
//                            'Invalid team id.',
//                    );
//                }
                $team = $this->apiManager->search('team',['id'=>$this->getOption('group_by')['team']])->getContent()[0];
                if (!$team) {
                    throw new InvalidArgumentException(
                        'Invalid team id.',
                    );
                }
                $groups["In team {$team->name()}"]['label'] = sprintf('In team %s', $team->name());
                $groups["In team {$team->name()}"]['resources'] = [];
                $groups["Not in team {$team->name()}"]['label'] = "Not in team";
                $groups["Not in team {$team->name()}"]['resources'] = [];
                $groups["Added to team"]['label'] = sprintf('Added to team %s', $team->name());
                $groups["Added to team"]['resources'] = [];
                $groups["Removed from team"]['label'] = sprintf('Removed from team %s', $team->name());
                $groups["Removed from team"]['resources'] = [];
                $teamResourceTemplates = $this->apiManager->search($this->getTeamResourceName(), ['team' => $team->id()],['returnScalar' => $this->getFK()])->getContent();
                foreach ($resourceReps as $resource) {

                    if (in_array($resource->id(),$teamResourceTemplates)){
                        $groups["In team {$team->name()}"]['resources'][] = $resource;
                    } else {
                        $groups["Not in team {$team->name()}"]['resources'][] = $resource;
                    }
                }
                ksort($groups);

                $valueOptions = [];
                foreach ($groups as $group) {
                    $options = [];
                    foreach ($group['resources'] as $resource) {
                        $options[$resource->id()] = $this->getValueLabel($resource);
                        if (!$options) {
                            continue;
                        }
                    }
                    $label = $group['label'];
                    $valueOptions[] = ['label' => $label, 'options' => $options];
                }
            }

            $prependValueOptions = $this->getOption('prepend_value_options');
            if (is_array($prependValueOptions)) {
                $valueOptions = $prependValueOptions + $valueOptions;
            }
            return $valueOptions;
        }
    }

}