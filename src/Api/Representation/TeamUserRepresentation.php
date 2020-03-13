<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\User;
use Teams\Api\Representation\TeamUserReference;

/**
 * TeamUserRepresentation representation.
 */
class TeamUserRepresentation extends AbstractEntityRepresentation
{

    public function getJsonLdType()
    {
        return 'o-module-teams:TeamUser';
    }

    public function getJsonLd()
    {
        return [
            'o:team' => $this->team(),
            'o:user' => $this->user(),
            'o:role' => $this->role(),
            'o:current' => $this->current(),

        ];
    }

    public function getReference()
    {
        return new TeamUserReference($this->resource, $this->adapter);
    }

    public function team()
    {
        return $this->resource->getName();
    }

    public function user()
    {
        return $this->resource->getUser();
    }

    public function role()
    {
        return $this->resource->getRole();
    }

    public function current()
    {
        return $this->resource->getCurrent();
    }

//    public function media()
//    {
//        $media = [];
//        $mediaAdapter = $this->getAdapter('media');
//        foreach ($this->resource->getMedia() as $mediaEntity) {
//            $media[] = $mediaAdapter->getRepresentation($mediaEntity);
//        }
//        return $media;
//    }


}
