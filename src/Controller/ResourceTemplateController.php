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
use Laminas\View\Model\ViewModel;

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

    protected function getAddEditView()
    {
        $action = $this->params('action');
        $form = $this->getForm(ResourceTemplateForm::class);
        $em = $this->entityManager;
        $user_id = $this->identity()->getId();
        $team_users = $em->getRepository('Teams\Entity\TeamUser')->findBy(['user' => $user_id]);

        $teams = $em->getRepository('Teams\Entity\TeamUser');
        if ($teams->findOneBy(['user'=>$user_id, 'is_current'=>1])){
            $default_team = $teams->findOneBy(['user'=>$user_id, 'is_current'=>1]);
        } elseif ($teams->findBy(['user' => $user_id])){
            $default_team = $teams->findOneBy(['user' => $user_id], ['name']);
        } else {
            $default_team = null;
        }


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
            if ($data['o:title_property']) {
                $data['o:title_property[o:id]'] = $data['o:title_property']->id();
            }
            if ($data['o:description_property']) {
                $data['o:description_property[o:id]'] = $data['o:description_property']->id();
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
                    foreach ($data['o-module-teams:Team'] as $team_id):
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
                    foreach ($data['o-module-teams:Team'] as $team_id):
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
        $view->setVariable('default_team', $default_team);

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
