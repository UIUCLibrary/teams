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
use Teams\Entity\TeamUser;
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

        $view = new ViewModel(
            [
                'form' => $form,
                'itemsetForm' => $itemsetForm,
                'resourceForm' => $resourceForm,
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
            $team = $this->entityManager->getRepository('Teams\Entity\Team')
                ->findOneBy(['id' => (int)$newTeam->getContent()->id()]);
            if ($request->getPost('o:team_users')) {
                foreach ($request->getPost('o:team_users') as $team_user):
                    $user = $this->entityManager->getRepository('Omeka\Entity\User')
                        ->findOneBy(['id' => (int)$team_user['o:user']['o:id']]);
                    $role = $this->entityManager->getRepository('Teams\Entity\TeamRole')
                        ->findOneBy(['id' => (int)$team_user['o:team_role']['o:id']]);

                    $teamUser = new TeamUser($team, $user, $role);
                    $teamUser->setCurrent(null);
                    $this->entityManager->persist($teamUser);
                endforeach;
                $this->entityManager->flush();
            }

            $resource_array = array();
            $resource_template_array = array();
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

            //persist the assets
            foreach (array_keys($asset_array) as $asset_id):
                $asset = $this->entityManager->getRepository('Omeka\Entity\Asset')
                    ->findOneBy(['id' => $asset_id]);
                $team_asset = new TeamAsset($team, $asset);
                $this->entityManager->persist($team_asset);
            endforeach;

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
