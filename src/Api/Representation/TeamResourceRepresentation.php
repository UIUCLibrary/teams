<?php


namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

//legacy from deciding how much of the module to expose to the API
class TeamResourceRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-teams:TeamResource';
    }

    public function getJsonLd()
    {
        return [
            'team' => $this->team(),
            'resource' => $this->resource(),
            'resource-type' => $this->joinResourceName(),
        ];
    }



    public function team()
    {
        return $this->resource->getTeam()->getId();
    }

    public function resource()
    {
        return $this->resource->getResource()->getId();
    }

    public function id(): string
    {
        return $this->team() . '-' . $this->resource();
    }

    public function joinResourceName(): string
    {
        return$this->resource->getResource()->getResourceName();
    }
}
