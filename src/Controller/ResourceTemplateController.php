<?php


namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\DataType\Manager as DataTypeManager;

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
        if ($active){
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

        } else{
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'team' => $team_id ]);
        }

        $resources = array();
        if ($team_user) {

            $active_team_id = $team_user->getTeam()->getId();

            $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $active_team_id]);




            $q = $this->entityManager->createQuery("SELECT resource FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\ResourceTemplate");
            $item_sets = $q->getArrayResult();
            $team_resources = array();
            foreach ($team_entity->getTeamResources() as $team_resource):
                //obv here would be a place where you could just use the discriminator to see if it is an itemset
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
        $user_id = $this->identity()->getId();

        $response = $this->teamResources('media', $this->params()->fromQuery(),$user_id);

        $this->paginator(count($response['team_resources']), $this->params()->fromQuery('page'));

        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id);
            return $this->redirect()->toRoute('admin/default',['controller'=>'media', 'action'=>'browse']);

        }
        return null;


    }
}