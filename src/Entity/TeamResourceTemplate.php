<?php


namespace Teams\Entity;


use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\ResourceTemplate;

/**
 * User is a table in the core, so it is not annotable, so the join table is
 * declared as the entity TeamResource in order to bypass this issue.
 * This entity is available only by the orm, not by Omeka S.
 *
 * @Entity
 */
class TeamResourceTemplate extends AbstractEntity
{

    /**
     * @var Team
     * @Id
     * @ManyToOne(
     *     targetEntity="Teams\Entity\Team",
     *     inversedBy="team_resource_templates",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $team;

    /**
     * @var ResourceTemplate
     * @Id
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\ResourceTemplate",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $resource_template;

    public function __construct(Team $team, ResourceTemplate $resource_template)
    {
        $this->team = $team;
        $this->resource_template = $resource_template;
    }

    public function getTeam()
    {
        return $this->team;
    }

    public function getResourceTemplate()
    {
        return $this->resource_template;
    }

    public function __toString()
    {
        return json_encode([
            'team' => $this->getTeam()->getId(),
            'resource_template' => $this->getResourceTemplate()->getId(),
        ]);
    }

    public function getId(): array
    {
        return [
            'team' => $this->getTeam()->getId(),
            'resource-template' => $this->getResourceTemplate()->getId()
        ];
    }

}