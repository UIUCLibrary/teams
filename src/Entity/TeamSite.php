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
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Site;
use Omeka\Entity\User;


/**
 *
 *
 * @Entity
 * @Table(uniqueConstraints={@UniqueConstraint(name="active_team", columns={"is_current", "site_id"})})
 */
class TeamSite extends AbstractEntity
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

    /**
     * @Column(type="boolean", nullable=true)
     */
    protected $is_current = null;


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

    public function getCurrent(){
        return $this->is_current;
    }

    public function setCurrent($bool){
        $this->is_current = $bool;
    }

    public function __toString()
    {
        return json_encode([
            'team' => $this->getTeam()->getId(),
            'site' => $this->getSite()->getId(),
        ]);
    }


    /**
     * @inheritDoc
     */
    public function getId()
    {
        return [
            'team' => $this->getTeam()->getId(),
            'site' => $this->getSite()->getId()
        ];
    }
}
