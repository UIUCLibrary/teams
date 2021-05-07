<?php
namespace Teams;

use Omeka\Api\Adapter\UserAdapter;
use Omeka\Media\Ingester\IngesterInterface;
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
use Teams\Entity\TeamResourceTemplate;
use Teams\Entity\TeamSite;
use Teams\Entity\TeamUser;
use Teams\Form\ConfigForm;
use Teams\Form\Element\AllTeamSelect;
use Teams\Form\Element\RoleSelect;
use Teams\Form\Element\TeamSelect;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\ItemSetAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Entity\User;
use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Element\Checkbox;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;

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

    public function handleConfigForm(AbstractController $controller)
    {
        $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');

        $params = $controller->params()->fromPost();

        $globalSettings->set('teams_site_admin_make_site', $params['teams_site_admin_make_site']);
        $globalSettings->set('teams_editor_make_site', $params['teams_editor_make_site']);
   }

    public function getConfigForm(PhpRenderer $renderer)
    {

        $html = '';

        $formElementManager = $this->getServiceLocator()->get('FormElementManager');
        $form = $formElementManager->get(ConfigForm::class, []);
        $html .= $renderer->formCollection($form, false);

        return $html;
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

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $roles = $acl->getRoles();
        //entity rights are the actions of controllers
        $entityRights = ['read', 'create', 'update', 'delete'];

        //allow everyone to see their teams
        $acl->allow(
            $roles,
            'Teams\Controller\Index',
            ['index', 'teamDetail']

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

        // Only admin can manage groups.
        $adminRoles = [
            Acl::ROLE_GLOBAL_ADMIN,
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

        $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');
        if (! $globalSettings->get('teams_site_admin_make_site')){
            $acl->deny(
                'site_admin',
                'Omeka\Entity\Site',
                'create'
            );
        }

        if (!$globalSettings->get('teams_editor_make_site')){
            $acl->deny(
                'editor',
                'Omeka\Entity\Site',
                'create'
            );
        }

        $acl->deny(
            ['site_admin', 'editor', 'reviewer', 'author', 'researcher'],
            'Teams\Controller\Trash',
            'update'
        );


    }


    /**
     * Add a tab to section navigation of a admin view.
     *
     * @param Event $event
     */
    public function addTab(Event $event)
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['teams'] = 'Teams'; // @translate
        $event->setParam('section_nav', $sectionNav);
    }

    public function removeTab(Event $event)
    {
        $sectionNav = $event->getParam('section_nav');
        unset($sectionNav['item-pool']);
        $event->setParam('section_nav', $sectionNav);

    }

    /**
     * Displays the teams that a resource belongs to for admin pages.
     *
     * @param Event $event
     */
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

    /**
     * Populates the list of teams users can choose from
     * for the selector on top of browse/index type pages.
     * The selector filters the results on  the page by team.
     *
     * @param Event $event
     */
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
            $resource_type= null;
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
//TODO need to change language on results page so it is clear which team is being searched against
    /**
     * Populates a team selector on the admin advanced search page for resources
     *
     * @param Event $event
     */
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

    /**
     * Get all the teams a resource belongs to
     *
     * @param AbstractEntityRepresentation|null $resource
     * @return array
     */
    protected function listTeams(AbstractEntityRepresentation $resource = null)
    {
        $result = [];

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');

        $team_resource = $entityManager->getRepository('Teams\Entity\TeamResource')
            ->findBy(['resource'=>$resource->id()]);

        foreach ($team_resource as $tr):
            $result[$tr->getTeam()->getName()] = $tr->getTeam();
        endforeach;

        return  $result;
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

    /**
     * Allows users to add teams to resources on edit or create.
     * Adds the right side panel selector + options and pass selections to the submission form
     *
     * @param Event $event
     */
    public function displayTeamFormNoId(Event $event)
    {
        $view = $event->getTarget();
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
        $default_team = $this->currentTeam();
        if (! $default_team){
            $messanger = new Messenger();
            $messanger->addError("You can only make a new resource after you have been added to a team");
            echo '<script>$(\'button:contains("Add")\').prop("disabled",true);</script>';
        }
        $view->headScript()->appendFile($view->assetUrl('js/add-team-to-resource.js', 'Teams'));
        $view->headLink()->appendStylesheet($view->assetUrl('css/teams.css', 'Teams'));
        echo $event->getTarget()->partial(
            'teams/partial/team-form-no-id',
            ['user_id'=>$user_id, 'default_team' => $default_team]
        );
    }

//    public function addItemSite(Event $event)
//    {
//        echo $event->getTarget()->partial(
//            'teams/partial/item/add/add-item-site',
//            ['team' => $this->currentTeam()]
//        );
//
//    }

    /**
     * Displays a message where the site pool. Not currently used.
     *
     * @param Event $event
     */
    public function displaySitePoolMsg(Event $event)
    {
       echo '
<p class="section" id="team">Site Pools superseded by the Teams Module. This site will have access to all associated Team Resources.</p>
';

    }

    /**
     * Adds the teams partial the the list partials for the advanced search for resources form.
     * Each partial is a form field
     *
     * @param Event $event
     */
    public function advancedSearch(Event $event)
    {
        $partials = $event->getParams()['partials'];
        $partials[] = 'teams/partial/advanced-search';
        $event->setParam('partials', $partials);
    }


//TODO: refactor to use the currentTeam() function
//need to use the currentTeam() function

    /**
     * Adds the team selector to the admin navigation
     *
     * @param Event $event
     */
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


        } else {
            $ct = 'None';
        }
        echo $event->getTarget()->partial(
            'teams/partial/team-nav-selector',
            ['current_team' => $ct]
        );
        }
    }

    //injects into AbstractEntityAdapter where queries are structured for the api
    public function currentTeam()
    {
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $identity = $this->getUser();
        //TODO add handeling for user not logged-in !!!this current solution would not work
        if (!$identity){
            $user_id = null;
            return null;

        }else{

            $user_id = $identity->getId();
        }

        //look for their current team
        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current'=>1]);
        if (!$team_user){
            $team_user = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id]);

            if ($team_user){

                $team_user->setCurrent('1');
                $entityManager->merge($team_user);
                $entityManager->flush();
            }
            else{

                return null;
            }


        }
        if ($team_user){
            $current_team = $team_user->getTeam();
            $team_id = $current_team->getId();
        }else{
            $current_team = null;
            $team_id = 0;
        }
        return $current_team;
    }

    /**
     * Gets team ids to use for filtering that are appropriate for the context. For users browsing resources, the
     * relevant team is the user's current team. For sites, the relevant team(s) are those that the site belongs to.
     *
     * @param $query
     * @param Event $event
     * @return array
     */
    public function getTeamContext($query, Event $event)
    {


        //if the query explicitly asks for a team, that trumps all
        if (isset($query['team_id'])){
            foreach ($query['team_id'] as $id):
                $team_id[] = $id;
            endforeach;
        }

        //Logged-in or not, if it is a public site use the TeamSite
        elseif ($this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()){
            if (! isset($query['site_id'])){
                return array(0);
            }
            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
            if (isset($query['site_id'])){

                $team = $entityManager->getRepository('Teams\Entity\TeamSite')
                    ->findBy(['site' => $query['site_id']]);
                if ($team){
                    foreach ($team as $t):
                        $team_id[] = $t->getTeam()->getId();
                    endforeach;

                }
                else{$team_id = array(0);}
            } else{ $team_id = array(0);}
        }

        elseif ($this->getUser() != null && $this->currentTeam() != null) {
            $team_id[] = $this->currentTeam()->getId();
            }

        return $team_id;
    }
    /**
     *
     * Adds a join to API calls for resources and sites to filter results by teams
     *
     * @param Event $event
     */
    public function filterByTeam(Event $event){

        $qb = $event->getParam('queryBuilder');
        $query = $event->getParam('request')->getContent();
        $entityClass = $event->getTarget()->getEntityClass();
        $alias = 'omeka_root';
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        //this is for the list-of-sites block.
        if ($event->getParam('request')->getResource() === 'sites' &&
            $event->getParam('request')->getOperation() === 'search' &&
            $this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()
        ){
            //get the id for the current site
            $site_slug = $this->getServiceLocator()->get('Omeka\Status')->getRouteMatch()->getParam('site-slug');
            $site_id = $em->getRepository('Omeka\Entity\Site')->findOneBy(['slug' => $site_slug])->getId();

            //get the teams of the current site because we only want to show sites within its teams
            $teams = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site' => $site_id]);
            $team_ids = [];

            foreach ($teams as $team):
                $team_ids[] = $team->getTeam()->getId();
            endforeach;

            //only get sites that share a team with the current sitedd
            $qb->join('Teams\Entity\TeamSite', 'ts', Expr\Join::WITH, $alias . '.id = ts.site')
                ->andWhere('ts.team IN (:team_ids)')
                ->setParameter('team_ids', $team_ids);
            return;
        }


        //TODO: if is set (search_everywhere) and ACL check passes as global admin, bypass the join
        //for times when the admin needs to turn off the filter by teams (e.g. when adding resources to a new team)

        if (isset($query['bypass_team_filter']) && $this->getUser()->getRole() == 'global_admin') {
            return;
        }

        //Omeka sets up a way to specifically assign item sets to sites for the Browse by Item Set block.
        //for now just ignoring team filter on that
        //TODO replace the site_item_set with TR join itemset
        if (isset($query['site_id'])) {
            if ($entityClass == 'Omeka\Entity\ItemSet'){
                return;
            }
            else{
                $team_site = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site' => $query['site_id']]);
                foreach ($team_site as $ts):
                    $team_id[] = $ts->getTeam()->getId();
                endforeach;
                $qb->leftJoin('Teams\Entity\TeamResource', 'tr_si', Expr\Join::WITH, $alias . '.id = tr_si.resource')
                    ->andWhere('tr_si.team = :team_id')
                    ->setParameter('team_id', $team_id[0]);

                if (count($team_id) > 1) {
                    $orX = $qb->expr()->orX();
                    $i = 0;
                    foreach ($team_id as $value) {
                        $orX->add($qb->expr()->eq('tr_si.team', ':name' . $i));
                        $qb->setParameter('name' . $i, $value);
                        $i++;
                    }
                    $qb->orWhere($orX);
                    return;

                }
            }
        }
        ///If this is a case where someone is adding something and can choose which team to add it to, take that into
        /// consideration and add it to that team. Otherwise, conduct the query filtering based on the current team
        /// This turned out to be vital to making public facing browse and search work
        if (isset($query['team_id'])){

            $team_id = (int) $query['team_id'];
            $qb->leftJoin('Teams\Entity\TeamResource', 'tr_ti', Expr\Join::WITH, $alias .'.id = tr_ti.resource')
                ->andWhere('tr_ti.team = :team_id')
                ->setParameter('team_id', $team_id)
            ;
            return;

        }else{

            $team_id = $this->getTeamContext($query, $event);
        }

        if ($team_id === 0){

            return;
        }
        if ( is_array($team_id)){

            //TODO (Done): site really should be taking its team cue from the teams the site is associated with, not the user
            //otherwise it will not work when the public searches the site
            if ($entityClass == \Omeka\Entity\Site::class){

                if (!$this->getUser()){
                    return ;
                }else{

                    //TODO get the team_id's associated with the site and then do an orWhere()/orX()
                    $qb->leftJoin('Teams\Entity\TeamSite', 'ts', Expr\Join::WITH, $alias .'.id = ts.site')
                        ->andWhere('ts.team = :team_id')
                        ->setParameter('team_id', $team_id);

                    //TODO:This needs to be moved to its own fuction and only fire when on the site index page, which
                    //is where it belongs
//                        if ($team_id !=[0]){
//                            $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
//                            $team_name = $entityManager->getRepository('Teams\Entity\Team')
//                                ->findOneBy(['id'=> $team_id])
//                                ->getName();
//                            echo
//                            <<<EOF
//<script>
//    window.addEventListener("load", function () {
//    $(".site-list-heading").text("Sites for Team: '$team_name'")
//        }
//    );
//</script>
//EOF;
//                        }
                }
            }
            elseif ($entityClass == \Omeka\Entity\ResourceTemplate::class){

                $qb->leftJoin('Teams\Entity\TeamResourceTemplate', 'trt', Expr\Join::WITH, $alias .'.id = trt.resource_template')->andWhere('trt.team = :team_id')
                    ->setParameter('team_id', $team_id)
         ;
                 //
            }
            elseif ($entityClass == \Omeka\Entity\User::class){
                return;
            }
            elseif ($entityClass == \Omeka\Entity\Vocabulary::class){
                return;
            }
            else{
                //this is the case that catches for site browse. For sites with multiple teams, need to orWhere for each
                $qb->leftJoin('Teams\Entity\TeamResource', 'tr_else', Expr\Join::WITH, $alias .'.id = tr_else.resource')
                    ->andWhere('tr_else.team = :team_id')
                    ->setParameter('team_id', $team_id[0])

                ;

                if (count($team_id) > 1) {
                    $orX = $qb->expr()->orX();
                    $i=0;
                    foreach ($team_id as $value) {
                        $orX->add($qb->expr()->eq('tr.team', ':name'.$i));
                        $qb->setParameter('name'.$i, $value);
                        $i++;
                    }
                    $qb->orWhere($orX);
                }
            }
        }
    }

