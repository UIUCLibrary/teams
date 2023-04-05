<?php
namespace Teams\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;


/**
 *
 *
 * @Entity
 * @Table(uniqueConstraints={@UniqueConstraint(name="active_team", columns={"is_current", "user_id"})})

 */
class TeamUser extends AbstractEntity
{
    /**
     * @var Team
     * @Id
     * @ManyToOne(
     *     targetEntity="Teams\Entity\Team",
     *     inversedBy="team_users",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $team;

    /**
     *
     * @var User
     * @Id
     * @ManyToOne(
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
     * @ManyToOne(targetEntity="TeamRole", inversedBy="team_users")
     */
    protected $role;

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $is_current = null;

//TODO make this an auto generated column
    /**
     * @Column(type="integer", unique=true)
     *
     *
     */
    protected $id;


    public function __construct( Team $team,  User $user,  TeamRole $role)
    {
        $this->team = $team;
        $this->user = $user;
        $this->role = $role;
    }

    public function getTeam()
    {
        return $this->team;
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;
    }

    public function getUser()
    {
        return $this->user;
    }


    public function getRole()
    {
        return $this->role;
    }

    public function setRole($role)
    {
        $this->role = $role;
    }

    public function __toString()
    {
        return json_encode([
            'team' => $this->getTeam()->getId(),
            'user' => $this->getUser()->getId(),
        ]);
    }

    public function getCurrent(){
        return $this->is_current;
    }

    public function setCurrent($bool){
        $this->is_current = $bool;
    }

    public function getId (){
        return $this->id;
    }

}
