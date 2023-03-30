<?php
namespace Teams\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use \Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Logger;

/**
 * Controller plugin for authorize the current user.
 */
class TeamAuth extends AbstractPlugin
{
    public $actions = ['add', 'delete', 'update'];
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


    public function teamAuthorized(User $user, string $action, string $domain, int $context=0): bool
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
//        $this->logger->err(get_class($this->identity()));

        if ($this->isGlobAdmin($user)) {
            return true;
        }

        $em = $this->entityManager;
        $user_id = $user->getId();
        $authorized = false;


        //if the user has a current team
        if ($has_role = $em->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current' => true, 'user'=>$user_id])
        ) {
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
