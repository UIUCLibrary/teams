<?php
namespace Teams;

use Doctrine\ORM\Events;
use Doctrine\ORM\Query\Expr;
use Omeka\Api\Adapter\SiteAdapter;
use Omeka\Entity\Resource;
use Omeka\Form\ResourceTemplateForm;
use Omeka\Permissions\Acl;
use Teams\Api\Adapter\TeamAdapter;
use Teams\Controller\TestController;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamUser;
use Teams\Form\Element\AllTeamSelect;
use Teams\Form\Element\TeamSelect;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\ItemSetAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Adapter\UserAdapter;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\User;
use Omeka\Module\AbstractModule;
use Teams\Form\Element\UserSelect;
use Teams\Model\TestControllerFactory;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Interop\Container\ContainerInterface;
use Zend\Dom\Document;


class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }


    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');

        $conn->exec('
CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(240) NOT NULL, description LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_C4E0A61F5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_resource (team_id INT NOT NULL, resource_id INT NOT NULL, INDEX IDX_4D32868296CD8AE (team_id), INDEX IDX_4D3286889329D25 (resource_id), PRIMARY KEY(team_id, resource_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_resource_template (team_id INT NOT NULL, resource_template_id INT NOT NULL, INDEX IDX_75325B72296CD8AE (team_id), INDEX IDX_75325B7216131EA (resource_template_id), PRIMARY KEY(team_id, resource_template_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(240) NOT NULL, can_add_users TINYINT(1) DEFAULT NULL, can_add_items TINYINT(1) DEFAULT NULL, can_add_itemsets TINYINT(1) DEFAULT NULL, can_modify_resources TINYINT(1) DEFAULT NULL, can_delete_resources TINYINT(1) DEFAULT NULL, can_add_site_pages TINYINT(1) DEFAULT NULL, comment LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_86887E115E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_site (team_id INT NOT NULL, site_id INT NOT NULL, is_current TINYINT(1) DEFAULT NULL, INDEX IDX_B8A2FD9F296CD8AE (team_id), INDEX IDX_B8A2FD9FF6BD1646 (site_id), UNIQUE INDEX active_team (is_current, site_id), PRIMARY KEY(team_id, site_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_user (team_id INT NOT NULL, user_id INT NOT NULL, role_id INT DEFAULT NULL, is_current TINYINT(1) DEFAULT NULL, id INT NOT NULL, UNIQUE INDEX UNIQ_5C722232BF396750 (id), INDEX IDX_5C722232296CD8AE (team_id), INDEX IDX_5C722232A76ED395 (user_id), INDEX IDX_5C722232D60322AC (role_id), UNIQUE INDEX active_team (is_current, user_id), PRIMARY KEY(team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
ALTER TABLE team_resource ADD CONSTRAINT FK_4D32868296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_resource ADD CONSTRAINT FK_4D3286889329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_resource_template ADD CONSTRAINT FK_75325B72296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_resource_template ADD CONSTRAINT FK_75325B7216131EA FOREIGN KEY (resource_template_id) REFERENCES resource_template (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_site ADD CONSTRAINT FK_B8A2FD9F296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_site ADD CONSTRAINT FK_B8A2FD9FF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_user ADD CONSTRAINT FK_5C722232296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_user ADD CONSTRAINT FK_5C722232A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_user ADD CONSTRAINT FK_5C722232D60322AC FOREIGN KEY (role_id) REFERENCES team_role (id);');



    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec('DROP TABLE IF EXISTS team_user');
        $conn->exec('DROP TABLE IF EXISTS team_role');
        $conn->exec('DROP TABLE IF EXISTS team_resource');
        $conn->exec('DROP TABLE IF EXISTS team_resource_template');
        $conn->exec('DROP TABLE IF EXISTS team_site');
        $conn->exec('DROP TABLE IF EXISTS team');


    }
    protected function addAclRules()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Everybody can read groups, but not view them.
        $roles = $acl->getRoles();
        $entityRights = ['read', 'create', 'update', 'delete', 'assign'];
        $adapterRights = ['read', 'create', 'update', 'delete', 'assign'];

        $acl->allow(
            'editor',
            [

                'Teams\Controller\Index',
                'Teams\Controller\Add',
                'Teams\Controller\Update',

            ],
            [
                'browse',
                'add',
                'edit',
                'delete',
                'delete-confirm',
                'index',
                'teamAdd',
                'teamDetail',
                'teamUpdate'
            ]
        );
        $acl->allow(
            null,
            [
                Entity\Team::class,
                Entity\TeamUser::class,
                Entity\TeamResource::class,
                Entity\TeamRole::class

            ],
            $entityRights
        );
        // Deny access to the api for non admin.
        /*
        $acl->deny(
            null,
            [\Group\Api\Adapter\GroupAdapter::class],
            null
        );
        */

        // Only admin can manage groups.
        $adminRoles = [
            Acl::ROLE_RESEARCHER,
            Acl::ROLE_SITE_ADMIN,

        ];

        //added
        $viewerRoles = [
            Acl::ROLE_AUTHOR,
            Acl::ROLE_EDITOR,
            Acl::ROLE_RESEARCHER,
            Acl::ROLE_REVIEWER
        ];

        //added--this gives an author user the ability to see their own group and which groups items belong to
        $acl->allow(
            $viewerRoles,
            [Api\Adapter\TeamAdapter::class],
            ['search', 'read']
        );

//        $acl->deny(
//            Acl::ROLE_SITE_ADMIN,
//            [Controller\IndexController::class],
//            ['index']
//
//        );

        $acl->allow(
            $viewerRoles,
            [Entity\TeamResource::class],
            // The right "assign" is used to display the form or not.
            ['read', 'create', 'update', 'delete', 'assign']
        );


        $acl->allow(
            $viewerRoles,
            [Entity\Team::class],
            ['read', 'create', 'update', 'delete']
        );
        $acl->allow(
            $viewerRoles,
            [TeamUser::class, Entity\TeamResource::class],
            // The right "assign" is used to display the form or not.
            ['read', 'create', 'update', 'delete', 'assign']
        );


        $acl->allow(
            $viewerRoles,
            [Entity\Team::class],
            ['read', 'create', 'update', 'delete']
        );
        $acl->allow(
            $viewerRoles,
            [Entity\TeamUser::class, Entity\TeamResource::class],
            // The right "assign" is used to display the form or not.
            ['read', 'create', 'update', 'delete', 'assign']
        );
        $acl->allow(
            $viewerRoles,
            [Api\Adapter\TeamAdapter::class],
            ['search', 'read', 'create', 'update', 'delete']
        );
        $acl->allow(
            $viewerRoles,
            [Controller\AddController::class],
            ['show', 'browse', 'add', 'edit', 'delete', 'delete-confirm']
        );
        $acl->allow(
            $viewerRoles,
            [Controller\DeleteController::class],
            ['show', 'browse', 'add', 'edit', 'deleteRole', 'delete-confirm']
        );
        $acl->allow(
            $viewerRoles,
            [Controller\IndexController::class],
            ['show', 'browse', 'add', 'edit', 'delete', 'delete-confirm']
        );

        $acl->allow(
            $viewerRoles,
            [Controller\ItemController::class],
            ['show', 'browse', 'add', 'edit', 'delete', 'delete-confirm']
        );

        $acl->allow(
            $viewerRoles,
            [Controller\UpdateController::class],
            ['show', 'browse', 'add', 'edit', 'delete', 'delete-confirm']
        );
        $acl->allow(
            $adminRoles,
            [Controller\IndexController::class],
            ['roleIndex']
        );
        $acl->allow(
            $viewerRoles,
            [Controller\IndexController::class],
            ['roleIndex']
        );

    }


    /**
     * Add the tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event)
    {


//        $params = $event->getParam('section_nav');
//        foreach ($params as $p):
//            echo $p;
////            foreach ($p as $ar):
////                echo $ar . "<br>";
////                foreach ($ar as $what):
////                    echo gettype($what);
////                endforeach;
////            endforeach;
//        endforeach;
//        $value = null;
//
//        $values = $event->getParam('Values');
//        echo $values;
//
//        $event->setParam('Metadata', $value);


        $sectionNav = $event->getParam('section_nav');
        $sectionNav['teams'] = 'Teams'; // @translate
        $event->setParam('section_nav', $sectionNav);


    }

//    public function overloadVariable(Event $event)
//    {
//        $params = $event->getParams();
//        foreach ($params as $p):
//            foreach ($p as $ar):
//                echo $ar . "<br>";
////                foreach ($ar as $what):
////                    echo gettype($what);
////                endforeach;
//            endforeach;
//        endforeach;
//        $value = null;
//
//        $event->setParam('Metadata', $value);
//    }

    public function viewShowAfterResource(Event $event)
    {
        echo '<div id="teams" class="section">';
        $resource = $event->getTarget()->vars()->resource;
        $this->adminShowTeams($event);
        echo '</div>';
    }

    public function filterResource(Event $event)
    {
        $resource = $event->getTarget()->vars()->sites;
    }
//copied this into teamSelectorNav because for some reason the two were coming into conflict
    public function addAsset(Event $event){

        if ($this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()) {
            $view = $event->getTarget();

            echo '<style> #content {min-height: 50vh;} </style>';
            $view->headLink()->appendStylesheet($view->assetUrl('css/iopn_header.css', 'Teams'));
            echo $view->partial('teams/partial/overload-footer');
        }



    }
    public function adminShowTeams(Event $event)
    {

        $resource = $event->getTarget()->vars()->resource;

        $new_item = null;

        $resource_type = $resource->getControllerName();
        $associated_teams = $this->listTeams($resource);
//        $event->setTarget(\Omeka\Controller\Admin\ItemSetController::class);
//        $event->setParam('item', null);

        echo '<div id="teams" class="section"><p>';
            //get the partial and pass it whatever variables it needs
        echo $event->getTarget()->partial(
            'teams/partial/test',
            [
                'teams' => $associated_teams,
                'resource_type' => $resource_type
            ]);


        echo '</div>';
    }

    public function teamSelectorBrowse(Event $event)

    {
        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        $user_id = $identity->getId();
        $view =  $event->getTarget();
        $vars = $view->vars();

        if ( count($vars->resources) > 0){
            $resource_type = $vars->resources[0]->getControllerName() . 's';
        }elseif (count($vars->sites)){
            $resource_type ='sites';
        }else{
            $resource_type='nothing';
        }


        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser');
        $user_teams = $team_user->findBy(['user'=>$user_id]);
        $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true]);
        if ($current_team){
            $current_team = $current_team->getTeam()->getName();
        }else $current_team = null;

        echo $event->getTarget()->partial(
            'teams/partial/team-selector',
            ['user_teams'=>$user_teams, 'current_team' => $current_team, 'resource_type' => $resource_type]
        );
    }

    public function teamDACL(Event $event)
    {
        $resource = $event->getTarget()->item->id();
        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        $user_id = $identity->getId();

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser');
        $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true]);
        $team_id = $current_team->getTeam()->getId();
        if ($current_team){
            if($entityManager->getREpository('Teams\Entity\TeamResource')->findOneBy(['team'=>$team_id, 'resource'=>100000])){
                echo 'yes, do what you like';
            }else echo 'nopy, not here';



        }else $current_team = null;






    }
    public function teamSelectorAdvancedSearch(Event $event)

    {
        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        $user_id = $identity->getId();

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser');
        $user_teams = $team_user->findBy(['user'=>$user_id]);
        $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true]);
        if ($current_team){
            $current_team = $current_team->getTeam()->getName();
        }else $current_team = null;

        echo $event->getTarget()->partial(
            'teams/partial/team-selector-adv-search',
            ['user_teams'=>$user_teams, 'current_team' => $current_team]
        );
    }


    public function addViewAfter(Event $event)
    {
        $sectionNav = $event->getParam('sidebar');
        $event->stopPropagation(true); // @translate
//        $event->setParam('sidebar', $sectionNav);
    }
//    protected function displayViewAdmin(
//        Event $event,
//        AbstractEntityRepresentation $resource = null,
//        $listAsDiv = false
//    ) {
//        // TODO Add an acl check for right to view groups (controller level).
//        $isUser = $resource && $resource->getControllerName() === 'user';
//        $groups = $this->listGroups($resource, 'representation');
//        $partial = $listAsDiv
//            ? 'common/admin/groups-resource'
//            : 'common/admin/groups-resource-list';
//        echo $event->getTarget()->partial(
//            $partial,
//            [
//                'resource' => $resource,
//                'groups' => $groups,
//                'isUser' => $isUser,
//            ]
//        );
//    }

    protected function listUsers()
    {

    }

    protected function listTeams(AbstractEntityRepresentation $resource = null)
    {

        /*
         * TODO: this function goes through *every* resource associated with a team, needs optimized into something like a db search with WHERE
         *
         */

        //
        $result = [];

        //get all the teams
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $teams = $entityManager->getRepository('Teams\Entity\Team')->findAll();

        //for every team, get the team resources
        foreach ($teams as $team):
            $team_resources = $team->getTeamResources();

            //for each of those resources
            foreach ($team_resources as $team_resource):
                //check to see if the resource id == the resource_id passed in to the function
                if ($team_resource->getResource()->getId() == $resource->id()){
                    $result[$team->getName()] = $team;}
                endforeach;


        endforeach;
        return  $result;
    }

    public function addBrowseBefore(Event $event)
    {
        $browse_before = $event->getParam('before');
        $browse_before['team'] = 'testing';
        $event->setParam('before', $browse_before);
    }

    protected function checkAcl($resourceClass, $privilege)
{
    $acl = $this->getServiceLocator()->get('Omeka\Acl');
    $groupEntity = $resourceClass == User::class
        ? TeamUser::class
        : TeamResource::class;
    return $acl->userIsAllowed($groupEntity, $privilege);
}
//    public function displayGroupResourceForm(Event $event)
//    {
//        $operation = $event->getName();
//        if (!$this->checkAcl(Resource::class, $operation === 'view.add.form.after' ? 'create' : 'update')
//            || !$this->checkAcl(Resource::class, 'assign')
//        ) {
//            $this->viewShowAfterResource($event);
//            return;
//        }
//
//        $vars = $event->getTarget()->vars();
//        // Manage add/edit form.
//        if (isset($vars->item)) {
//            $vars->offsetSet('resource', $vars->item);
//        } elseif (isset($vars->itemSet)) {
//            $vars->offsetSet('resource', $vars->itemSet);
//        } elseif (isset($vars->media)) {
//            $vars->offsetSet('resource', $vars->media);
//        } else {
//            $vars->offsetSet('resource', null);
//            $vars->offsetSet('groups', []);
//        }
//        if ($vars->resource) {
//            $vars->offsetSet('groups', $this->listTeams($vars->resource, 'representation'));
//        }
//
//        echo $event->getTarget()->partial(
//            'common/admin/groups-resource-form'
//        );
//    }

    public function displayUserForm(Event $event)
    {

        $vars = $event->getTarget()->vars();


        $vars->offsetSet('team-members', 'test');



    }


    public function displayTeamForm(Event $event)
    {


        $vars = $event->getTarget()->vars();
        // Manage add/edit form.
        if (isset($vars->item)) {
            $vars->offsetSet('resource', $vars->item);
        } elseif (isset($vars->itemSet)) {
            $vars->offsetSet('resource', $vars->itemSet);
        } elseif (isset($vars->media)) {
            $vars->offsetSet('resource', $vars->media);
        } else {
            $vars->offsetSet('resource', null);
            $vars->offsetSet('teams', []);
        }
        if ($vars->resource) {
            $vars->offsetSet('teams', $this->listTeams($vars->resource, 'representation'));
        }
        //TODO: this is actually a js script and needs to just be added as such
        echo $event->getTarget()->partial(
            'teams/partial/team-form'
        );
    }

    public function displayTeamFormNoId(Event $event)
    {


        $vars = $event->getTarget()->vars();
        // Manage add/edit form.
        if (isset($vars->item)) {
            $vars->offsetSet('resource', $vars->item);
        } elseif (isset($vars->itemSet)) {
            $vars->offsetSet('resource', $vars->itemSet);
        } elseif (isset($vars->media)) {
            $vars->offsetSet('resource', $vars->media);
        } else {
            $vars->offsetSet('resource', null);
            $vars->offsetSet('teams', []);
        }
        if ($vars->resource) {
            $vars->offsetSet('teams', $this->listTeams($vars->resource, 'representation'));
        }
        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        $user_id = $identity->getId();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $teams = $entityManager->getRepository('Teams\Entity\TeamUser');
        if ($teams->findOneBy(['user'=>$user_id, 'is_current'=>1])){
            $default_team = $teams->findOneBy(['user'=>$user_id, 'is_current'=>1]);
            $default_team = $default_team->getTeam();
        } elseif ($teams->findBy(['user' => $user_id])){
            $default_team = $teams->findOneBy(['user' => $user_id], ['name']);
            $default_team = $default_team->getTeam();
        } else {
            $default_team = null;
        }

        echo $event->getTarget()->partial(
            'teams/partial/team-form-no-id',
            ['user_id'=>$user_id, 'default_team' => $default_team]
        );
    }


    public function advancedSearch(Event $event){
        $partials = $event->getParams()['partials'];
        $partials[] = 'teams/partial/advanced-search';
        $event->setParam('partials', $partials);



    }




    public function teamSelectorNav(Event $event)
    {
        if (!$this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()){
        $view = $event->getTarget();

        $view->headScript()->appendFile($view->assetUrl('js/team_nav_selector.js', 'Teams'));

        if (
        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity()){
        $user_id = $identity->getId();}else{$user_id=null;}

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $ct = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['is_current'=>true, 'user'=>$user_id]);
        if($ct){
            $ct = $ct->getTeam();
        } else {
            $ct = 'None';
        }
        echo $event->getTarget()->partial(
            'teams/partial/team-nav-selector',
            ['current_team' => $ct]
        );}

//        $view = $event->getTarget();
//
//        echo '<style> #content {min-height: 50vh;} </style>';
//        $view->headLink()->appendStylesheet($view->assetUrl('css/iopn_header.css', 'Teams'));
//        echo $view->partial('teams/partial/overload-footer');
    }

    //injects into AbstractEntityAdapter where queries are structured for the api
    public function filterByTeam(Event $event){

        //TODO Bug(DONE): on the advanced search class, template and search by value fail with error
        // Too many parameters: the query defines n parameters and you bound n+1
        // even if I remove the setParameter here. Because itemset and sitepool both work


        $qb = $event->getParam('queryBuilder');
        $query = $event->getParam('request')->getContent();
        $entityClass = $event->getTarget()->getEntityClass();
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');



        //TODO: if is set (search_everywhere) and ACL check passes as global admin, bypass the join

        ///If this is a case where someone is adding something and can choose which team to add it to, take that into
        /// consideration and add it to that team. Otherwise, conduct the query filtering based on the current team
        if (isset($query['team_id']) && is_int($query['team_id'])){
            $team_id = $query['team_id'];
        }else{

            //TODO this can be refactored out because it is used in basicaly the same form many palces
            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
            $identity = $this->getServiceLocator()
                ->get('Omeka\AuthenticationService')->getIdentity();
            //TODO add handeling for user not logged-in !!!this current solution would not work
            if (!$identity){
                $user_id = null;
                return;

            }else{

                $user_id = $identity->getId();
                $user_role = $entityManager->getRepository('Omeka\Entity\User')->findOneby(['id'=>$user_id])->getRole();

        }

            //for times when the admin needs to turn off the filter by teams (e.g. when adding resources to a new team)
        if (isset($query['bypass_team_filter']) && $user_role == 'global_admin'){
            return;
        }




            //there currently is not an integrity constrain that enforces one and only one is_current per user
            //so adding a test here (there can not be more than one but the db would allow 0)

            //TODO need some behavior for when a user doesn't belong to any teams. Ideally passing an error message
            $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current'=>1]);
            if (!$team_user){
                $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id]);
            }
            if ($team_user){
                $current_team = $team_user->getTeam();
                $team_id = $current_team->getId();
            }else{
                $current_team = 'None';
                $team_id = 0;
            }

        }


        if ( is_int($team_id)){
            $adapter = $event->getTarget();
            $entityAlias = $adapter->getEntityClass();

            //TODO: site really should be taking its team cue from the teams the site is associated with, not the user
            //otherwise it will not work when the public searches the site
            if ($entityClass == \Omeka\Entity\Site::class){
                //TODO get the team_id's associated with the site and then do an orWhere()/orX()
                $qb->leftJoin('Teams\Entity\TeamSite', 'ts', Expr\Join::WITH, $entityClass .'.id = ts.site')->andWhere('ts.team = :team_id')
                    ->setParameter('team_id', $team_id)
                ;
            }else{
                 $qb->leftJoin('Teams\Entity\TeamResource', 'tr', Expr\Join::WITH, $entityClass .'.id = tr.resource')->andWhere('tr.team = :team_id')
                    ->setParameter('team_id', $team_id)
         ;}
        }
    }

    //add user's teams to the user detail page view/omeka/admin/user/show.phtml
    public function userTeams(Event $event){
        $view = $event->getTarget();
        $user_id = $view->vars()->user->id();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $user_teams = $entityManager->getRepository('Teams\Entity\TeamUser')->findBy(['user'=>$user_id]);
        $team_names = array();
        foreach ($user_teams as $user_team):
            $team_names[] = $user_team->getTeam()->getName();
        endforeach;
        echo $view->partial('teams/partial/user/view', ['team_names'=> $team_names]);
    }




    public function userTeamsEdit(Event $event)
    {
        //send the form data for processing by module controller to add teamUser
        $view = $event->getTarget();
        $user_id = $view->vars()->user->id();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $user_teams = $entityManager->getRepository('Teams\Entity\TeamUser')->findBy(['user'=>$user_id]);
        $team_ids = array();
        foreach ($user_teams as $user_team):
            $team_ids[] = $user_team->getTeam()->getId();
        endforeach;
        echo $view->partial('teams/partial/user/edit', ['team_ids' => $team_ids]);
    }

    public function userFormEdit(Event $event)
    {
        $view = $event->getTarget();
        echo $view->partial('teams/partial/return_url', 'Teams');
    }


    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $services = $this->getServiceLocator();

        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'teamSelectorNav']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.after',
            [$this, 'userTeamsEdit']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.form.after',
            [$this, 'userFormEdit']
        );


        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.show.after',
            [$this, 'userTeams']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\IndexController',
            'view.layout',
            [$this, 'teamSelectorNav']
        );

//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\Item',
//            'view.show.section_nav',
//            [$this, 'teamDACL']
//        );

        $adapters = [
            ItemSetAdapter::class,
            ItemAdapter::class,
            MediaAdapter::class,
            SiteAdapter::class,
        ];
        foreach ($adapters as $adapter):

            // Add the group filter to the search.
            $sharedEventManager->attach(
                $adapter,
//                '*',
                'api.search.query',
                [$this, 'filterByTeam']
            );

        endforeach;

        $sharedEventManager->attach(
            'Teams\Controller\Add',
            'view.add.section_nav',
            [$this, 'displayUserForm']
        );
//        $sharedEventManager->attach(
////            'Omeka\Controller\Site\Index',
//            '*',
//            'view.layout',
//            [$this, 'addAsset']
//
//        );

        //Edit pages//
            //Item//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.edit.section_nav',
            [$this, 'addTab']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.edit.form.after',
            [$this, 'displayTeamForm']
        );

