<?php


namespace Teams\Controller;


use Doctrine\ORM\EntityManager;
use Omeka\DataType\Manager as DataTypeManager;
use Omeka\Form\ResourceTemplateForm;
use Omeka\Stdlib\Message;
use phpDocumentor\Reflection\Types\This;
use Teams\Entity\TeamResourceTemplate;
use Teams\Form\TeamFieldset;
use Teams\Form\TeamSelect;
use Teams\Form\TeamUpdateForm;
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



        //this logic looks at which page they requested, how many items per page, and collects the appropriate results.
        $per_page = 10;

            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
            $max_i = count($team_rts);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}

            $page_resources = array();
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

    protected function getAddEditView()
    {
        $action = $this->params('action');
        $form = $this->getForm(ResourceTemplateForm::class);
        $em = $this->entityManager;
        $user_id = $this->identity()->getId();
        $team_users = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $user_id]);
        $user_teams = array();
        foreach ($team_users as $tu):
            $user_teams[$tu->getTeam()->getId()] = $tu->getTeam()->getName();
        endforeach;
        $teams_rt = $em->getRepository('Teams\Entity\TeamResourceTemplate')->findBy(['resource_template'=>$this->params('id')]);
        $rt_teams = array();
        foreach ($teams_rt as $team_rt):
            $rt_teams[$team_rt->getTeam()->getId()] = $team_rt->getTeam()->getName();
        endforeach;


        if ('edit' == $action) {
            $resourceTemplate = $this->api()
                ->read('resource_templates', $this->params('id'))
                ->getContent();
            $data = $resourceTemplate->jsonSerialize();
            if ($data['o:resource_class']) {
                $data['o:resource_class[o:id]'] = $data['o:resource_class']->id();
            }
            $form->setData($data);
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = ('edit' === $action)
                    ? $this->api($form)->update('resource_templates', $resourceTemplate->id(), $data)
                    : $new_rt = $this->api($form)->create('resource_templates', $data);

                if ('edit' == $action) {


                    $teams_rt = $em->getRepository('Teams\Entity\TeamResourceTemplate')->findBy(['resource_template' => $this->params('id')]);
                    foreach ($teams_rt as $team_rt):
                        $em->remove($team_rt);
                    endforeach;
                    $em->flush();
                    $resource_template = $em->getRepository('Omeka\Entity\ResourceTemplate')->findOneBy(['id' => $this->params('id')]);
                    foreach ($data['team'] as $team_id):
                        $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                        $trt = new TeamResourceTemplate($team, $resource_template);
                        $em->persist($trt);
                    endforeach;

                    //to add back in the teams that the resource belongs to but not the user doesn't
                    foreach (array_diff(array_keys($rt_teams), array_keys($user_teams)) as $team_id):
                        $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                        $trt = new TeamResourceTemplate($team, $resource_template);
                        $em->persist($trt);
                    endforeach;

                    $em->flush();

                } else {

                    $rt_id = $new_rt->getContent()->id();

                    $resource_template = $em->getRepository('Omeka\Entity\ResourceTemplate')->findOneBy(['id' => $rt_id ]);
                    foreach ($data['team'] as $team_id):
                        $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                        $trt = new TeamResourceTemplate($team, $resource_template);
                        $em->persist($trt);
                    endforeach;
                    $em->flush();

                }

                if ($response) {
                    if ('edit' === $action) {
                        $successMessage = 'Resource template successfully updated'; // @translate
                    } else {
                        $successMessage = new Message(
                            'Resource template successfully created. %s', // @translate
                            sprintf(
                                '<a href="%s">%s</a>',
                                htmlspecialchars($this->url()->fromRoute(null, [], true)),
                                $this->translate('Add another resource template?')
                            )
                        );
                        $successMessage->setEscapeHtml(false);
                    }
                    $this->messenger()->addSuccess($successMessage);
                    return $this->redirect()->toUrl($response->getContent()->url());

                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }


        $view = new ViewModel;
        if ($this->getRequest()->isPost()){
            $view->setVariable('data', $data);
        }
        if ('edit' === $action) {
            $view->setVariable('resourceTemplate', $resourceTemplate);
        }
        $view->setVariable('propertyRows', $this->getPropertyRows());
        $view->setVariable('form', $form);
        $view->setVariable('rt_teams', $rt_teams);
        $view->setVariable('user_teams', $user_teams);

        return $view;
    }

    public function editAction()
    {
        return $this->getAddEditView();
    }

    public function addAction()
    {
        return $this->getAddEditView();
    }



}