<?php
namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;
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
        $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$id]);

//        $items_te = $team_entity->getTeamResources()->getValues();
//        $items_omeka = $this->entityManager->getRepository('Omkea\Entity\Item')->findAll();


//        $resource_id = array_column(array_values($items_te),'resource');
//        $view->setVariable('test', $items_te);
//        $count = count($resource_id);
//        $items = array();
        $em = $this->entityManager;
//        $count = $em->createQueryBuilder();
//        $count->select('count(distinct(tr.resource))')
//            ->from('Teams\Entity\TeamResource', 'tr')
//            ->leftJoin('Omeka\Entity\Resource', 'r')
//            ->where('r INSTANCE of Omeka\Entity\Item')
//            ->where('tr.team = ?1');
//        $count->setParameter(1, $this->params('id'));
//        $count = $count->getQuery()->getSingleScalarResult();
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

//        //get the page resources for the page of results the user requested
//        $qb = $em->createQueryBuilder();
//        $q = $qb->select('tr')
//            ->from('Teams\Entity\TeamResource', 'tr')
//            ->where('tr.team = ?1')
//            ->andWhere('tr.resource in (:ids)')
//            ->setParameter('ids', $ids)
//        ;
//        //establish first and last results to get for the page
//        //if a page is listed in the query, start there. Else, start at 0
//        $items_per_page = 10;
//        if ($this->params()->fromQuery()['page']){
//            $offset = ($this->params()->fromQuery()['page']*$items_per_page)-$items_per_page;
//
//        }else{$offset=0;}
//        $limit = $offset + $items_per_page;
//
//        $qb->setFirstResult($offset);
//        $qb->setMaxResults($limit);
//        $qb->setParameter(1, $this->params('id'));
//
//        $team_resources = $q->getQuery()->getResult();
//
//        $items = array();
//        $item_sets = array();
//        $media = array();
//
//
//        foreach ($team_resources as $tr):
//            $tr = $tr->getResource();
//            if ($tr->getResourceName() == 'items') {
//                $items[] = $this->api()->read('items', $tr->getId())->getContent();
//
//            } elseif ($tr->getResourceName() == 'item_sets'){
//                $item_sets[] = $this->api()->read('item_sets', $tr->getId())->getContent();
//            } elseif ($tr->getResourceName() == 'media'){
//                $media[] = $this->api()->read('media', $tr->getId())->getContent();
//            } else {}
//        endforeach;
//        $this->paginator($count, $this->params()->fromQuery('page'));
        $view->setVariable('response', $response);
//        $view->setVariable('items', $items);


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