//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\ResourceTemplate',
//            'view.edit.form.after',
//            [$this, 'displayTeamForm']
//        );

            //ItemSet//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.edit.form.after',
            [$this, 'displayTeamForm']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.edit.section_nav',
            [$this, 'addTab']
        );
            //Media//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.edit.form.after',
            [$this, 'displayTeamForm']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.edit.section_nav',
            [$this, 'addTab']
        );
            //ResourceTemplate//
//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\ResourceTemplate',
//            'view.edit.form.after',
//            [$this, 'displayTeamForm']
//        );



        // Show pages //
            //Item//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            [$this, 'addTab']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            [$this, 'adminShowTeams']
        );
            //ItemSet//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.section_nav',
            [$this, 'addTab']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.after',
            [$this, 'adminShowTeams']
        );
            //Media//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.section_nav',
            [$this, 'addTab']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.after',
            [$this, 'adminShowTeams']
        );

        //Browse pages//
            //Item//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.browse.before',
            [$this, 'teamSelectorBrowse']
        );
            //ItemSet//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.browse.before',
            [$this, 'teamSelectorBrowse']
        );
            //Media//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.browse.before',
            [$this, 'teamSelectorBrowse']
        );
            //ResourceTemplate//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.browse.before',
            [$this, 'teamSelectorBrowse']
        );
        //Site//
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.browse.before',
            [$this, 'teamSelectorBrowse']
        );



        //Add pages//
            //ItemSet//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.add.form.after',
            [$this, 'displayTeamFormNoId']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.add.section_nav',
            [$this, 'addTab']
        );
            //Item//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.section_nav',
            [$this, 'addTab']
        );