//Handle Users

    /**
     * Adds user's teams to the user view page
     *
     * @param Event $event
     */
    public function userTeamsView(Event $event){
        $view = $event->getTarget();
        $user_id = $view->vars()->user->id();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $team_users = $entityManager->getRepository('Teams\Entity\TeamUser')->findBy(['user'=>$user_id]);

        echo $view->partial('teams/partial/user/view', ['team_users'=> $team_users]);
    }

    /**
     * Adds user teams+roles to the user edit form
     *
     * @param Event $event
     */
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

    //at one point this was fixing a bug but no longer needed. Keeping for reference for now.
//    /**
//     * @param Event $event
//     */
//    public function userFormEdit(Event $event)
//    {
//        $view = $event->getTarget();
//        echo $view->partial('teams/partial/return_url', 'Teams');
//    }


    //TODO: make at least one team the user's 'active' team.
    /**
     *
     * When user is created, gets team+role info from the form and creates new TeamUser(s)
     *
     * @param Event $event
     */
    public function userCreate(Event $event){

        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        if ($operation == 'create'){

            $response = $event->getParam('response');
            $resource =  $response->getContent();
            $user_id =  $resource->getId();
            $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['id'=>$user_id]);
            $teams =  $em->getRepository('Teams\Entity\Team');

            $team_ids = $request->getContent()['o-module-teams:Team'];

            //format is  {team_id => role_id}
            $team_role_ids = $request->getContent()['o-module-teams:TeamRole'];

            foreach ($team_ids as $team_id):
                $team_id = (int) $team_id;
                if ($team_id === 0){
                    $u_name = $request->getContent()['o:name'];
                    $team_name = sprintf("%s's team", $u_name);
                    $team_exists = $em->getRepository('Teams\Entity\Team')->findOneBy(['name'=>$team_name]);
                    if ($team_exists){
                        $messanger = new Messenger();
                        $messanger->addWarning("The team you tried to add already exists. Added user to the team.");
                        $team = $team_exists;


                    } else{
                        $team = new Team();
                        $team->setName($team_name);
                        $team->setDescription(sprintf('A team automatically generated for new user %s', $u_name));
                        $em->persist($team);
                        $em->flush();
                    }

                } else {
                    $team = $teams->findOneBy(['id'=> $team_id]);
                }
                $role_id = $team_role_ids[$team_id];
                $role = $em->getRepository('Teams\Entity\TeamRole')
                    ->findOneBy(['id'=>$role_id]);
                $team_user_exists = $em->getRepository('Teams\Entity\TeamUser')
                    ->findOneBy(['team'=>$team->getId(), 'user'=>$user_id]);
                if (! $team_user_exists){
                    $team_user = new TeamUser($team,$user,$role);
                    $em->persist($team_user);
                }



            endforeach;
            $em->flush();
        }
    }

    /**
     * Sets the default value for AutoAssignNewItems to false
     * @param Event $event
     */
    public function siteCreateAutoAssignValue(Event $event)
    {
        $entity = $event->getParam('entity');
        $entity->setAssignNewItems(false);
        $event->setParam('entity', $entity);

    }
    public function siteCreate(Event $event)
    {
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        if ($operation === 'create'){
            $response = $event->getParam('response');
            $resource =  $response->getContent();
            $site_id =  $resource->getId();
            $site = $em->getRepository('Omeka\Entity\Site')->findOneBy(['id'=>$site_id]);
            $teams =  $em->getRepository('Teams\Entity\Team');

            $team_ids = $request->getContent()['team'];


            foreach ($team_ids as $team_id):
                $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                $trt = new TeamSite($team, $site);
                $em->persist($trt);
            endforeach;
            $em->flush();
        }
    }

    /**
     *
     * On Team Update, remove all TeamUser entities with user id and generates new ones based. Makes sure user has one
     * team that is set to 'active'.
     *
     * @param Event $event
     */
    public function userUpdate(Event $event){
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $error_store = $event->getParam('errorStore');

        if ($operation == 'update' ){
            $user_id = $request->getId();

            //stop if teams aren't part of the update
            if (array_key_exists('o-module-teams:Team', $request->getContent())){
                //array of team ids

                if ($user_role = $this->getUser()->getRole() == 'global_admin') {//remove the user's teams
                    $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['id' => $user_id]);

                    $pre_teams = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $user_id]);

                    foreach ($pre_teams as $pre_team):
                        if ($pre_team->getCurrent()){
                            $current_team_id = $pre_team->getTeam()->getId();
                        }
                        $em->remove($pre_team);
                    endforeach;
                    $em->flush();
                    $current_team_id = isset($current_team_id )? $current_team_id : 0;

                    //add the teams from the form
                    $teams =  $em->getRepository('Teams\Entity\Team');
                    foreach ($request->getContent()['o-module-teams:Team'] as $team_id):
                        $team_id = (int) $team_id;

                        //adding new team from the user form, indicated by an id of 0
                        if ($team_id === 0){
                            $u_name = $request->getContent()['o:name'];
                            $team_name = sprintf("%s's team", $u_name);
                            $team_exists = $em->getRepository('Teams\Entity\Team')->findOneBy(['name'=>$team_name]);
                            if ($team_exists){
                                $messanger = new Messenger();
                                $messanger->addWarning("The team you tried to add already exists. Added user to the team.");
                                $team = $team_exists;


                            } else{
                                $team = new Team();
                                $team->setName($team_name);
                                $team->setDescription(sprintf('A team automatically generated for new user %s', $u_name));
                                $em->persist($team);
                                $em->flush();
                            }

                        } else {
                            $team = $teams->findOneBy(['id'=> $team_id]);
                        }

                        //get it this way because the roles are added dynamically as js and not part of pre-baked form
                        $role_id = $request->getContent()['o-module-teams:TeamRole'][$team_id];
                        $role = $em->getRepository('Teams\Entity\TeamRole')
                            ->findOneBy(['id'=>$role_id]);

                        $team_user_exists = $em->getRepository('Teams\Entity\TeamUser')
                            ->findOneBy(['team'=>$team->getId(), 'user'=>$user_id]);

                        if ($team_user_exists){
                            echo $team_user_exists->getId();
                        } else {
                            $team_user = new TeamUser($team,$user,$role);
                            $em->persist($team_user);
                            if ($team_id == $current_team_id){
                                $team_user->setCurrent(true);
                            }
                            $em->persist($team_user);

                            //this is not ideal to flush each iteration, but it is how to check to make sure they didn't
                            //TODO: catch this in chosen-trigger.js instead
                            $em->flush();
                        }

                    endforeach;

                    $em->flush();

                    //if their current team was removed, just give them a current team from the top of the list
                    if (array_key_exists('o-module-teams:Team', $request->getContent())){
                        if (! in_array($current_team_id, $request->getContent()['o-module-teams:Team'])){
                            $em->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id])
                                ->setCurrent(true);
                            $em->flush();

                        }
                    }

                }

            }

        }
        return;

    }

    public function siteUpdate(Event $event)
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $error_store = $event->getParam('errorStore');

        if ($operation==='update' && $request->getContent()['team']){

            $site_id = $request->getId();
            $team_sites = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
            foreach ($team_sites as $team_site):
                $em->remove($team_site);
            endforeach;
            $em->flush();

            $team_ids = $request->getContent()['team'];
            //add teams to the site for each team listed in the form
            foreach ($team_ids as $team):
                $team_site = new TeamSite($em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team]),
                    $em->getRepository('Omeka\Entity\Site')->findOneBy(['id' => $site_id]));
                $em->persist($team_site);
            endforeach;
            $em->flush();

        }
    }

