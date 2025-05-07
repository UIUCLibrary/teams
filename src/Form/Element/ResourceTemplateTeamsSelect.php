<?php

namespace Teams\Form\Element;


class ResourceTemplateTeamsSelect extends AbstractGroupBySelect
{

    /**
     * @inheritDoc
     */
    public function getResourceName()
    {
        return 'resource_templates';
    }

    /**
     * @inheritDoc
     */
    public function getValueLabel($resource)
    {
        return $resource->label();
    }

    public function getTeamResourceName()
    {
        return 'team-resource-template';
    }

    public function getFK()
    {
        return 'resource_template';
    }
}