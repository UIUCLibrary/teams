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
    public $actions = ['create','add', 'delete', 'update'];
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


    public function teamAuthorized(User $user, string $action, string $domain, int $team = 0, int $entity = 0): bool
    {
        /*
         * Query parameters:
         * $user: The user whose permissions we are evaluating. Should be a User object (Omeka\Entity\User)
         * $action: The thing they want to do, e.g. delete something. Should be among $this->actions.
         * $domain: The type of Entity they want to do the action to, e.g. a resource. Should be among $this->domain
         * $team: The team where they want to do this. Some actions aren't team depended, like making a role. Should
         * be an int.
         * $entity: The specific entity they would like to do the action too. Optional
         */
        //validate inputs
        if (!in_array($action, $this->actions)) {
            throw new InvalidArgumentException(
                sprintf(
                    ' "%1$s" not a valid action for teamAuthorized().',
                    $action
                )
            );
        }
        if ($action === 'create'){
            $action = 'add';
        }
        if (!in_array($domain, $this->domains)) {
            throw new InvalidArgumentException(
                sprintf(
                    '"%1$s" not a valid domain for teamAuthorized().',
                    $domain
                )
            );
        }

        //global admins can do anything
        if ($this->isGlobAdmin($user)) {
            return true;
        }

        $em = $this->entityManager;
        $user_id = $user->getId();
        $authorized = false;
        $role = null;


        //if the user has a current team
        if ($team !== 0) {
            $team_user = $em->getRepository('Teams\Entity\TeamUser')
                ->findOneBy(['team' => $team, 'user'=>$user_id]);
            if ($team_user){
                $role = $team_user->getRole();
            }

        } elseif ($em->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current' => true, 'user'=>$user_id])) {
            $role = $em->getRepository('Teams\Entity\TeamUser')
                ->findOneBy(['is_current' => true, 'user'=>$user_id])->getRole();
        }


        if ($role){
        //go through each domain and determine if user is authorized for actions in that domain

        //only the global admin can create, delete or modify teams
            if ($domain == 'team' || $domain ==  'role') {
                $authorized = false;
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
            } elseif ($domain == 'site') {
                $authorized = $role->getCanAddSitePages();
            }
        }
        return $authorized;
    }

    public function canEditTeamEntity($teamEntity, User $user)
    {
        //Determines if a given user has the authority to edit a given Team Entity (like a resource, asset, or template)
        $authorized = false;
        $entityClass = $teamEntity->getEntityClass();

        $em = $this->entityManager;
        $teams = [];
        $entity_teams = $em->getRepository($entityClass)->findBy([$teamEntity->getMappedEntityName()]);
        foreach ($entity_teams as $entity_team){
            $teams[] = $entity_team->getTeam()->getId();
        }
        $user_roles = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $user->getId()]);

        foreach ($user_roles as $user_role) {
            if (in_array($user_role->getTeam()->getId(), $teams) && $user_role->getRole()->getCanModifyResources()){
                $authorized = true;

            }
        }
        return $authorized;

    }
}
