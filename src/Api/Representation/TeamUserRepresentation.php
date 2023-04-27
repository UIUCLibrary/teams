<?php
namespace Teams\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\User;
use Teams\Api\Representation\TeamUserReference;

//legacy from deciding how much of the module to expose to the API
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
            'o:team' => [
                'o:id' => $this->team()->getId(),
                'o:name' => $this->team()->getName(),
            ],
            'o:user' =>
                [
                    'o:id' => $this->user()->getId(),
                    'o:name' => $this->user()->getName(),
                ],
            'o:role' => [
                'o:id' => $this->role()->getId(),
                'o:name' => $this->role()->getName(),
            ],
            'o:current' => $this->current(),

        ];
    }

    public function getReference()
    {
        return new TeamUserReference($this->resource, $this->adapter);
    }

    public function team()
    {
        return $this->resource->getTeam();
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
