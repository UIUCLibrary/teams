<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Form\ResourceForm;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use phpDocumentor\Reflection\Types\This;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamResourceTemplate;
use Teams\Entity\TeamSite;
use Teams\Entity\TeamUser;
use Teams\Form\AddSitesToTeam;
use Teams\Form\AddSiteToTeamFieldset;
use Teams\Form\TeamAddUserRole;
use Teams\Form\TeamItemSetForm;
use Teams\Form\TeamRoleForm;
use Teams\Form\TeamForm;
use Teams\Form\TeamUserForm;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\ServiceManager\ServiceLocatorInterface;


Class AddController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    public function teamAddAction()
    {

        $all_u_array = array();
        $all_u_collection = $this->api()->search('users')->getContent();
        foreach ($all_u_collection as $u):
            $all_u_array[$u->name()] = $u->id();
        endforeach;

        $role_query = $this->entityManager->createQuery('select partial r.{id, name} from Teams\Entity\TeamRole r');
        $roles_array =  $role_query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
        $roles = array();

        foreach ($roles_array as $role):
            $roles[$role['name']] = $role['id'];
        endforeach;

        $request   = $this->getRequest();

        $form = $this->getForm(TeamForm::class);
        $userForm = $this->getForm(TeamUserForm::class);
        $itemsetForm = $this->getForm(TeamItemSetForm::class);
        $userRoleForm = $this->getForm(TeamAddUserRole::class);
        $view = new ViewModel(
            [
                'form' => $form,
                'available_u_array' => $all_u_array,
                'roles' => $roles,
                'userForm' => $userForm,
                'itemsetForm' => $itemsetForm,
                'userRoleForm' => $userRoleForm,
            ]
        );

        //if it is get, then give them the form
        if (! $request->isPost()){
            return $view;
        }


        //otherwise, set the data
        //TODO: turn the section where user+role are added into a form so it can be populated below
        $form->setData($request->getPost());
        $userForm->setData($request->getPost());
        $itemsetForm->setData($request->getPost());
        $userRoleForm->setData($request->getPost());


        //if the form isn't valid, return it

        if (! $form->isValid()){
            return $view;
        }

        //get the data from the post
        $data = $request->getPost('team');

        $newTeam = $this->api($form)->create('team', $data);

        if ($newTeam) {
            //looks like this was a diagnostic i used to see what was in the data variable
            $view->setVariable('post_data', $data);
            $view->setVariable('team', $newTeam);

            $team = $this->entityManager->getRepository('Teams\Entity\Team')
                ->findOneBy(['id' => (int)$newTeam->getContent()->id()]);
            if ($request->getPost('user_role')) {
                foreach ($request->getPost('user_role') as $userId => $roleId):
                    $user = $this->entityManager->getRepository('Omeka\Entity\User')
                        ->findOneBy(['id' => (int)$userId]);
                    $role = $this->entityManager->getRepository('Teams\Entity\TeamRole')
                        ->findOneBy(['id' => (int)$roleId]);

                    $teamUser = new TeamUser($team, $user, $role);
                    $teamUser->setCurrent(null);
                    $this->entityManager->persist($teamUser);
                endforeach;
                $this->entityManager->flush();
            }

            //TODO: (Done) also add the itemset itself

            $resource_array = array();
            $resource_template_array = array();
            if (isset($request->getPost('itemset')['itemset']['o:itemset'])) {
                foreach ($request->getPost('itemset')['itemset']['o:itemset'] as $item_set_id):
                    if ((int)$item_set_id > 0) {
                        $item_set_id = (int)$item_set_id;

                        //add all items belonging to itemset
                        foreach ($this->api()->search('items', ['item_set_id' => $item_set_id, 'bypass_team_filter' => true])->getContent() as $item):
                            $resource_array[$item->id()] = true;

                            //add all media belonging to item
                            foreach ($this->api()->search('media', ['item_id' => $item->id(), 'bypass_team_filter' => true])->getContent() as $media):
                                $resource_array[$media->id()] = true;
                            endforeach;
                        endforeach;

                    }
                    //add itemset itself
                    $resource = $this->entityManager->getRepository('Omeka\Entity\Resource')
                        ->findOneBy(['id' => $item_set_id]);
                    $team_resource = new TeamResource($team, $resource);

                    $this->entityManager->persist($team_resource);
                endforeach;
            }
            if (isset($request->getPost('itemset')['itemset']['o:user'])) {
                foreach ($request->getPost('itemset')['itemset']['o:user'] as $user_id):
                    if ((int)$user_id > 0) {
                        $user_id = (int)$user_id;

                        //add all of that users items and their media
                        foreach ($this->api()->search('items', ['owner_id' => $user_id, 'bypass_team_filter' => true])->getContent() as $item):
                            $resource_array[$item->id()] = true;
                            foreach ($this->api()->search('media', ['item_id' => $item->id(), 'bypass_team_filter' => true])->getContent() as $media):
                                $resource_array[$media->id()] = true;
                            endforeach;
                        endforeach;

                        //add all of that user's item sets
                        foreach ($this->api()->search('item_sets', ['owner_id' => $user_id, 'bypass_team_filter' => true])->getContent() as $itemset):
                            $resource_array[$itemset->id()] = true;
                        endforeach;

                        $rts = $this->entityManager->getRepository('Omeka\Entity\ResourceTemplate')->findBy(['owner' => $user_id]);
                        foreach ($rts as $rt):
                            $resource_template_array[$rt->getId()] = true;
                        endforeach;
                    }
                endforeach;
            }

            //persist the resources, ie item, item set, media
            foreach (array_keys($resource_array) as $resource_id):
                $resource = $this->entityManager->getRepository('Omeka\Entity\Resource')
                    ->findOneBy(['id' => $resource_id]);
                $team_resource = new TeamResource($team, $resource);
                $this->entityManager->persist($team_resource);
            endforeach;

            //persist the resource templates
            foreach (array_keys($resource_template_array) as $rt_id):
                $resource_template = $this->entityManager->getRepository('Omeka\Entity\ResourceTemplate')
                    ->findOneBy(['id' => $rt_id]);
                $team_rt = new TeamResourceTemplate($team, $resource_template);
                $this->entityManager->persist($team_rt);
            endforeach;
            $this->entityManager->flush();

            //persist the sites (no possibility of duplicates, so don't need to save to associative array)
            if (isset($request->getPost('site')['site']['o:site'])) {
                foreach ($request->getPost('site')['site']['o:site'] as $site_id):
                    $site_id = (int)$site_id;
                    $site = $this->entityManager->getRepository('Omeka\Entity\Site')
                        ->findOneBy(['id' => $site_id]);
                    $team_site = new TeamSite($team, $site);
                    $this->entityManager->persist($team_site);
                endforeach;
                $this->entityManager->flush();
            }

            $successMessage = sprintf("Successfully added the team: '%s'", $data['o:name']);
            $this->messenger()->addSuccess($successMessage);
            return $this->redirect()->toRoute('admin/teams');
        }
        $view = new ViewModel;

//        $userForm->setData($request->getPost());
//        $itemsetForm->setData($request->getPost());
//        $userRoleForm->setData($request->getPost());

        $view->setVariable('form', $form);
        $view->setVariable('userForm', $userForm);
        $view->setVariable('itemsetForm', $itemsetForm);
        $view->setVariable('userRoleForm', $userRoleForm);
        $view->setVariable('roles', $roles);
        $view->setVariable('available_u_array', $all_u_array);
        $view->setVariable('user_roles', $request->getPost('user_role'));

        return $view;
    }

    public function roleAddAction()
    {
        $form = $this->getForm(TeamRoleForm::class);
        $request   = $this->getRequest();
        $view = new ViewModel(['form' => $form]);

        //if it is get, then give them the form
        if (! $request->isPost()){
            return $view;
        }

        //otherwise, set the data
        $form->setData($request->getPost());

        //if the form isn't valid, return it
        if (! $form->isValid()){
            return $view;
        }

        //get the data from the post
        $data = $request->getPost('role');

        $new_role = $this->api($form)->create('team-role', $data);

//        return new ViewModel(['data' => $data]);
        return $this->redirect()->toRoute('admin/teams/roles');


    }




}
