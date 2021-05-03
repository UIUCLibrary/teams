<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\Api\Request;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\DateTime;
use Laminas\EventManager\Event;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\User;
use Teams\Entity\Team;
use Teams\Entity\TeamRole;
use Omeka\Permissions\Acl;



class IndexController extends AbstractActionController
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

    public function resourcesAction()
    {
        $site = $this->currentSite();
        $site_id = $site->id();
        $em = $this->entityManager;
        $site_teams = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $site_resources = array();
        $site_resource_templates = array();
        $get_item_sets = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\ItemSet");
        $get_items = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Item");
        $get_media = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Media");

        //thought that a scalar would be easier but from what I can tell in this case getScalarResult===getArrayResult===getResult
        //leaving it this way because it sounds closest to the kind of data I actually want to get out
        $all_item_sets = $get_item_sets->getScalarResult();
        $all_items = $get_items->getScalarResult();
        $all_media = $get_media->getScalarResult();

        //combine all of the resources from all of the teams the site is associated with
        //maintaining the distinction between resources and resource templates from core omeka
        foreach ($site_teams as $site_team):
            $team_resources = $site_team->getTeam()->getTeamResources()->toArray();
            $team_resource_templates = $site_team->getTeam()->getTeamResourceTemplates()->toArray();
            $site_resources = array_merge($site_resources, $team_resources);
            $site_resource_templates = array_merge($site_resource_templates, $team_resource_templates);

        endforeach;

        //returns the id when either a TeamResource object or row from a doctrine result set is provided
        $getIds = function ($resource){
            if (is_object($resource)){
                return $resource->getResource()->getId();
            }elseif(is_array($resource)){
                return $resource['id'];
            }else{return null;}
        };

        //if there is a way to do this by testing TeamResources with INSTANCE OF it would be simpler, but I was not
        //able to get that to work. So using an array of Omeka itemsets as discriminator

        //get just the resource ids from TeamResources associated with the site
        $team_res_ids = array_map($getIds, $site_resources);

        //get the ids from all omeka item sets
        $all_item_set_ids = array_map($getIds, $all_item_sets);

        //get the ids from all omeka items
        $all_item_ids = array_map($getIds, $all_items);

        //get the ids from all omeka media
        $all_media_ids = array_map($getIds, $all_media);

        //filter out just the site's itemsets
        $site_item_sets = array_intersect($all_item_set_ids, $team_res_ids);

        //filterout just the site's items
        $site_items = array_intersect($all_item_ids, $team_res_ids);

        //filter out just the site's media
        $site_media = array_intersect($all_media_ids, $team_res_ids);




        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $itemPool = $formData;
                unset($itemPool['form_csrf']);
                unset($itemPool['site_item_set']);

                $itemSets = isset($formData['o:site_item_set']) ? $formData['o:site_item_set'] : [];

                $updateData = ['o:item_pool' => $itemPool, 'o:site_item_set' => $itemSets];
                $response = $this->api($form)->update('sites', $site->id(), $updateData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site resources successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $itemCount = $this->api()
            ->search('items', ['limit' => 0, 'site_id' => $site->id()])
            ->getTotalResults();
        $itemSets = [];
        foreach ($site->siteItemSets() as $siteItemSet) {
            $itemSet = $siteItemSet->itemSet();
            $owner = $itemSet->owner();
            $itemSets[] = [
                'id' => $itemSet->id(),
                'title' => $itemSet->displayTitle(),
                'email' => $owner ? $owner->email() : null,
            ];
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('itemCount', $itemCount);
        $view->setVariable('itemSets', $itemSets);
        $view->setVariable('site_resources', $site_resources);
        $view->setVariable('site_media', $site_media);
        $view->setVariable('site_items', $site_items);
        $view->setVariable('site_item_sets', $site_item_sets);
        $view->setVariable('site_teams', $site_teams);
        return $view;
    }


    public function allAction(){

        $view = new ViewModel;
        $teams = $this->entityManager->getRepository('Teams\Entity\Team')->findAll();
        $super_admin = $this->entityManager->getRepository('Omeka\Entity\User')
            ->findOneBy(['id' => 1, 'role' => 'global_admin']);
        $user = $this->identity();


        $view->setVariable('teams', $teams);
        $view->setVariable('super_admin', $super_admin);
        $view->setVariable('user', $user);

        return $view;




    }
    public function permDeleteAction()
    {
        echo 'nothing went wrong';


    }
    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {

                $entityManager = $this->entityManager;


                $user_id = $this->identity()->getId();

                $team_id = $entityManager
                    ->getRepository('Teams\Entity\TeamUser')
                    ->findOneBy(['is_current'=>true, 'user'=>$user_id])
                    ->getTeam()->getId();

                //array of media ids
                $media_ids = [];

                //date to update last modified
                $datetime = new \DateTime('now');
                foreach ($this->api()->read('items', $this->params('id'))->getContent()->media() as $media):
                    $media_ids[] = $media->id();
                endforeach;

                $entity = $entityManager
                    ->getRepository('Teams\Entity\TeamResource')
                    ->findOneBy(['team'=>$team_id, 'resource'=> (int) $this->params('id')]);

                $request = new Request('delete', 'team_resource');
                $event = new Event('api.hydrate.pre', $this, [
                    'entity' => $entity,
                    'request' => $request,
                ]);
                $this->getEventManager()->triggerEvent($event);


                if ($entity){
                    $entity->getResource()->setModified($datetime);
                    $entityManager->remove($entity);

                    //remove associated media from the team
                    foreach ($media_ids as $media_id):
                        $tr = $entityManager->getRepository('Teams\Entity\TeamResource')
                            ->findOneBy(['team' => $team_id, 'resource' => $media_id]);
                        if ($tr){
                            $entityManager->remove($tr);
                            $this->messenger()->addSuccess('Associated Media successfully removed from your team.'); // @translate
                            $entityManager->getRepository('Omeka\Entity\Resource')
                                ->findOneBy(['id'=>$media_id])->setModified($datetime);
                        }
                    endforeach;
                    $entityManager->flush();
                    $this->messenger()->addSuccess('Item successfully removed from your team.'); // @translate
                    $this->messenger()->addSuccess('Item remains available to other teams if they are linked to it.'); // @translate
                    $this->messenger()->addSuccess('Item will be deleted after x days   '); // @translate

                } else{
                    $this->messenger()->addError('something went wrong'); // @translate

                }

//                $response = $this->api($form)->delete('items', $this->params('id'));
//                if ($response) {
//                    $this->messenger()->addSuccess('Item successfully deleted'); // @translate
//                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/default',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('admin', ['action'=>'browse', 'controller' => 'item']);
        }

        $entityManager = $this->entityManager;

        $user_id = $this->identity()->getId();

        $team_id = $entityManager
            ->getRepository('Teams\Entity\TeamUser')
            ->findOneBy(['is_current'=>true, 'user'=>$user_id])
            ->getTeam()->getId();

        $request = $this->getRequest();

        $resource_ids = $request->getPost()['resource_ids'];
        for ($i= 0; $i< count($resource_ids); $i++){
            $entity = $this->entityManager->getRepository('Teams\Entity\TeamResource')
                ->findOneBy(['team'=>$team_id, 'resource'=>$resource_ids[$i]]);
            $entityManager->remove($entity);
    }


        $entityManager->flush();
        return $this->redirect()->toRoute('admin', ['controller'=>'item']);


    }

    public function changeCurrentTeamAction($user_id)
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin');
        } else {
            $data = $request->getPost();
            $em = $this->entityManager;
            $team_user = $em->getRepository('Teams\Entity\TeamUser');
            $old_current = $team_user->findOneBy(['user' => $user_id, 'is_current' => true]);
            if ($old_current){
                $old_current->setCurrent(null);
            }
            $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);
            $new_current->setCurrent(true);
            $em->flush();


        }
    }

    public function indexAction()
    {
        $user_id = $this->identity()->getId();

        //post requests from this page should be the user changing their team
        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id);
            $this->redirect()->toRoute('admin/teams');
        }



        $view = new ViewModel;
        $user_id = $this->identity()->getId();
        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser');
        $user_teams = $team_user->findBy(['user'=>$user_id]);
        if ( $team_user->findOneBy(['user'=>$user_id,'is_current'=>true])){
            $current_team = $team_user->findOneBy(['user'=>$user_id,'is_current'=>true])->getTeam();
        } elseif ($team_user->findOneBy(['user'=>$user_id])){
            $current_team = $team_user->findOneBy(['user'=>$user_id]);
            $current_team->setCurrent(true);
            $this->entityManager->flush();
            $current_team = $current_team->getTeam();
        }


         else {
             $current_team = 'None';
        }

        $view->setVariable('current_team', $current_team);
        $view->setVariable('user_teams', $user_teams);
        $view->setVariable('user_id', $user_id);
        $view->setVariable('data', $this->getRequest()->getPost());




        return $view;
    }
    public function teamResources($resource_type, $query, $user_id, $active = true, $team_id = null)
    {
        if ($team_id) {

            $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);




            $q = $this->entityManager->createQuery("SELECT resource FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Item");
            $item_sets = $q->getArrayResult();
            $team_resources = array();
            foreach ($team_entity->getTeamResources() as $team_resource):
                //obv here would be a place where you could just use the discriminator to see if it is an item
                if (array_search($team_resource->getResource()->getId(), array_column($item_sets, 'id')) ){
                    $team_resources[] = $team_resource;
                }
            endforeach;
            $per_page = 10;
            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
//            $tr = $team_entity->getTeamResources();
            $max_i = count($team_resources);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}
