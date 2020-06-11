<?php


namespace Teams\View\Helper;


use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Zend\View\Helper\AbstractHelper;

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
        return $this->user()->getRole() == 'global_admin';
    }

    public function teamAuthorized(string $action, string $domain)
    {
        //validate inputs
        if (!in_array($action, $this->actions)){
            throw new InvalidArgumentException(
                sprintf(' "%1$s" not a valid action for teamAuthorized().',
                    $action)
            );
        }
        if (!in_array($domain, $this->domains)){
            throw new InvalidArgumentException(
                sprintf('"%1$s" not a valid domain for teamAuthorized().',
                    $domain)
            );
        }

        $em = $this->entityManger;
        $user_id = $this->user()->getId();
        $authorized = false;


        //if the user has a current team
        if ($current_role = $em->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current' => true, 'user'=>$user_id])
            ->getRole()) {

            //go through each domain and determine if user is authorized for actions in that domain

            //only the global admin can create, delete or modify teams
            if ($domain == 'team' || 'role'){
                    $authorized = $this->isGlobAdmin();
            }

            //if they can manage users of the team (including their role)
            elseif ($domain == 'team_user'){
                $authorized = $current_role->getCanAddUsers();
            }

            elseif ($domain == 'resource'){
                if ($action == 'add'){
                    $authorized = $current_role->getCanAddItems();
                }
                elseif ($action == 'update'){
                    $authorized = $current_role->getCanModifyResources();
                }
                elseif ($action == 'delete'){
                    $authorized = $current_role->getCanDeleteResources();
                }
            }

            elseif ($domain == 'site'){

                //only the global admin can add and delete sites
                if ($action == 'add' || $action == 'delete'){
                    $authorized = $this->isGlobAdmin();
                }
                elseif ($action == 'update'){
                    $authorized = $current_role->getCanAddSitePages();
                }
            }
        }
        return $authorized;
    }


}