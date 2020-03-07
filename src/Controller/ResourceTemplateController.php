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

        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);
        // TODO add if teamuser

        $team = $team_user->getTeam();
        $team_resource_templates = $team->getTeamResourceTemplates();

        $team_rts = array();


        foreach ($team_resource_templates as $rt):
            $team_rts[] =  $this->api()->read('resource_templates', $rt->getResourceTemplate()->getId())->getContent();
        endforeach;




        $per_page = 10;
            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
            $max_i = count($team_rts);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}


            for ($i = $start_i; $i < $end_i; $i++) {

                $page_resources[] = $team_rts[$i];
            }


        return array('page_resources'=>$page_resources, 'team_resources'=>$team_rts);



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
        $view->setVariable('resources', $response['page_resources']);
        $view->setVariable('team_resource', $response['team_resources']);
        $view->setVariable('resourceTemplates', $response['team_resources']);






            return $view;


    }
}