<?php
namespace Teams;

use Doctrine\ORM\Events;
use Omeka\Entity\Resource;
use Omeka\Permissions\Acl;
use Teams\Api\Adapter\TeamAdapter;
use Teams\Controller\TestController;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamUser;
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
use Teams\Model\TestControllerFactory;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Interop\Container\ContainerInterface;


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
CREATE TABLE team_user (team_id INT NOT NULL, user_id INT NOT NULL, role_id INT DEFAULT NULL, is_current TINYINT(1) DEFAULT NULL, INDEX IDX_5C722232296CD8AE (team_id), INDEX IDX_5C722232A76ED395 (user_id), INDEX IDX_5C722232D60322AC (role_id), UNIQUE INDEX active_team (is_current, user_id), PRIMARY KEY(team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(240) NOT NULL, can_add_users TINYINT(1) DEFAULT NULL, can_add_items TINYINT(1) DEFAULT NULL, can_add_itemsets TINYINT(1) DEFAULT NULL, can_modify_resources TINYINT(1) DEFAULT NULL, can_delete_resources TINYINT(1) DEFAULT NULL, can_add_site_pages TINYINT(1) DEFAULT NULL, comment LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_86887E115E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_resource (team_id INT NOT NULL, resource_id INT NOT NULL, INDEX IDX_4D32868296CD8AE (team_id), INDEX IDX_4D3286889329D25 (resource_id), PRIMARY KEY(team_id, resource_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_resource_template (team_id INT NOT NULL, resource_template_id INT NOT NULL, INDEX IDX_75325B72296CD8AE (team_id), INDEX IDX_75325B7216131EA (resource_template_id), PRIMARY KEY(team_id, resource_template_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
CREATE TABLE team_site (team_id INT NOT NULL, site_id INT NOT NULL, is_current TINYINT(1) DEFAULT NULL, INDEX IDX_B8A2FD9F296CD8AE (team_id), INDEX IDX_B8A2FD9FF6BD1646 (site_id), UNIQUE INDEX active_team (is_current, site_id), PRIMARY KEY(team_id, site_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;');
        $conn->exec('
ALTER TABLE team_user ADD CONSTRAINT FK_5C722232296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_user ADD CONSTRAINT FK_5C722232A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE;');
        $conn->exec('
ALTER TABLE team_user ADD CONSTRAINT FK_5C722232D60322AC FOREIGN KEY (role_id) REFERENCES team_role (id);');
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



    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec('DROP TABLE IF EXISTS team_user');
        $conn->exec('DROP TABLE IF EXISTS team_role');
        $conn->exec('DROP TABLE IF EXISTS team_resource');
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

        $acl->deny(
            Acl::ROLE_SITE_ADMIN,
            [Controller\IndexController::class],
            ['index']

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

    }


    /**
     * Add the tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event)
    {


        $params = $event->getParam('section_nav');
        foreach ($params as $p):
            echo $p;
//            foreach ($p as $ar):
//                echo $ar . "<br>";
//                foreach ($ar as $what):
//                    echo gettype($what);
//                endforeach;
//            endforeach;
        endforeach;
        $value = null;

        $values = $event->getParam('Values');
        echo $values;

        $event->setParam('Metadata', $value);


        $sectionNav = $event->getParam('section_nav');
        $sectionNav['teams'] = 'TeamTest10'; // @translate
        $event->setParam('section_nav', $sectionNav);


    }

    public function overloadVariable(Event $event)
    {
        $params = $event->getParams();
        foreach ($params as $p):
            foreach ($p as $ar):
                echo $ar . "<br>";
//                foreach ($ar as $what):
//                    echo gettype($what);
//                endforeach;
            endforeach;
        endforeach;
        $value = null;

        $event->setParam('Metadata', $value);
    }

    public function viewShowAfterResource(Event $event)
    {
        echo '<div id="teams" class="section">';
        $resource = $event->getTarget()->vars()->resource;
        $this->adminShowTeams($event);
        echo '</div>';
    }

    public function adminShowTeams(Event $event)
    {

        $resource = $event->getTarget()->vars()->resource;
        $resource_type = $resource->getControllerName();
        $associated_teams = $this->listTeams($resource);
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

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $team_user = $entityManager->getRepository('Teams\Entity\TeamUser');
        $user_teams = $team_user->findBy(['user'=>$user_id]);
        $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true]);
        if ($current_team){
            $current_team = $current_team->getTeam()->getName();
        }else $current_team = null;

        echo $event->getTarget()->partial(
            'teams/partial/team-selector',
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

    protected function listTeams(AbstractEntityRepresentation $resource = null)
    {


        $result = [];
        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $teams = $entityManager->getRepository('Teams\Entity\Team')->findAll();
        foreach ($teams as $team):
            $team_resources = $team->getTeamResources();
            foreach ($team_resources as $team_resource):
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

        echo $event->getTarget()->partial(
            'teams/partial/team-form'
        );
    }

    public function teamSelectorNav(Event $event)
    {
        $identity = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService')->getIdentity();
        $user_id = $identity->getId();

        $entityManager = $this->getServiceLocator()->get('Omeka\EntityManager');
        $ct = $entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['is_current'=>true, 'user'=>$user_id]);
        if($ct){
            $ct = $ct->getTeam()->getName();
        } else {
            $ct = 'None';
        }
        echo $event->getTarget()->partial(
            'teams/partial/team-nav-selector',
            ['current_team' => $ct]
        );
    }


    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $services = $this->getServiceLocator();







        //on this one, which should be a model for further refactorying out the manual controller edits, need to add
        //vars used by the partial
//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\ResourceTemplate',
//            'view.browse.before',
//            [$this, 'teamSelectorBrowse']
//        );




        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'teamSelectorNav']
        );


















        // Add the show groups to the show admin pages.


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



        // Show pages //
            //Item//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            [$this, 'addTab']
        );
//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\Item',
//            'view.show.sidebar',
//            [$this, 'addViewAfter']
//
//        );
//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\Item',
//            'view.show.after',
//            [$this, 'viewShowAfterResource']
//        );
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



        //Add pages//
            //ItemSet//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.add.form.after',
            [$this, 'displayTeamForm']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.add.section_nav',
            [$this, 'addTab']
        );
            //ItemSet//
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.section_nav',
            [$this, 'addTab']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.form.after',
            [$this, 'displayTeamForm']
        );





//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\Item',
//            'view.edit.form.after',
//            [$this, 'displayGroupResourceForm']
//        );

        // Bypass the core filter for media (detach two events of Omeka\Module).
        // The listeners can't be cleared without a module weighting system.
//        $listeners = $sharedEventManager->getListeners([MediaAdapter::class], 'api.search.query');
//        $sharedEventManager->detach(
//            [$listeners[1][0][0], 'filterMedia'],
//            MediaAdapter::class
//        );
//        $sharedEventManager->attach(
//            MediaAdapter::class,
//            'api.search.query',
//            [$this, 'filterMedia'],
//            100
//        );
//        $sharedEventManager->attach(
//            MediaAdapter::class,
//            'api.find.query',
//            [$this, 'filterMedia'],
//            100
//        );
//
        // Add the group part to the representation.
        $representations = [
            UserRepresentation::class,
            ItemSetRepresentation::class,
            ItemRepresentation::class,
            MediaRepresentation::class,
        ];
//        foreach ($representations as $representation) {
//            $sharedEventManager->attach(
//                $representation,
//                'rep.resource.json',
//                [$this, 'filterEntityJsonLd']
//            );
//        }

        $adapters = [
            UserAdapter::class,
            ItemSetAdapter::class,
            ItemAdapter::class,
            MediaAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            // Add the group filter to the search.
//            $sharedEventManager->attach(
//                $adapter,
//                'api.search.query',
//                [$this, 'searchQuery']
//            );

            // The event "api.*.post" is used to avoid some flush issues.
//            $sharedEventManager->attach(
//                $adapter,
//                'api.create.post',
//                [$this, 'handleCreatePost']
//            );
//            $sharedEventManager->attach(
//                $adapter,
//                'api.update.post',
//                [$this, 'handleUpdatePost']
//            );
            // Required for partial batch, since requests are filtered by core.
//            $sharedEventManager->attach(
//                $adapter,
//                'api.batch_update.post',
//                [$this, 'handleBatchUpdatePost']
//            );
        }
//         The deletion is managed automatically when not recursive.
//        if ($recursiveItemSets) {
//            $sharedEventManager->attach(
//                ItemSetAdapter::class,
//                'api.delete.pre',
//                [$this, 'handleRecursiveDeleteItemSetPre']
//            );
//        }

//         Add headers to group views.
//        $sharedEventManager->attach(
//            GroupController::class,
//            'view.show.before',
//            [$this, 'addHeadersAdmin']
//        );
//        $sharedEventManager->attach(
//            GroupController::class,
//            'view.browse.before',
//            [$this, 'addHeadersAdmin']
//        );

        // Add the group element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'addUserFormElement']
        );
//        $sharedEventManager->attach(
//            \Omeka\Form\UserForm::class,
//            'form.add_input_filters',
//            [$this, 'addUserFormFilter']
//        );
        // FIXME Use the autoset of the values (in a fieldset) and remove this.
        //This is breaking authors from editing themselves, and is not required for admin
        //to assign user to a group so commenting out for now


        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.form.before',
            [$this, 'addUserFormValue']
        );

//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\User',
//            'view.edit.response',
//            [$this, 'addTeamUpdate']
//
//        );

//        public function addTeamUpdate($response)
//    {
//        $response = $this->api($form)->update('users', $id, $values['user-information']);
//    }




        // Add the show groups to the show admin pages.
//        $sharedEventManager->attach(
//            'Omeka\Controller\Admin\User',
//            'view.show.after',
//            [$this, 'viewShowAfterUser']
//        );
//
//        // Add the groups form to the resource batch edit form.
//        $sharedEventManager->attach(
//            \Omeka\Form\UserBatchUpdateForm::class,
//            'form.add_elements',
//            [$this, 'addBatchUpdateFormElement']
//        );
//        $sharedEventManager->attach(
//            \Omeka\Form\UserBatchUpdateForm::class,
//            'form.add_input_filters',
//            [$this, 'addBatchUpdateFormFilter']
//        );
//        $sharedEventManager->attach(
//            \Omeka\Form\ResourceBatchUpdateForm::class,
//            'form.add_elements',
//            [$this, 'addBatchUpdateFormElement']
//        );
//        $sharedEventManager->attach(
//            \Omeka\Form\ResourceBatchUpdateForm::class,
//            'form.add_input_filters',
//            [$this, 'addBatchUpdateFormFilter']
//        );
//
//        if ($recursiveItemSets) {
//            $controllers = [
//                'Omeka\Controller\Admin\ItemSet',
//            ];
//        } elseif ($recursiveItems) {
//            $controllers = [
//                'Omeka\Controller\Admin\ItemSet',
//                'Omeka\Controller\Admin\Item',
//            ];
//        } else {
//            $controllers = [
//                'Omeka\Controller\Admin\ItemSet',
//                'Omeka\Controller\Admin\Item',
//                'Omeka\Controller\Admin\Media',
//            ];
//        }
//        foreach ($controllers as $controller) {
//            // Add the group element form to the resource form.
//            $sharedEventManager->attach(
//                $controller,
//                'view.add.section_nav',
//                [$this, 'addTab']
//            );
//            $sharedEventManager->attach(
//                $controller,
//                'view.edit.section_nav',
//                [$this, 'addTab']
//            );
//            $sharedEventManager->attach(
//                $controller,
//                'view.add.form.after',
//                [$this, 'displayGroupResourceForm']
//            );
//            $sharedEventManager->attach(
//                $controller,
//                'view.edit.form.after',
//                [$this, 'displayGroupResourceForm']
//            );
//        }
//
//        $controllers = [
//            'Omeka\Controller\Admin\ItemSet',
//            'Omeka\Controller\Admin\Item',
//            'Omeka\Controller\Admin\Media',
//        ];
//        foreach ($controllers as $controller) {
//            // Add the show groups to the resource show admin pages.
//            $sharedEventManager->attach(
//                $controller,
//                'view.show.section_nav',
//                [$this, 'addTab']
//            );
//
//            // Add the show groups to the show admin pages.
//            $sharedEventManager->attach(
//                $controller,
//                'view.show.after',
//                [$this, 'viewShowAfterResource']
//            );
//        }
//
//        $controllers = [
//            'Omeka\Controller\Admin\User',
//            'Omeka\Controller\Admin\ItemSet',
//            'Omeka\Controller\Admin\Item',
//            'Omeka\Controller\Admin\Media',
//        ];
//        foreach ($controllers as $controller) {
//            // Add the show groups to the browse admin pages (details).
//            $sharedEventManager->attach(
//                $controller,
//                'view.details',
//                [$this, 'viewDetails']
//            );
//
//            // Filter the search filters for the advanced search pages.
//            $sharedEventManager->attach(
//                $controller,
//                'view.search.filters',
//                [$this, 'filterSearchFilters']
//            );
//        }
    }




    /**
     * Add the tab to section navigation.
     *
     * @param Event $event
     */




    public function addUserFormElement(Event $event)
    {


        $form = $event->getTarget();
        $form->get('user-information')->add([
            'name' => 'o-module-teams:Team',
            'type' => TeamSelect::class,
            'options' => [
                'label' => 'Teams', // @translate
                'chosen' => true,
            ],
            'attributes' => [
                'multiple' => true,
            ],
        ]);
    }



    public function addUserFormValue(Event $event)
    {
        $user = $event->getTarget()->vars()->user;
        $form = $event->getParam('form');
        $values = $this->listTeams($user, 'reference');
        $form->get('user-information')->get('o-module-teams:Team')
            ->setAttribute('value', array_keys($values));
    }

//    protected function listTeams(AbstractEntityRepresentation $resource = null, $contentType = null)
//    {
//        if (is_null($resource) || empty($resource->id())) {
//            return [];
//        }
//        $resourceJson = $resource->jsonSerialize();
//        $list = empty($resourceJson['o-module-teams:Team'])
//            ? []
//            : $resourceJson['o-module-team:Team'];
//
//        $result = [];
//        switch ($contentType) {
//            case 'reference':
//                foreach ($list as $entity) {
//                    $result[$entity->name()] = $entity;
//                }
//                break;
//            case 'representation':
//                $api = $this->getServiceLocator()->get('Omeka\ApiManager');
//                foreach ($list as $entity) {
//                    $result[$entity->name()] = $api->read('team', $entity->id())->getContent();
//                }
//                break;
//            case 'json':
//            default:
//                $result = $list;
//                break;
//        }
//        return  $result;
//    }

//    public function addUserFormFilter(Event $event)
//    {
//
//
//        // TODO Add a validator for the groups of user.
//        $inputFilter = $event->getParam('inputFilter');
//        $inputFilter->get('user-information')->add([
//            'name' => 'o-module-teams:Team',
//            'required' => false,
//        ]);
//    }

//    /**
//     * Helper to get the column id of a representation.
//     *
//     * Note: Resource representation have method resourceName(), but site page
//     * and user don't. Site page has no getControllerName().
//     *
//     * @param AbstractEntityRepresentation $representation
//     * @return string
//     */
//    protected function columnNameOfRepresentation(AbstractEntityRepresentation $representation)
//    {
//        $entityColumnNames = [
//            'item-set' => 'item_set_id',
//            'item' => 'item_id',
//            'media' => 'media_id',
//            'user' => 'user_id',
//        ];
//        $entityColumnName = $entityColumnNames[$representation->getControllerName()];
//        return $entityColumnName;
//    }

//    public function filterEntityJsonLd(Event $event)
//    {
//        // The groups are not shown to public.
//        $acl = $this->getServiceLocator()->get('Omeka\Acl');
//        if (!$acl->userIsAllowed(TeamAdapter::class, 'search')
//            && !$acl->userIsAllowed(TeamAdapter::class, 'read')
//        ) {
//            return;
//        }
//
//        $resource = $event->getTarget();
//        $columnName = $this->columnNameOfRepresentation($resource);
//        $jsonLd = $event->getParam('jsonLd');
//        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
//        $groups = $api
//            ->search('groups', [$columnName => $resource->id()], ['responseContent' => 'reference'])
//            ->getContent();
//        $jsonLd['o-module-group:group'] = $groups;
//        $event->setParam('jsonLd', $jsonLd);
//    }
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

