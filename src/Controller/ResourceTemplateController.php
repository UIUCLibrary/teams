<?php


namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\DataType\Manager as DataTypeManager;
use Zend\View\Model\ViewModel;

class ResourceTemplateController extends \Omeka\Controller\Admin\ResourceTemplateController
{
    /**
     * @var entityManager
     */
    protected $entityManager;

    protected $dataTypeManager;

    /**
     * @param DataTypeManager $dataTypeManager
     * @param EntityManager $entityManager
     */
    public function __construct(DataTypeManager $dataTypeManager,EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataTypeManager = $dataTypeManager;
    }
    public function teamResources($resource_type, $query, $user_id, $active = true, $team_id = null)
    {
        //if we are looking for the person's current default team
        if ($active){
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);
        //if we are looking for a different team
        } else{
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'team' => $team_id ]);
        }
        //array to hold the resources
        $resources = array();

        //if the user is assigned to a team
        if ($team_user) {

            //get the id of their active team
            $active_team_id = $team_user->getTeam()->getId();

            //get that team
            $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $active_team_id]);



            //query to get all of the appropriate resource from core omeka
            $q = $this->entityManager->createQuery("SELECT resource_template FROM Omeka\Entity\ResourceTemplate resource_template");

            //get the results from the query as an array
            $item_sets = $q->getArrayResult();

            //set up an array to hold all of the appropriate resources for the join of resource and team reasource
            $team_resources = array();

            $team_resource_collection = $team_entity->getTeamResourceTemplates();
            if (count($team_resource_collection)>0){
                //iterate through the team's arraycollection of resource templates and if their ids match, pull it from the q
                foreach ($team_entity->getTeamResourceTemplates() as $team_resource):
                    //obv here would be a place where you could just use the discriminator to see if it is an itemset
                    if (array_search($team_resource->getResourceTemplate()->getId(), array_column($item_sets, 'id')) ){
                        $team_resources[] = $team_resource;
                    }
                endforeach;


            $per_page = 10;
            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
            $max_i = count($team_resources);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}


            for ($i = $start_i; $i < $end_i; $i++) {

                $resources[] = $this->api()->read($resource_type, $team_resources[$i]->getResourceTemplate()->getId())->getContent();}}

        }else{$team_resources=null;}

        return array('page_resources'=>$resources, 'team_resources'=>$team_resources);



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
            $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);
            if ($old_current){
                $old_current->setCurrent(null);
                //no idea why, but this function fails in this controller only unless the action is flushed first
                $em->flush();}
            $new_current->setCurrent(true);
            $em->flush();


        }
    }


    public function browseAction()
    {
        $this->setBrowseDefaults('owner_name', 'dec');


        $user_id = $this->identity()->getId();

        $response = $this->teamResources('resource_templates', $this->params()->fromQuery(),$user_id);

        $this->paginator(count($response['team_resources']), $this->params()->fromQuery('page'));

        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id);
            return $this->redirect()->toRoute('admin/default',['controller'=>'resourceTemplate', 'action'=>'browse']);

        }

        $view = new ViewModel;
        $view->setVariable('resourceTemplates', $response['page_resources']);
        $view->setVariable('resources', $response['page_resources']);
        $view->setVariable('team_resource', $response['team_resources']);


        return $view;


    }
}