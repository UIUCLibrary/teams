<?php
namespace Teams;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Exception;
use Doctrine\ORM\Query\Expr;
use Omeka\Api\Adapter\ResourceTemplateAdapter;
use Omeka\Api\Adapter\SiteAdapter;
use Omeka\Entity\EntityInterface;
use Omeka\Permissions\Acl;
use Teams\Entity\Team;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamUser;
use Teams\Form\Element\AllTeamSelect;
use Teams\Form\Element\TeamSelect;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\ItemSetAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Entity\User;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;

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
CREATE TABLE team_user (team_id INT NOT NULL, user_id INT NOT NULL, role_id INT DEFAULT NULL, is_current TINYINT(1) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_5C722232BF396750 (id), INDEX IDX_5C722232296CD8AE (team_id), INDEX IDX_5C722232A76ED395 (user_id), INDEX IDX_5C722232D60322AC (role_id), UNIQUE INDEX active_team (is_current, user_id), PRIMARY KEY(team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
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

    public function createNamedParameter(QueryBuilder $qb, $value,
                                         $prefix = 'omeka_'
    ) {
        $index = 0;
        $placeholder = $prefix . $index;
        $index++;
        $qb->setParameter($placeholder, $value);
        return ":$placeholder";
    }

    protected function addAclRules()
    {

        /*
         *
         */
        //db get the roles table
        //for each role, make an acl
        //    public function addRoleLabel($roleId, $roleLabel)
        //    {
        //        $this->roleLabels[$roleId] = $roleLabel;
        //    }
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Everybody can read groups, but not view them.
        $roles = $acl->getRoles();
        //entity rights are the actions of controllers
        $entityRights = ['read', 'create', 'update', 'delete', 'assign'];
        $adapterRights = ['read', 'create', 'update', 'delete', 'assign'];

        //allow everyone to see their teams
        $acl->allow(
            $roles,
            'Teams\Controller\Index',
            ['index']

        );

        //allow everyone to change their current team
        $acl->allow(
            $roles,
            'Teams\Controller\Update',
            ['currentTeam']

        );

        $acl->allow(
            'global_admin',
            [
                'Teams\Controller\Index',
                'Teams\Controller\Add',
                'Teams\Controller\Update',
            ],
            [

                'index',
                'teamAdd',
                'teamDetail',
                'teamUpdate',

            ]
        );
        $acl->allow(
            'global_admin',
            Entity\TeamRole::class,
            $entityRights
        );
        $acl->allow(
            null,
            [
                Entity\Team::class,
                Entity\TeamUser::class,
                Entity\TeamResource::class,

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

    public function removeTab(Event $event)
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['item-pool'] = 'Do Not Use'; // @translate
        unset($sectionNav['item-pool']);

        //adding a tab that says Site Pool to add explanation to users who are used to the feature
        $sectionNav['team'] = 'Site Pool'; // @translate

        $event->setParam('section_nav', $sectionNav);

    }

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

    //content under the Teams tab for resources
    public function adminShowTeams(Event $event)
    {

        $resource = $event->getTarget()->vars()->resource;

        $new_item = null;

        $resource_type = $resource->getControllerName();
        $associated_teams = $this->listTeams($resource);


        echo '<div id="teams" class="section"><p>';
            //get the partial and pass it whatever variables it needs
        echo $event->getTarget()->partial(
            'teams/partial/resource-show-teams',
            [
                'teams' => $associated_teams,
                'resource_type' => $resource_type
            ]);


        echo '</div>';
    }

    public function teamSelectorBrowse(Event $event)

    {
        $identity = $this->getUser();
        $user_id = $identity->getId();
        $view =  $event->getTarget();
        $vars = $view->vars();

        if (is_array($vars->resources) && count($vars->resources) > 0){
            $resource_type = $vars->resources[0]->getControllerName() . 's';
        }elseif (is_array($vars->sites) && count($vars->sites)){
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
        } elseif ($user_teams){
            $current_team = $team_user->findOneBy(['user'=>$user_id]);
            $current_team->setCurrent(true);
            $entityManager->flush();
            $current_team = $current_team->getTeam()->getName();
        }else $current_team = null;

        echo $event->getTarget()->partial(
            'teams/partial/team-selector',
            ['user_teams'=>$user_teams, 'current_team' => $current_team, 'resource_type' => $resource_type]
        );
    }

//    public function teamDACL(Event $event)
//    {
//        $resource = $event->getTarget()->item->id();
//        $identity = $this->getUser();
//        $user_id = $identity->getId();
//
//        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
//        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser');
//        $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true]);
//        $team_id = $current_team->getTeam()->getId();
//        if ($current_team){
//            if($entityManager->getREpository('Teams\Entity\TeamResource')->findOneBy(['team'=>$team_id, 'resource'=>100000])){
//                echo 'yes, do what you like';
//            }else echo 'nopy, not here';
//
//
//
//        }else $current_team = null;
//
//
//
//
//
//
//    }
    public function teamSelectorAdvancedSearch(Event $event)

    {
        $identity = $this->getUser();
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

    protected function listTeams(AbstractEntityRepresentation $resource = null)
    {

        /*
         * TODO: this function goes through *every* resource associated with a team, needs optimized into something like a db search with WHERE
         *
         */

        //
//        $messanger = new Messenger();
//        $messanger->addError('this is an error');
//        $messanger->addWarning('THIS is an warning');
//        $messanger->addNotice("you got this");
//        $messanger->addSuccess('yup');
        $result = [];

        //get all the teams
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
//        $teams = $entityManager->getRepository('Teams\Entity\Team')->findAll();
//
//        //for every team, get the team resources
//        foreach ($teams as $team):
//            $team_resources = $team->getTeamResources();
//
//            //for each of those resources
//            foreach ($team_resources as $team_resource):
//                //check to see if the resource id == the resource_id passed in to the function
//                if ($team_resource->getResource()->getId() == $resource->id()){
//                    $result[$team->getName()] = $team;}
//                endforeach;
//        endforeach;
        $team_resource = $entityManager->getRepository('Teams\Entity\TeamResource')
            ->findBy(['resource'=>$resource->id()]);

        foreach ($team_resource as $tr):
            $result[$tr->getTeam()->getName()] = $tr->getTeam();
        endforeach;

        return  $result;
    }

//    public function addBrowseBefore(Event $event)
//    {
//        $browse_before = $event->getParam('before');
//        $browse_before['team'] = 'testing';
//        $event->setParam('before', $browse_before);
//    }

    protected function checkAcl($resourceClass, $privilege)
{
    $acl = $this->getServiceLocator()->get('Omeka\Acl');
    $groupEntity = $resourceClass == User::class
        ? TeamUser::class
        : TeamResource::class;
    return $acl->userIsAllowed($groupEntity, $privilege);
}


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
        $identity = $this->getUser();
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

    public function displaySitePoolMsg(Event $event)
    {
       echo '
<p class="section" id="team">Site Pools superseded by the Teams Module. This site will have access to all associated Team Resources.</p>
';

    }


    public function advancedSearch(Event $event){
        $partials = $event->getParams()['partials'];
        $partials[] = 'teams/partial/advanced-search';
        $event->setParam('partials', $partials);



    }



//need to use the currentTeam() function
    public function teamSelectorNav(Event $event)
    {
        if (!$this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()){
            $view = $event->getTarget();
            $view->headScript()->appendFile($view->assetUrl('js/team_nav_selector.js', 'Teams'));
            if ($identity = $this->getUser()) {
                $user_id = $identity->getId();
            }else{$user_id = null;}
            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
            $tu = $entityManager->getRepository('Teams\Entity\TeamUser');
            $ct = $tu->findOneBy(['is_current'=>true, 'user'=>$user_id]);

            if($ct){
            $ct = $ct->getTeam();
//        } elseif ($tu->findOneBy(['user'=>$user_id])){
//            $tu = $tu->findOneBy(['user'=>$user_id]);
//            $ct = $tu->getTeam();
//            $tu->setCurrent(1);
//            $entityManager->flush();

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



    public function currentTeam()
    {
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $identity =$this->getUser();
        //TODO add handeling for user not logged-in !!!this current solution would not work
        if (!$identity){
            $user_id = null;
            return null;

        }else{

            $user_id = $identity->getId();
        }

        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current'=>1]);
        if (!$team_user){
            $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id]);

            if ($team_user){

                $team_user->setCurrent('1');
                $entityManager->merge($team_user);
                $entityManager->flush();
            }
            else{

                return null;}
            ;

        }
        if ($team_user){
            $current_team = $team_user->getTeam();
            $team_id = $current_team->getId();
        }else{
            $current_team = 'None';
            $team_id = 0;
        }
        return $current_team;
    }


    public function getTeamContext($query)
    {
//        foreach ($query as $key => $value):
//            echo $key . ': ' . $value . '<br>';
//        endforeach;

        //if the query explicitly asks for a team, that trumps all
        if (isset($query['team_id'])){
            $team_id = $query['team_id'];
        }

        //Logged-in or not, if it is a public site use the TeamSite
        elseif ($this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()){
            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
            $team_id = $entityManager->getRepository('Teams\Entity\TeamSite')
                ->findOneBy(['site' => $query['site_id']])
                ->getTeam()->getId();
        }

        elseif ($this->getUser() != null && $this->currentTeam() != null) {

                $team_id = $this->currentTeam()->getId();


            }



        else{
            $team_id = 0;
        }
        return $team_id;

    }
    public function filterByTeam(Event $event){

        //TODO Bug(DONE): on the advanced search class, template and search by value fail with error
        // Too many parameters: the query defines n parameters and you bound n+1
        // even if I remove the setParameter here. Because itemset and sitepool both work

        $qb = $event->getParam('queryBuilder');
        $query = $event->getParam('request')->getContent();
        $entityClass = $event->getTarget()->getEntityClass();
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $alias = 'omeka_root';


        //TODO: if is set (search_everywhere) and ACL check passes as global admin, bypass the join
        //for times when the admin needs to turn off the filter by teams (e.g. when adding resources to a new team)
        if (isset($query['bypass_team_filter']) && $this->getUser()->getRole() == 'global_admin') {
            return;
        }
        ///If this is a case where someone is adding something and can choose which team to add it to, take that into
        /// consideration and add it to that team. Otherwise, conduct the query filtering based on the current team

        $team_id = $this->getTeamContext($query);
        if ( is_int($team_id)){

            //TODO (Done): site really should be taking its team cue from the teams the site is associated with, not the user
            //otherwise it will not work when the public searches the site
            if ($entityClass == \Omeka\Entity\Site::class){
                if (! $this->getUser()){
                    return ;
                }else{
                    $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
                    $team_name = $entityManager->getRepository('Teams\Entity\Team')
                        ->findOneBy(['id'=> $team_id])
                        ->getName();
                    echo
                    <<<EOF
<script>
    window.addEventListener("load", function () {
    $(".site-list-heading").text("Sites for Team: '$team_name'")
        }
    );
</script>
EOF;

                    //TODO get the team_id's associated with the site and then do an orWhere()/orX()
                    $qb->leftJoin('Teams\Entity\TeamSite', 'ts', Expr\Join::WITH, $alias .'.id = ts.site')->andWhere('ts.team = :team_id')
                        ->setParameter('team_id', $team_id)
                ;}
            }elseif ($entityClass == \Omeka\Entity\ResourceTemplate::class){
                 $qb->leftJoin('Teams\Entity\TeamResourceTemplate', 'trt', Expr\Join::WITH, $alias .'.id = trt.resource_template')->andWhere('trt.team = :team_id')
                    ->setParameter('team_id', $team_id)
         ;
                 //
            }elseif ($entityClass == \Omeka\Entity\User::class){

                return;
            }elseif ($entityClass == \Omeka\Entity\Vocabulary::class){
                return;
            }
            else{

                $qb->leftJoin('Teams\Entity\TeamResource', 'tr', Expr\Join::WITH, $alias .'.id = tr.resource')->andWhere('tr.team = :team_id')
                    ->setParameter('team_id', $team_id)
                ;
            }
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
        echo $view->partial('teams/partial/user/edit', ['user_teams' => $user_teams, 'team_ids' => $team_ids]);
    }

    public function itemSetCreate(Event $event){
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');


        if ($operation == 'create'){

        $response = $event->getParam('response');

        $resource =  $response->getContent();

        $teams = $request->getContent()['team'];

        foreach ($teams as $team_id):
            $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
            $tr = new TeamResource($team, $resource);
            $em->persist($tr);
        endforeach;
        $em->flush();

        }


    }

    public function itemSetUpdate(Event $event)
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $error_store = $event->getParam('errorStore');

        if ($operation == 'update'){

            $resource_id = $request->getId();
            $teams = $request->getContent()['team'];

            //remove team resources for id
            $remove_tr  = $em->getRepository('Teams\Entity\TeamResource')->findBy(['resource' => $resource_id]);
            foreach ($remove_tr as $tr):
                $em->remove($tr);
            endforeach;
            $em->flush();

            //add team resources for id
            $resource = $em->getRepository('Omeka\Entity\Resource')->findOneBy(['id'=>$resource_id]);
            foreach ($teams as $team_id):
                $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
                $add_tr = new TeamResource($team, $resource);
                $em->persist($add_tr);
            endforeach;
            $em->flush();

            //add and return errors
//            $validationException = new Exception\ValidationException;
//            $validationException->setErrorStore($error_store);
//            throw $validationException;
        }
    }

    public function itemUpdate(Event $event){
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $error_store = $event->getParam('errorStore');

        if ($operation == 'update' ){
            $resource_id = $request->getId();

            if (array_key_exists('team', $request->getContent())){

                //array of team ids
                $teams = $request->getContent()['team'];

                //array of media ids
                $media_ids = [];
                foreach ($entity->getMedia() as $media):
                    $media_ids[] = $media->getId();
                endforeach;

                //resource represented by the item in the resource table
                $resource = $em->getRepository('Omeka\Entity\Resource')
                    ->findOneBy(['id' => $resource_id]);

                foreach ($teams as $team_id):
                    $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                    if (! $em->getRepository('Teams\Entity\TeamResource')
                        ->findOneBy(['team'=>$team_id, 'resource'=>$resource_id])){
                        $tr = new TeamResource($team, $resource);
                        $em->persist($tr);}


                    foreach ($media_ids as $media_id):
                        if (! $em->getRepository('Teams\Entity\TeamResource')
                            ->findOneBy(['team'=>$team_id, 'resource'=>$media_id])){
                        $m = $em->getRepository('Omeka\Entity\Resource')->findOneBy(['id'=>$media_id]);
                        $mtr = new TeamResource($team, $m);
                        $em->persist($mtr);}
                    endforeach;
                endforeach;
                $em->flush();
            }
        }

    }

    public function itemCreate(Event $event)
    {

        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');


        if ($operation == 'create'){

            $response = $event->getParam('response');

            $resource =  $response->getContent();
            $media = $resource->getMedia();

            if (array_key_exists('team', $request->getContent())){
                $teams = $request->getContent()['team'];

                //add items to team
                foreach ($teams as $team_id):
                    $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
                    $tr = new TeamResource($team, $resource);
                    $em->persist($tr);

                    //if there is media, at those to the team as well
                    if (count($media) > 0) {
                        foreach ($media as $m):
                            $tr = new TeamResource($team, $m);
                            $em->persist($tr);
                        endforeach;}

                endforeach;
                $em->flush();
            }
        }
    }





    public function resourceTemplateTeamsEdit(Event $event)
    {
        $view = $event->getTarget();
        $rt_id = $view->vars()->resourceTemplate->id();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $rt_teams = $entityManager->getRepository('Teams\Entity\TeamResourceTemplate')->findBy(['resource_template'=>$rt_id]);

        $team_ids = array();
        foreach ($rt_teams as $rt_team):
            $team_ids[] = $rt_team->getTeam()->getId();
        endforeach;
        echo $view->partial('teams/partial/resource-template/edit', ['rt_teams' => $rt_teams, 'team_ids' => $team_ids]);
    }

    public function resourceTemplateTeamsAdd(Event $event)
    {


        $view =  $event->getTarget();

        $team_id = $this->currentTeam()->getId();

        echo $view->partial('teams/partial/resource-template/add', ['team_id' => $team_id]);




    }

    public function userFormEdit(Event $event)
    {
        $view = $event->getTarget();
        echo $view->partial('teams/partial/return_url', 'Teams');
    }

    public function siteFormAdd(Event $event)
    {
        $team_id = $this->currentTeam()->getId();
        $view = $event->getTarget();
        echo $view->partial('teams/partial/site-admin/add.phtml', ['team_ids'=>$team_id]);
    }



    public function siteEdit(Event $event)
    {
        $view = $event->getTarget();

        //send the form data for processing by module controller to add teamUser
        $site_id = $view->vars()->site->id();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $site_teams = $entityManager->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $team_ids = array();
        foreach ($site_teams as $site_team):
            $team_ids[] = $site_team->getTeam()->getId();
        endforeach;
        echo $view->partial('teams/partial/site-admin/edit', ['site_teams' => $site_teams, 'team_ids' => $team_ids]);



    }

    //if process is looking at a doctrine proxy, eg for a lot of Resource template processes, test against the proxied class
    public  function getResourceClass($resource){
        $doctrine_ent = 'DoctrineProxies\__CG__';
        $doctrine_test = strpos(get_class($resource), $doctrine_ent);
        if ($doctrine_test === false){
            $res_class = get_class($resource);
        }else{
            $res_class = substr(get_class($resource), strlen($doctrine_ent)+1);
        }
        return $res_class;
    }

    public function inTeam($resource, $team_user)
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $resource_domains = ['Omeka\Entity\Item', 'Omeka\Entity\ItemSet', 'Omeka\Entity\Media'];
        $fk_id = $resource->getId();
        $team = $team_user->getTeam();
        $user = $team_user->getUser();
        $res_class = $this->getResourceClass($resource);

        if (in_array($res_class, $resource_domains )){
            $teamsRepo = 'Teams\Entity\TeamResource';
            $fk = 'resource';
            $criteria = ['team'=>$team->getId(), $fk =>$fk_id];
        }
        elseif ($res_class == 'Teams\Entity\TeamResource'){
            $teamsRepo = 'Teams\Entity\TeamResource';
            $fk = 'resource';
            $fk_id = $resource->getResource()->getId();
            $criteria = ['team'=>$team->getId(), $fk =>$fk_id];
        }
        elseif ($res_class == 'Omeka\Entity\Site'){
            $teamsRepo = 'Teams\Entity\TeamSite';
            $fk = 'site';
            $criteria = ['team'=>$team->getId(), $fk =>$fk_id];
        }
        elseif ($res_class == 'Omeka\Entity\SitePage'){
            $teamsRepo = 'Teams\Entity\TeamSite';
            $fk = 'site';
            $fk_id = $resource->getSite()->getId();
            $criteria = ['team'=>$team->getId(), $fk =>$fk_id];


        }
        elseif ($res_class == 'Omeka\Entity\ResourceTemplate'){
            $teamsRepo = 'Teams\Entity\TeamResourceTemplate';
            $fk = 'resource_template';
            $criteria = ['team'=>$team->getId(), $fk =>$fk_id];
        }
        elseif ($res_class == 'Teams\Entity\Team' ){
            $teamsRepo = 'Teams\Entity\TeamUser';
            $fk = 'user';
            $criteria = ['team'=>$team->getId(), $fk =>$user->getId()];        }
        elseif ($res_class == 'Omeka\Entity\User'){

            return true;
        }
        elseif ($res_class == 'Teams\Entity\TeamRole'){

            return true;
        }
        elseif ($res_class == 'Omeka\Entity\Job'){
            return true;
        }
        elseif ($res_class == 'Omeka\Entity\Property'){
            return true;
        }
        else{
            throw new Exception\PermissionDeniedException(sprintf(
//                $this->getTranslator()->translate(
                'Case not yet handled. The developer of Teams has not yet explicitly handled this resource, 
                    so by default action here is not permitted. Resource "%1$s: %2$s" .'

//                )
                ,
                $res_class, $resource->getId()
            ));

        }

        if ($team_resource = $em->getRepository($teamsRepo)
            ->findOneBy($criteria)){
            $in_team = true;
        }
        else{
            $in_team = false;
        }

        return $in_team;

    }

    public function getUser(){
        if ($this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity()){
            return $this->getServiceLocator()
                ->get('Omeka\AuthenticationService')->getIdentity();

        };
    }

    //only working for read, update, add
    public function teamAuthority(EntityInterface $resource, $action, Event $event){
        $user = $this->getUser();

        if (!$user){

            return true;
        }

        //if it is the 'super' global admin, bypass any team controls
        if ($user->getId() === 1 && $user->getRole() === 'global_admin'){
            return true;
        }
        $messenger = new Messenger();

        $authorized = false;

        $res_class = $this->getResourceClass($resource);

        //case that I don't fully understand. When selecting resource template on new item form
        //the Omeka\AuthenticationService->getIdentity() returns null

        //should be by far the most common case
        //if it isn't on the backend, let the public vs private rules take over
        if ($this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()) {
            return true;
        }
        //actually need this even less restrictive just so that public can see frontend
        if ($user == null && $action == 'read'){
            return true;
        }


        $is_glob_admin = ($user->getRole() == 'global_admin');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        $user_id = $user->getId();
        $team_user = $em->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current'=>true, 'user'=>$user_id]);
        $team = $team_user->getTeam();
        $team_user_role = $team_user->getRole() ;

        //don't check if in same team if a create action
        if ($action != 'create'){
            //if resource not part of user's current team, no action at all
            if (! $this->inTeam($resource, $team_user)){
                $authorized = false;
                $err = sprintf(
//                $this->getTranslator()->translate(
                    'Permission denied. Resource "%1$s: %2$s" is not part of your current team, %3$s.
                    If you feel this is an error, try changing teams or talk to the administrator.
                    Action: %4$s
                    '
//                )
                    ,
                    get_class($resource), $resource->getId(), $team->getName(), $action
                );
                $messenger->addError($err);
                throw new Exception\PermissionDeniedException($err

                );
            }
        }

        $resource_domains = [
            'Omeka\Entity\Item',
            'Omeka\Entity\ItemSet',
            'Omeka\Entity\Media',
            'Omeka\Entity\ResourceTemplate',
            'Teams\Entity\TeamResource'
            ];

        if (in_array($res_class, $resource_domains )){
            if ($action == 'create'){
                $authorized = $team_user_role->getCanAddItems();
            }
            elseif ($action == 'delete' || $action == 'batch_delete'){
                $authorized = $team_user_role->getCanDeleteResources();
            }
            elseif ($action == 'update'){
                $authorized = $team_user_role->getCanModifyResources();

            }
            elseif ($action == 'read'){
                $authorized = true;

            }

        }
        elseif ($res_class == 'Omeka\Entity\Site'){
            if ($action == 'create'){
                $authorized = $is_glob_admin;

            }
            elseif ($action == 'delete' || $action == 'batch_delete'){
                $authorized = $is_glob_admin;

            }
            elseif ($action == 'update'){
                $authorized = $team_user_role->getCanAddSitePages();

            }
            elseif ($action == 'read'){
                $authorized = true;

            }


        }
        elseif ($res_class == 'Omeka\Entity\SitePage'){
            if ($action == 'create'){
                $authorized = $team_user_role->getCanAddSitePages();

            }
            elseif ($action == 'delete' || $action == 'batch_delete'){
                $authorized = $team_user_role->getCanAddSitePages();

            }
            elseif ($action == 'update'){
                $authorized = $team_user_role->getCanAddSitePages();

            }
            elseif ($action == 'read'){
                $authorized = true;

            }


        }
        elseif ($res_class == 'Teams\Entity\Team' ){
            if ($action == 'create'){
                $authorized = $is_glob_admin;

            }
            elseif ($action == 'delete' || $action == 'batch_delete'){
                $authorized = $is_glob_admin;

            }
            elseif ($action == 'update'){
                $authorized = $team_user_role->getCanAddUsers();

            }
            elseif ($action == 'read'){
                $authorized = true;

            }
        }
        elseif ($res_class == 'Omeka\Entity\User'){
            return true;
        }
        elseif ($res_class == 'Omeka\Entity\Job'){
            return true;
        }
        elseif ($res_class == 'Omeka\Entity\Property'){
            return true;
        }


        elseif ($res_class == 'Teams\Entity\TeamRole'){
            $authorized = $is_glob_admin;
        }

        if (!$authorized){

            $authorized = false;
            $msg = sprintf(
//                    $this->getTranslator()->translate(
                'Permission denied. Your role in %5$s, %4$s, does not permit you to %3$s this resource.'

//                    )
                ,
                get_class($resource), $resource->getId(), $action, $team_user_role->getName(), $team->getName()
            );
            $diagnostic = sprintf(
//                    $this->getTranslator()->translate(
                'Diagnostic:  --  Resource type: %1$s. Resource id: %2$s. Action: %3$s. Your role: %4$s'

//                    )
                ,
                get_class($resource), $resource->getId(), $action, $team_user_role->getName(), $team->getName()
            );

            $messenger->addError($msg);
            $messenger->addError($diagnostic);
//            $str = <<<EOD
//                <script>
//                    window.addEventListener("load", function() {
//                      let msg = "$msg";
//                      let content = document.getElementById("content");
//                      let ul = document.createElement("ul")
//                      ul.className = "messages";
//                      let li = document.createElement("li");
//                      li.className = "error";
//                      li.innerText = msg;
//                      ul.appendChild(li);
//                      content.prepend(ul);
//                      })
//                      </script>
//EOD;
//            echo $str;



            throw new Exception\PermissionDeniedException($msg);

        }
        return $authorized;

    }

    public function teamAuthorizeOnRead(Event $event){
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $this->teamAuthority($entity, $operation, $event);

    }

    public function teamAuthorizeOnHydrate(Event $event){
        $request = $event->getParam('request');
        $entity = $event->getParam('entity');
        $operation = $request->getOperation();
        $this->teamAuthority($entity, $operation, $event);


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
            'Omeka\Controller\SiteAdmin\Index',
            'view.add.before',
            [$this, 'siteFormAdd']
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
            ResourceTemplateAdapter::class,

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
            '*',
            'api.find.post',
            [$this, 'teamAuthorizeOnRead']
        );

        //on api calls, make sure the users has the team authority to do action as well.
        $sharedEventManager->attach(
            '*',
            'api.hydrate.pre',
            [$this, 'teamAuthorizeOnHydrate']
        );

        $sharedEventManager->attach(
            ItemSetAdapter::class,
            'api.execute.post',
            [$this, 'itemSetCreate']
        );

        $sharedEventManager->attach(
            ItemAdapter::class,
            'api.execute.post',
            [$this, 'itemCreate']
        );

        //only make changes after checking team authority . . . should these two be combined?
        //seems like they probably should because they are looking at similar things . . .
        //unless I really need it to be pre and post hydration . . . which maybe I do?
        $sharedEventManager->attach(
            ItemSetAdapter::class,
            'api.hydrate.post',
            [$this, 'itemSetUpdate']
        );

        $sharedEventManager->attach(
            MediaAdapter::class,
            'api.hydrate.post',
            [$this, 'itemSetUpdate']
        );

        $sharedEventManager->attach(
            ItemAdapter::class,
            'api.hydrate.post',
            [$this, 'itemUpdate']
        );

        $sharedEventManager->attach(
            'Teams\Controller\Add',
            'view.add.section_nav',
            [$this, 'displayUserForm']
        );

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

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.edit.form.after',
            [$this, 'displayTeamForm']
        );

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
//            ResourceTemplate//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.add.form.before',
            [$this, 'resourceTemplateForm']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.edit.form.before',
            [$this, 'resourceTemplateForm']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.edit.form.before',
            [$this, 'resourceTemplateTeamsEdit']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.add.form.after',
            [$this, 'resourceTemplateTeamsAdd']
        );

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

        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.add.section_nav',
            [$this, 'removeTab']

        );

        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.add.form.before',
            [$this, 'displaySitePoolMsg']
        );

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


        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.edit.after',
            [$this, 'siteEdit']
        );


        //put the roles data in the user page

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.add.before',
            [$this, 'addRoleFormTemplate']

        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.before',
            [$this, 'addRoleFormTemplate']

        );

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


    }

    public function resourceTemplateForm(Event $event)
    {
        $form = $event->getTarget()->vars()->form;
        $form->add([

            'name' => 'o-module-teams:Team',
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
    public function addUserFormElement(Event $event)
    {
        $user_role = $this->getUser()
            ->getRole();
        ;

        if ($user_role === 'global_admin'){
            //TODO: only add if the user is superuser
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
                    'id' => 'team',


                ],
            ]);
        }
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
                'id' => 'team_selected'
            ],
        ]);

    }

    public function addRoleFormTemplate(Event $event){
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $roles = $entityManager->getRepository('Teams\Entity\TeamRole')->findAll();

        if (count($roles)>0){

            //this seems to encode the ids as characters

            foreach ($roles as $role):
                $role_array[$role->getId()] = $role->getName();
            endforeach;
            echo '<script> let role_array = '. json_encode($role_array) . ' </script>';
            $view = $event->getTarget();

            $view->headScript()->prependFile($view->assetUrl('js/chosen-trigger.js', 'Teams'));




        }


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