//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\Item',
//            'view.add.after',
//            [$this, 'displayTeamFormNoId']
//        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.form.after',
            [$this, 'displayTeamFormNoId']
        );

        //Advanced Search//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.advanced_search',
            [$this, 'advancedSearch']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'teams.advanced_search',
            [$this, 'teamSelectorAdvancedSearch']
        );

        //Site Pool Item Pool//

        //Advanced Search//
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.advanced_search',
            [$this, 'advancedSearch']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'teams.advanced_search',
            [$this, 'teamSelectorAdvancedSearch']
        );




            //Sites//
//        $sharedEventManager->attach(
//            'Omeka\Controller\SiteAdmin\Index',
//            'view.add.section_nav',
//            [$this, 'addTab']
//        );
//
//        $sharedEventManager->attach(
//            'Omeka\Controller\SiteAdmin\Index',
//            'view.add.after',
//            [$this, 'displayTeamFormNoId']
//        );



        // Add the team element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'addUserFormElement']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SiteForm::class,
            'form.add_elements',
            [$this, 'addSiteFormElement']
        );



//        $sharedEventManager->attach(
//            '*',
//            'view.edit.form.after',
//            [$this, 'addResourceTemplateFormElement']
//        );



//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\User',
//            'view.edit.form.before',
//            [$this, 'addUserFormValue']
//        );









    }


    public function addUserFormElement(Event $event)
    {
        $form = $event->getTarget();
        $form->get('user-information')->add([
            'name' => 'o-module-teams:Team',
            'type' => AllTeamSelect::class,
            'options' => [
                'label' => 'Teams', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
                'id' => 'team'
            ],
        ]);
    }

    public function addSiteFormElement(Event $event){
        $form = $event->getTarget();
        $form->add([
            'name' => 'team',
            'type' => TeamSelect::class,
            'options' => [
                'label' => 'Teams', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
                'id' => 'team'
            ],
        ]);

    }
    public function addUserFormValue(Event $event)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $user = $event->getTarget()->vars()->user;
        $form = $event->getParam('form');
        $values = $this->listTeams($user, 'reference');
        $form->get('user-information')->get('o-module-teams:Team')
            ->setAttribute('value', array_keys($values));
    }

    public function teamItems($resource_type, $query, $user_id, $active = true, $team_id = null)
    {

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        if ($active){
            $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

        } else{
            $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'team' => $team_id ]);
        }

        $resources = array();
        if ($team_user) {

            $active_team_id = $team_user->getTeam()->getId();

            $team_entity = $entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $active_team_id]);


            $per_page = 10;
            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
            $tr = $team_entity->getTeamResources();
            $max_i = count($tr);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}

            $tr = $team_entity->getTeamResources();
            for ($i = $start_i; $i < $end_i; $i++) {
                $resources[] = $this->api()->read($resource_type, $tr[$i]->getResource()->getId())->getContent();
            }
        }else{$tr=null;}

        return array('page_resource'=>$resources, 'team_resources'=>$tr);



    }



}