//            $tr = $team_entity->getTeamResources();
            for ($i = $start_i; $i < $end_i; $i++) {

                $resources[] = $this->api()->read($resource_type, $team_resources[$i]->getResource()->getId())->getContent();}

        }else{$team_resources=null;}

        return array('page_resources'=>$resources, 'team_resources'=>$team_resources);



    }

    public function teamDetailAction()
    {
        $view = new ViewModel;
        $id = $this->params()->fromRoute('id');
        $response = $this->api()->read('team', ['id' => $id]);

        $em = $this->entityManager;

        $resources = [
            'items'=> ['count' => 0, 'entity' => 'Item', 'team_entity' => 'TeamResource', 'fk' => 'resource'],
            'item sets' => ['count' => 0, 'entity' => 'ItemSet', 'team_entity' => 'TeamResource', 'fk' => 'resource'],
            'media' => ['count' => 0, 'entity' => 'Media', 'team_entity' => 'TeamResource', 'fk' => 'resource'],
            'resource templates' => ['count' => 0, 'entity' => 'ResourceTemplate', 'team_entity' => 'TeamResourceTemplate', 'fk' => 'resource_template'],
            'sites' => ['count' => 0, 'entity' => 'Site', 'team_entity' => 'TeamSite', 'fk' => 'site']
        ];

        foreach ($resources as $key => $resource):
        //I imagine this as like a subquery that gets the list of item ids
            $sub_query = $em->createQueryBuilder();
            $sub_query->select('r.id')
                ->from('Omeka\Entity\\' . $resource['entity'], 'r');

            $ids = $sub_query->getQuery()->getArrayResult();

            //get the count of the total number of team items
            $qb = $em->createQueryBuilder();

            $qb->select('count(tr.' . $resource['fk'] . ')')
                ->from('Teams\Entity\\' . $resource['team_entity'], 'tr')
                ->where('tr.team = ?1')
                ->andWhere('tr.' . $resource['fk'] . ' in (:ids)')
                ->setParameter('ids', $ids)
            ;
            $qb->setParameter(1, $this->params('id'));
            $resources[$key]['count'] += $qb->getQuery()->getSingleScalarResult();
        endforeach;

        $view->setVariable('resources', $resources);


        $view->setVariable('response', $response);


        return $view;
    }

    public function roleDetailAction()

    {
        $id = $this->params()->fromRoute('id');
        $response = $this->api()->read('team-role', ['id' => $id]);
        return new ViewModel(['response'=>$response]);


    }
    public function roleIndexAction()
    {

        $view = new ViewModel;

        $response = $this->entityManager->getRepository('Teams\Entity\TeamRole')->findAll();
//        $response = $this->api()->search('team');
        $routeMatch = $this->getEvent()->getRouteMatch()->getMatchedRouteName();

        $view->setVariable('response', $response);
        $view->setVariable('route', $routeMatch);
        return $view;

    }

    //delete works. update, read, and create do not. Get errors like this:
    // Doctrine\DBAL\Exception\NotNullConstraintViolationException
    //An exception occurred while executing 'INSERT INTO team_user (team_id, user_id_id, team_user_role)
    // VALUES (?, ?, ?)' with params [null, null, null]: SQLSTATE[23000]: Integrity constraint violation:
    // 1048 Column 'user_id_id' cannot be null
    public function usersAction(){


        $team_users = $this->api()->search('team-user');
        $users = $this->api()->search('users');
        $view = new ViewModel(['users'=> $users, 'team_users'=>$team_users]);
        return $view;
//        $view->setVariable('response', $response);


    }



}