//Handle Item Sets

    /**
     * Gets the teams submitted in the item set create team and creates new TeamResource entities to represent the
     * relationship between team and item set. Does NOT recursively go through the items and add them as well.
     *
     * @param Event $event
     */
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

    //TODO: add validation and report errors
    /**
     * On Item Set update, deletes all TeamResources with item set id and creates new TeamResources based on form data
     *
     * @param Event $event
     */
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

//Handle Resource Templates. Because Resource templates are not extended from the Resource entity, they are represented
// by a separate tables in Teams as well.

    /**
     *
     * On create, get teams from Resource Template create form and add new TeamResourceTemplate entities
     *
     * @param Event $event
     */
    public function resourceTemplateCreate(Event $event){

        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');


        if ($operation == 'create') {
            $resource_template =  $event->getParam('entity');
            $teams =  $em->getRepository('Teams\Entity\Team');
            foreach ($request->getContent()['o-module-teams:Team'] as $team_id):
                $team = $teams->findOneBy(['id'=>$team_id]);
                $trt = new TeamResourceTemplate($team,$resource_template);
                $em->persist($trt);
            endforeach;
            $em->flush();
        }

    }

    /**
     *
     * On update, remove all TeamResourceTemplate entities with ResourceTemplate id and generate new entities based on
     * form data
     *
     * @param Event $event
     */
    public function resourceTemplateUpdate(Event $event){
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $error_store = $event->getParam('errorStore');

        if ($operation == 'update') {
            $resource_template_id = $request->getId();
            $resource_template  = $em->getRepository('Omeka\Entity\ResourceTemplate')
                ->findOneBy(['id' => $resource_template_id]);

            $pre_teams = $em->getRepository('Teams\Entity\TeamResourceTemplate')
                ->findBy(['resource_template' => $resource_template_id]);

            foreach ($pre_teams as $pre_team):
                $em->remove($pre_team);
            endforeach;
            $em->flush();

            $teams =  $em->getRepository('Teams\Entity\Team');
            foreach ($request->getContent()['o-module-teams:Team'] as $team_id):
                $team = $teams->findOneBy(['id'=>$team_id]);
                $trt = new TeamResourceTemplate($team,$resource_template);
                $em->persist($trt);
            endforeach;
            $em->flush();
        }
    }

    /**
     *
     * Adds resource template teams to the edit form
     *
     * @param Event $event
     */
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

    /**
     *
     * Adds team field to the resource template add form. Warns users if they don't currently belong to a team
     *
     * @param Event $event
     */
    public function resourceTemplateTeamsAdd(Event $event)
    {
        $view =  $event->getTarget();

        if ($has_team = $this->currentTeam()){
            $team_id = $has_team->getId();

        } else {
            $messanger = new Messenger();
            $messanger->addError("You can only make a resource template after you have been added to a team");
            $team_id = 0;
            echo '<script>$(\'button:contains("Add")\').prop("disabled",true);</script>';

        }
        echo $view->partial('teams/partial/resource-template/add', ['team_id' => $team_id]);

    }

