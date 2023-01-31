<?php


namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

//legacy from deciding how much of the module to expose to the API
class TeamAssetRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-teams:TeamAsset';
    }

    public function getJsonLd()
    {
        return [
            'team' => $this->team(),
            'asset' => $this->resource(),
        ];
    }

    public function getReference()
    {
        return new TeamReference($this->asset, $this->getAdapter());
    }

    public function team()
    {
        return $this->resource->getTeam()->getId();
    }

    public function asset()
    {
        return $this->resource->getAsset()->getId();
    }

    public function id(): string
    {
        return $this->team() . '-' . $this->resource();
    }
}
