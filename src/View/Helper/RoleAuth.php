<?php


namespace Teams\View\Helper;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Laminas\View\Helper\AbstractHelper;

class RoleAuth extends AbstractHelper
{
    public $actions = ['add', 'delete', 'update'];
    public $domains = ['resource', 'team', 'site', 'team_user', 'role'];
    /**
     * @var EntityManager
     */
    protected $entityManger;


    public function __construct(EntityManager $entityManager)
    {
        $this->entityManger = $entityManager;
    }

    public function user()
    {
        return $this->getView()->identity();
    }

    public function isGlobAdmin()
    {
        return $this->user()->getRole() === 'global_admin';
    }

    public function isSuper()
    {
        return ($this->isGlobAdmin() && $this->user()->getId() === 1);
    }

    public function teamAuthorized(string $action, string $domain, int $context=0)
    {
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

        //super admin should bypass team authority
        if ($this->isSuper()) {
            return true;
        }

        $em = $this->entityManger;
        $user_id = $this->user()->getId();
        $authorized = false;


        if ($context > 0) {
            //determine if the user is part of that team
            $has_role = $em->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['user' => $user_id, 'team'=>$context]);

        } else {
            //get the users current team
            $has_role = $em->getRepository('Teams\Entity\TeamUser')
                ->findOneBy(['is_current' => true, 'user'=>$user_id]);
        }



        //if the user has a current team
        if ($has_role) {
            $current_role = $has_role->getRole();

            //go through each domain and determine if user is authorized for actions in that domain


            //only the global admin can create, delete or modify teams
            if ($domain == 'team' || $domain ==  'role') {
                $authorized = $this->isGlobAdmin();

            }

            //if they can manage users of the team (including their role)
            elseif ($domain == 'team_user') {
                $authorized = $current_role->getCanAddUsers();
            } elseif ($domain == 'resource') {
                if ($action == 'add') {
                    $authorized = $current_role->getCanAddItems();
                } elseif ($action == 'update') {
                    $authorized = $current_role->getCanModifyResources();
                } elseif ($action == 'delete') {
                    $authorized = $current_role->getCanDeleteResources();
                }
            } elseif ($domain == 'site') {

                //only the global admin can add and delete sites
                if ($action == 'add' || $action == 'delete') {
                    $authorized = $this->isGlobAdmin();
                } elseif ($action == 'update') {
                    $authorized = $current_role->getCanAddSitePages();
                }
            }
        }
        return $authorized;
    }
}
