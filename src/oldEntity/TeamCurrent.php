<?php


namespace Teams\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;
use Zend\Form\Annotation\Name;

/**
 *
 * @Entity
 */
class TeamCurrent extends AbstractEntity
{

    /**
     * @var User
     * @Id
     * @OneToOne(
     *     targetEntity="Omeka\Entity\User",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $user;

    /**
     * @var Team
     * @Id
     * @ManyToOne(
     *     targetEntity="Teams\Entity\Team",
     *     inversedBy="activeUsers"
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $current_team_id;


    public function getId()
    {
        return $this->user;
    }

    public function setCurrentTeam($id)
    {
        $this->current_team_id = $id;
    }

    public function getCurrentTeam()
    {
        return $this->current_team_id;
    }

    public function setUser($id)
    {
        $this->user = $id;
    }
}