<?php
namespace Teams\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;

/**
 * User is a table in the core, so it is not annotable, so the join table is
 * declared as the entity TeamResource in order to bypass this issue.
 * This entity is available only by the orm, not by Omeka S.
 *
 * @Entity
 */
  class TeamResource extends AbstractEntity
{
//
//     /**
//      * @var int
//      * @Id
//      * @Column(type="integer")
//      * @GeneratedValue
//      */
//     protected $id;
    /**
     * @Id
     * @var Team
     * @ManyToOne(
     *     targetEntity="Teams\Entity\Team",
     *     inversedBy="team_resources",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $team;

    /**
     * @Id
     * @var Resource
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $resource;

    public function __construct(Team $team, Resource $resource)
    {
        $this->team = $team;
        $this->resource = $resource;
    }

    public function getTeam()
    {
        return $this->team;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function __toString()
    {
        return json_encode([
            'team' => $this->getTeam()->getId(),
            'resource' => $this->getResource()->getId(),
        ]);
    }


     /**
      * @inheritDoc
      */
     public function getResourceName()
     {
         // TODO: Implement getResourceName() method.
     }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return [
            'team' => $this->getTeam()->getId(),
            'resource' => $this->getResource()->getId()
        ];
    }
}