//Handle Items

    /**
     *
     * On update, remove all TeamResources associated with item and associated media, and generate new TeamResources
     * based on form data
     *
     * @param Event $event
     */
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
                    error_log('found a media. Id: ' . $media->getId());

                    $media_ids[] = $media->getId();
                endforeach;

                //remove resource from all teams
                $team_resources = $em->getRepository('Teams\Entity\TeamResource')->findBy(['resource' => $resource_id]);
                foreach ($team_resources as $tr):
                    $em->remove($tr);
                endforeach;



                //remove associated media from all teams
                foreach ($media_ids as $media_id):
                    $team_resources = $em->getRepository('Teams\Entity\TeamResource')->findBy(['resource' => $media_id]);
                    foreach ($team_resources as $tr):
                        $em->remove($tr);
                    endforeach;
                endforeach;
                $em->flush();

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
                        error_log('this is a media id:' . $media_id);
                        if (! $em->getRepository('Teams\Entity\TeamResource')
                            ->findOneBy(['team'=>$team_id, 'resource'=>$media_id])){

                        $m = $em->getRepository('Omeka\Entity\Resource')->findOneBy(['id'=>$media_id]);
                            if ($m){
                                $mtr = new TeamResource($team, $m);
                                $em->persist($mtr);
                            }

                        }
                    endforeach;
                endforeach;
                $em->flush();
            }
        }

    }

    /**
     *
     * On create, add TeamResource entities for item and associated media based on form data
     *
     * @param Event $event
     */
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

                    //if there is media, add those to the team as well
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


