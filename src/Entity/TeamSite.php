<?php
namespace Teams\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Omeka\Entity\Site;
use Omeka\Entity\User;


/**
 *
 *
 * @Entity
 * @Table(uniqueConstraints={@UniqueConstraint(name="active_team", columns={"is_current", "user_id"})})

 */
class TeamSite
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
     * @var Site
     * @Id
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Site",
     *     cascade={"persist"}
     * )
     * @JoinColumn(
     *     onDelete="cascade",
     *     nullable=false
     * )
     */
    protected $site;


    public function __construct( Team $team,  Site $site)
    {
        $this->team = $team;
        $this->site = $site;
    }

    public function getTeam()
    {
        return $this->team;
    }

    public function getSite()
    {
        return $this->site;
    }

    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;
    }


    public function __toString()
    {
        return json_encode([
            'team' => $this->getTeam()->getId(),
            'user' => $this->getUser()->getId(),
        ]);
    }


}
