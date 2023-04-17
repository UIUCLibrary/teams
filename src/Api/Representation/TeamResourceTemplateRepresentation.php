<?php


namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

//legacy from deciding how much of the module to expose to the API
class TeamResourceTemplateRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-teams:TeamResourceTemplate';
    }

    public function getJsonLd()
    {
        return [
            'team' => $this->team(),
            'resource-template' => $this->resource(),
        ];
    }

    public function getReference()
    {
        return new TeamReference($this->resource, $this->getAdapter());
    }

    public function team()
    {
        return $this->resource->getTeam()->getId();
    }

    public function resource()
    {
        return $this->resource->getResourceTemplate()->getId();
    }

    public function id(): string
    {
        return $this->team() . '-' . $this->resource();
    }
}
