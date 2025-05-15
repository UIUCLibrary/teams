<?php

namespace Teams\Form\Element;


use Omeka\Api\Manager as ApiManager;

class ItemSetTeamSelect extends AbstractGroupBySelect
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
     * @inheritDoc
     */
    public function getResourceName()
    {
        return 'item_sets';
    }

    /**
     * @inheritDoc
     */
    public function getTeamResourceName()
    {
        return 'team-resource';
    }

    /**
     * @inheritDoc
     */
    public function getFK()
    {
       return 'resource';
    }

    /**
     * @inheritDoc
     */
    public function getValueLabel($resource)
    {
       return $resource->title();
    }
}