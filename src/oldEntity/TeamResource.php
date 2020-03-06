<?php


namespace Teams\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;
use Omeka\Entity\AbstractEntity;

/**
 *
 * @Entity
 */
class TeamResource extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="Teams\Entity\Team")
     * @JoinColumn(name="team", referencedColumnName="id")
     */
    protected $team;

    /**
     * @OneToOne(targetEntity="Omeka\Entity\Resource")
     * @JoinColumn(name="resource_id", referencedColumnName="id")
     */
    private $resource_id;


    public function getId()
    {
        return $this->id;
    }

    public function getTeam()
    {
        return $this->team;
    }

    public function getResourceId()
    {
        return $this->resource_id;
    }
}