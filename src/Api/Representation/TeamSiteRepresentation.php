<?php


namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

//legacy from deciding how much of the module to expose to the API
class TeamSiteRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-teams:TeamSite';
    }

    public function getJsonLd()
    {
        return [
            'team' => $this->team(),
            'site' => $this->resource(),
        ];
    }

    public function team()
    {
        return $this->resource->getTeam()->getId();
    }

    public function resource()
    {
        return $this->resource->getSite()->getId();
    }

    public function id(): string
    {
        return $this->team() . '-' . $this->resource();
    }
}