//Handle media

//TODO: should this make sure that media and its associated item don't belong to different teams?
    /**
     *
     * On create, add TeamResource based on information from the form
     *
     * @param Event $event
     */
    public function addMedia(Event $event){
        $request = $event->getParam('request');
        $operation = $request->getOperation();
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $entity = $event->getParam('entity');


        if ($operation == 'create'){

            $item_id = $entity->getItem()->getId();

            $team_resources = $em->getRepository('Teams\Entity\TeamResource')->findBy(['resource'=>$item_id]);
            foreach($team_resources as $team_resource):
                $team = $team_resource->getTeam();
                $tr = new TeamResource($team, $entity);
                $em->persist($tr);
            endforeach;
            $em->flush();


        }

    }

//Handle sites

    /**
     *
     * Adds team field to the create site form
     *
     * @param Event $event
     */
    public function siteFormAdd(Event $event)
    {
        if ($has_team = $this->currentTeam()){
            $team_id = $has_team->getId();

        } else {
            $messanger = new Messenger();
            $messanger->addError("You can only make a site after you have been added to a team");
            $team_id = 0;

        }
        $view = $event->getTarget();
        echo $view->partial('teams/partial/site-admin/add.phtml', ['team_ids'=>$team_id]);
    }

    /**
     *
     *Adds team field to the edit site form
     *
     * @param Event $event
     */
    public function siteEdit(Event $event)
    {
        $view = $event->getTarget();
        $site_id = $view->vars()->site->id();
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $site_teams = $entityManager->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $team_ids = array();
        foreach ($site_teams as $site_team):
            $team_ids[] = $site_team->getTeam()->getId();
        endforeach;
        echo $view->partial('teams/partial/site-admin/edit', ['site_teams' => $site_teams, 'team_ids' => $team_ids]);

    }

    /**
     *
     * Returns the expected string for proxied resource class in cases where the class returned is the doctrine proxy.
     *
     * @param $resource
     * @return false|string
     */
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

    /*
     * TODO: need to add some way to automatically handle classes from other modules.
     * Default should be to allow access because they end up being things like adding a piece of metadata to an item
     * usually. In fact, for things that don't exist in a Team table, I think we can just pass true. Or in some other
     * way indicate that the Teams filter isn't relevant. Or, maybe check that before calling this function?
    */

    /**
     *
     * Check to see if the user and the object they are attempting to access or change are part of the same team.
     *
     * @param $resource
     * @param $team_user
     * @return bool
     */
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
        elseif ($res_class == 'Omeka\Entity\Property'){
            return true;
        }
        elseif ($res_class == 'CustomVocab\Entity\CustomVocab')
        {
            return true;
        }

        elseif (strpos($res_class, 'Mapping\Entity') === 0){
            return true;
        }

        elseif ($res_class == "Omeka\Entity\Asset"){
            return true;
        }

        else{
            $messanger = new Messenger();
            $msg = sprintf(
                'Case not yet handled. The developer of Teams has not yet explicitly handled this resource, 
                    so by default action here is not permitted. Resource "%1$s: %2$s" .'
                ,
                $res_class, $resource->getId()
            );
            $messanger->addError($msg);

            throw new Exception\PermissionDeniedException($msg);

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

        } else{
            return null;
        }
    }

    /**
     *
     * Checks to see if the user has the authority to do something based on their role in their current team. Users can
     * have different roles with different permissions in different teams.
     *
     * @param EntityInterface $resource
     * @param $action
     * @param Event $event
     * @return bool
     */
    public function teamAuthority(EntityInterface $resource, $action, Event $event){
        $user = $this->getUser();

        /*
         * first go through a couple of common cases where we don't need to judge permissions and don't bother checking
         * any other condition
         */

        //if the user isn't logged in (e.g., the public), use the default settings
        if (!$user){
            return true;
        }

        /*
         * This may make the previous rule redundant, but keeping both for now
         * No matter who is looking at the front-end, don't consider team authority
         * based on the user
         */

        //if it isn't on the backend, let the public vs private rules take over
        if ($this->getServiceLocator()->get('Omeka\Status')->isSiteRequest()) {
            return true;
        }

        $is_glob_admin = ($user->getRole() === 'global_admin');


        //if it is the global admin, bypass any team controls (but will still apply filters)
        if ($is_glob_admin){
            return true;
        }
        $messenger = new Messenger();

        $authorized = false;

        $res_class = $this->getResourceClass($resource);

        //case that I don't fully understand. When selecting resource template on new item form
        //the Omeka\AuthenticationService->getIdentity() returns null


        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        $user_id = $user->getId();
        $team_user = $em->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current'=>true, 'user'=>$user_id]);
        $team = $team_user->getTeam();
        $team_user_role = $team_user->getRole() ;

        //don't check if in same team if a create action, because items only assigned teams after creation
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
                $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');
                if ($globalSettings->get('teams_site_admin_make_site') && $user->getRole() == 'site_admin'){
                    $authorized = true;
                } elseif ($globalSettings->get('teams_editor_make_site') && $user->getRole() == 'editor'){
		    $authorized = true;
		} else {
                    $authorized = $is_glob_admin;
                }
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

        //a list of classes where we don't need to check teams
        //TODO: this should be refactored and go with the checks in the beginning
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

        elseif ($res_class == "CustomVocab\Entity\CustomVocab"){
            return true;
        }

        elseif (strpos($res_class, 'Mapping\Entity') === 0){
            return true;
        }

        elseif ($res_class == "Omeka\Entity\Asset"){
            return true;
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

    /**
     * Disable the auto-add field on the site edit form. Teams manages this by automatically adding items to the
     * appropriate team.
     * @param Event $event
     */
    public function siteSettingsRemoveAutoAssign(Event $event)
    {
//        $event->getTarget()->get('general')->get('o:assign_new_items')->setAttribute('disabled', 'disabled');
        $event->getTarget()->get('general')
            ->get('o:assign_new_items')
            ->setOption('info', 'The Teams Module manages how items become associated with sites, so this has been disabled.');

    }

    public function removeDefaultSite(Event $event)
    {

        //pre-fill with the sites that should be default based on that user's team.
//        $team_sites = $this->currentTeam()->getTeamSites();
//        $site_ids = [];
//        foreach ($team_sites as $team_site):
//            $site_ids[] = $team_site->getSite()->getId();
//        endforeach;
//        $event->getTarget()->get('user-settings')
//            ->get('default_item_sites')
//            ->setAttribute('value', $site_ids);
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

//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\User',
//            'view.edit.form.after',
//            [$this, 'userFormEdit']
//        );


        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.show.after',
            [$this, 'userTeamsView']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.add.before',
            [$this, 'siteFormAdd']
        );



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

        $sharedEventManager->attach(
            'Teams\Controller\Index',
            'view.browse.before',
            [$this, 'teamSelectorBrowse']
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

        $sharedEventManager->attach(
            UserAdapter::class,
            'api.execute.post',
            [$this, 'userCreate']
        );

        $sharedEventManager->attach(
            SiteAdapter::class,
            'api.execute.post',
            [$this, 'siteCreate']
        );

        $sharedEventManager->attach(
            SiteAdapter::class,
            'api.hydrate.post',
            [$this, 'siteCreateAutoAssignValue']
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
            SiteAdapter::class,
            'api.hydrate.post',
            [$this, 'siteUpdate']
        );

        $sharedEventManager->attach(
            UserAdapter::class,
            'api.hydrate.post',
            [$this, 'userUpdate']
        );

        $sharedEventManager->attach(
            ResourceTemplateAdapter::class,
            'api.execute.post',
            [$this, 'resourceTemplateUpdate']
        );

        $sharedEventManager->attach(
            ResourceTemplateAdapter::class,
            'api.hydrate.post',
            [$this, 'resourceTemplateCreate']
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
            MediaAdapter::class,
            'api.hydrate.post',
            [$this, 'addMedia']
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
            'Omeka\Controller\SiteAdmin\Index',
            'view.add.section_nav',
            [$this, 'removeTab']
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
            'view.add.form.before',
            [$this, 'displaySitePoolMsg']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.form.after',
            [$this, 'displayTeamFormNoId']
        );

//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\Item',
//            'view.add.form.after',
//            [$this, 'addItemSite']
//        );


        //Advanced Search//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.advanced_search',
            [$this, 'advancedSearch']
        );

        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_elements',
            [$this, 'siteSettingsRemoveAutoAssign']
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

//         Add the team element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'addUserFormElement']
        );


        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'removeDefaultSite']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SiteForm::class,
            'form.add_elements',
            [$this, 'addSiteFormElement']
        );


    }

    /**
     * Add team element to the resource template form
     *
     * @param Event $event
     */
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

    /**
     * Add team element to the user form
     * @param Event $event
     */
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
//                    'required' => 'true',
                ],
                'attributes' => [
                    'multiple' => true,
                    'id' => 'team',
//                    'required' => true,
                ],
            ]);

//            this needs to be in here so that the form will push the jQuery created team roles into the request object
            $form->get('user-information')->add([
                'name' => 'o-module-teams:TeamRole',
                'type' => RoleSelect::class,
                'options' => [
                    'label' => ' ', // @translate
                ],
                'attributes' => [
                    'multiple' => true,
                    'id' => 'team role',
                    'hidden' => 'hidden',
//                    'class' => 'hidden_no_value'


                ],
            ]);
        }
    }

    /**
     * Add team element to the site form
     *
     * @param Event $event
     */
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

    /**
     * Add TeamRoles to from
     *
     * @param Event $event
     */
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
}

