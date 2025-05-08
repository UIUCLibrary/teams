<?php
namespace Teams\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use \Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Logger;
use Omeka\Stdlib\ErrorStore;

/**
 * Controller plugin for authorize the current user.
 */
class TeamAuth extends AbstractPlugin
{
    public $actions = ['add', 'create','delete', 'update'];
    public $domains = ['resource', 'team', 'site', 'team_user', 'role'];

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * Construct the plugin.
     *
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager, \Laminas\Log\Logger $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }


    public function isGlobAdmin(User $user): bool
    {
        return $user->getRole() === 'global_admin';
    }



    public function teamAuthorized(User $user, string $action, string $domain, int $team=0): bool
    {

        if ($action=='create'){
            $action = 'add';
        }

        //validate inputs
        if (!in_array($action, $this->actions)) {
            throw new InvalidArgumentException(
                sprintf(
                    ' "%1$s" not a valid action for teamAuthorized().',
                    $action
                )
            );
        }
        if (!in_array($domain, $this->domains)) {
            throw new InvalidArgumentException(
                sprintf(
                    '"%1$s" not a valid domain for teamAuthorized().',
                    $domain
                )
            );
        }

        if ($this->isGlobAdmin($user)) {
            return true;
        }

        $em = $this->entityManager;
        $user_id = $user->getId();
        $authorized = false;


        //see if the user has a role in the supplied team
        if ($team>0){
            $teamUser = $em->getRepository('Teams\Entity\TeamUser')
                ->findOneBy(['team' => $team, 'user'=>$user_id]);
            if ($teamUser) {
                $role = $teamUser->getRole();
            } else {
                return false;
            }
            // if no team is supplied get their current team
        } else {
            $currentTeam = $em->getRepository('Teams\Entity\TeamUser')
                ->findOneBy(['is_current' => true, 'user'=>$user_id]);
            if ($currentTeam){
                $role = $currentTeam->getRole();
            } else {
                return false;
            }
        }


            //go through each domain and determine if user is authorized for actions in that domain

            //only the global admin can create, delete or modify teams
            if ($domain == 'team' || $domain ==  'role') {
                $authorized = $this->isGlobAdmin($user);
            }

            //if they can manage users of the team (including their role)
            elseif ($domain == 'team_user') {
                $authorized = $role->getCanAddUsers();
            } elseif ($domain == 'resource') {
                if ($action == 'add') {
                    $authorized = $role->getCanAddItems();
                } elseif ($action == 'update') {
                    $authorized = $role->getCanModifyResources();
                } elseif ($action == 'delete') {
                    $authorized = $role->getCanDeleteResources();
                }
            }
//            elseif ($domain == 'site') {
//
//                //only the global admin can add and delete sites
//                if ($action == 'add' || $action == 'delete') {
//                    $authorized = $this->isGlobAdmin($user);
//                } elseif ($action == 'update') {
//                    $authorized = $role->getCanAddSitePages();
//                }
//            }
        if ($authorized) {
            return true;
        } else {
            return false;
        }
    }
}
