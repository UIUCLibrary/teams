<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Form\ResourceForm;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use phpDocumentor\Reflection\Types\This;
use Teams\Entity\TeamAsset;
use Teams\Entity\TeamResource;
use Teams\Entity\TeamResourceTemplate;
use Teams\Entity\TeamSite;
use Teams\Form\SecondaryResourcesForm;
use Teams\Form\TeamItemSetForm;
use Teams\Form\TeamResourcesForm;
use Teams\Form\TeamRoleForm;
use Teams\Form\TeamForm;
use Teams\Form\TeamUserForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class AddController extends AbstractActionController
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
        $request   = $this->getRequest();

        $form = $this->getForm(TeamForm::class);
        $userForm = $this->getForm(TeamUserForm::class);
        $itemsetForm = $this->getForm(TeamItemSetForm::class);
        $resourceForm = $this->getForm(TeamResourcesForm::class)->setAttribute('id', 'team-resources-form');
        $secondaryResourcesForm = $this->getForm(SecondaryResourcesForm::class,
            ['team_id'=>0]
        );
        $view = new ViewModel(
            [
                'form' => $form,
                'itemsetForm' => $itemsetForm,
                'resourceForm' => $resourceForm,
                'secondaryResourcesForm' => $secondaryResourcesForm,
            ]
        );

        //if it is get, then give them the form
        if (! $request->isPost()) {
            return $view;
        }

        if (! $this->teamAuth()->teamAuthorized($this->identity(), 'add', 'team')){
            $this->messenger()->addError("You aren't authorized to add teams");
            return $view;
        }
        //TODO: turn the section where user+role are added into a form so it can be populated below
        $form->setData($request->getPost());
        $userForm->setData($request->getPost());
        $itemsetForm->setData($request->getPost());
        $resourceForm->setData($request->getPost());
        if (! $form->isValid()) {
            return $view;
        }

        $data = $request->getPost('team');

        $newTeam = $this->api($form)->create('team', $data);

        //add the users, resources and sites to the team
        if ($newTeam) {
            $teamEntity = $this->entityManager->getRepository('Teams\Entity\Team')
                ->findOneBy(['id' => (int)$newTeam->getContent()->id()]);
            if ($request->getPost('o:team_users')) {
                foreach ($request->getPost('o:team_users') as $team_user) {
                    $this->api()->create('team-user', [
                        'team' => $newTeam->getContent()->id(),
                        'user' => (int) $team_user['o:user']['o:id'],
                        'role' => (int) $team_user['o:team_role']['o:id'],
                    ]);
                }
            }

            //persist the sites (no possibility of duplicates, so don't need to save to associative array)
            if (isset($request->getPost('site')['site']['o:site'])) {
                foreach ($request->getPost('site')['site']['o:site'] as $site_id):
                    $site_id = (int)$site_id;
                    $site = $this->entityManager->getRepository('Omeka\Entity\Site')
                        ->findOneBy(['id' => $site_id]);
                    $team_site = new TeamSite($teamEntity, $site);
                    $this->entityManager->persist($team_site);
                endforeach;
                $this->entityManager->flush();
            }
            $asset_array = array();
            $formData = $this->params()->fromPost();
            $formData['item_pool'] .= "&bypass_team_filter=true";
            $resourceForm->setData($formData);
            parse_str($formData['item_pool'], $itemPool);
            if ($formData['item_assignment_action'] && $formData['item_assignment_action'] !== 'no_action') {
                $this->jobDispatcher()->dispatch('Teams\Job\UpdateTeamResources', [
                    'teams' => [$newTeam->getContent()->id() => $itemPool],
                    'action' => $formData['item_assignment_action'],
                ]);
                $this->messenger()->addSuccess('Item assignment in progress. To see the new item count, refresh the page.'); // @translate
            }

            $secondaryResourcesForm->setData($formData);
            $recursive = $formData['recursive_item_sets'] ?? false;
            if (isset($formData['item_sets'])) {
                foreach ($formData['item_sets'] as $item_set_id) {
                    $exists = $this->api()->search('team-resource', ['team' => $teamEntity->getId(), 'resource' => $item_set_id]);
                    if (count($exists->getContent()) < 1) {
                        $this->api()->create('team-resource', ['team' => $teamEntity->getId(), 'resource' => $item_set_id],[], ['recursive'=>$recursive, 'syncSites'=>true]);
                    }
                }
            }
            if (isset($formData['resource_templates'])){
                foreach ($formData['resource_templates'] as $resource_template_id) {
                    if (count($this->api()->search('team-resource-template', ['team'=>$teamEntity->getId(), 'resource-template'=>$resource_template_id])->getContent())<1){
                        $this->api()->create('team-resource-template', ['team'=>$teamEntity->getId(), 'resource-template'=>$resource_template_id]);
                    }
                }
            }



            //TODO: add assets to secondaryResourcesForm.

            // persist the assets
            foreach (array_keys($asset_array) as $asset_id):
                $asset = $this->entityManager->getRepository('Omeka\Entity\Asset')
                    ->findOneBy(['id' => $asset_id]);
                $team_asset = new TeamAsset($teamEntity, $asset);
                $this->entityManager->persist($team_asset);
            endforeach;


            $successMessage = sprintf("Successfully added the team: '%s'", $data['o:name']);
            $this->messenger()->addSuccess($successMessage);
            return $this->redirect()->toUrl($newTeam->getContent()->url());
        }
        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('itemsetForm', $itemsetForm);
        $view->setVariable('team_users', $request->getPost('o:team_users'));
        $view->setVariable('resourceForm', $resourceForm);

        return $view;
    }

    public function roleAddAction()
    {
        $form = $this->getForm(TeamRoleForm::class);
        $request   = $this->getRequest();
        $view = new ViewModel(['form' => $form]);

        //if it is get, then give them the form
        if (! $request->isPost()) {
            return $view;
        }

        if (! $this->teamAuth()->teamAuthorized($this->identity(), 'add', 'role')){
            $this->messenger()->addError("You aren't authorized to add roles");
            return $view;
        }

        //otherwise, set the data
        $form->setData($request->getPost());

        //get the data from the post
        $data = $request->getPost('role');

        //if the form isn't valid, return it
        if (!$form->isValid()) {
            return $view;
        }

        $newRole = $this->api($form)->create('team-role', $data);

        if ($newRole) {
            //        return new ViewModel(['data' => $data]);
            $successMessage = sprintf("Successfully added the role: '%s'", $data['o:name']);
            $this->messenger()->addSuccess($successMessage);
            return $this->redirect()->toRoute('admin/teams/roles/detail',  ['id' => $newRole->getContent()->id()]);
        } else {
            return $view;
        }
    }
}
