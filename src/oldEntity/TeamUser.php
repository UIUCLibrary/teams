<?php
namespace Teams\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;
use Zend\Form\Annotation\Name;

/**
 *
 * @Entity
 */
class TeamUser extends AbstractEntity
{
    /**
     * @var int
     * @Id
     * @Column(type="integer")
     *@GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="Team")
     * @JoinColumn(name="team_id", referencedColumnName="id")
     */
    protected $team_id;

    /**
     * @var User
     * @ManyToOne(
     *     targetEntity="TeamUserId"
     *
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $user_id;

    /**
     * @ManyToOne(targetEntity="TeamRole")
     * @JoinColumn(name="team_user_role", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    protected $role;

    public function getId()
    {
        return $this->id;
    }

    public function getTeamId()
    {
        return $this->team_id;
    }

    public function setTeamId($team_id)
    {
        $this->team_id = $team_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setRole($role)
    {
        $this->role = $role;
    }

}

