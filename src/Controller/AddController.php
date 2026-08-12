<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Teams\Entity\TeamAsset;
use Teams\Form\TeamForm;
use Teams\Form\TeamRoleForm;
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
            $request = $this->getRequest();

            /** @var TeamForm $form */
            $form = $this->getForm(TeamForm::class);
            $form->setAttribute('id', 'team-add-form');

            $view = new ViewModel([
                'form'                   => $form,
                'team'                   => [],
                'bypassTeamFilterRoles'  => [],
                'user'                   => $this->identity(),
                'isAdd'                  => true,
            ]);

            if (! $request->isPost()) {
                return $view;
            }

            if (! $this->teamAuth()->teamAuthorized($this->identity(), 'add', 'team')) {
                $this->messenger()->addError("You aren't authorized to add teams");
                return $view;
            }

            $postData = $request->getPost()->toArray();
            $form->setData($postData);
            if (! $form->isValid()) {
                $this->messenger()->addFormErrors($form);
                return $view;
            }

            $newTeam = $this->api($form)->create('team', [
                'o:name' => $postData['o:name'] ?? '',
                'o:description' => $postData['o:description'] ?? '',
            ]);

            if (! $newTeam) {
                return $view;
            }

            $teamId = $newTeam->getContent()->id();
            $teamEntity = $this->entityManager->getRepository('Teams\Entity\Team')
                ->findOneBy(['id' => (int) $teamId]);

            // Users and roles
            foreach ($postData['o:team_users'] ?? [] as $teamUser) {
                $this->api()->create('team-user', [
                    'team' => $teamId,
                    'user' => (int) $teamUser['o:user']['o:id'],
                    'role' => (int) $teamUser['o:team_role']['o:id'],
                ]);
            }

            // Sites
            foreach ($postData['teamSites']['o:site'] ?? [] as $siteId) {
                $this->api()->create('team-site', [
                    'team' => $teamId,
                    'site' => (int) $siteId,
                ]);
            }

            // Item assignment job
            $itemPoolStr = $postData['item_pool'] ?? '';
            $itemPoolStr .= '&bypass_team_filter=true';
            parse_str($itemPoolStr, $itemPool);
            if (! empty($postData['item_assignment_action']) && $postData['item_assignment_action'] !== 'no_action') {
                $this->jobDispatcher()->dispatch('Teams\Job\UpdateTeamResources', [
                    'teams' => [$teamId => $itemPool],
                    'action' => $postData['item_assignment_action'],
                ]);
                $this->messenger()->addSuccess('Item assignment in progress. To see the new item count, refresh the page.'); // @translate
            }

            // Item sets
            $recursive = $postData['recursive_item_sets'] ?? false;
            foreach ($postData['item_sets'] ?? [] as $itemSetId) {
                if (count($this->api()->search('team-resource', ['team' => $teamEntity->getId(), 'resource' => $itemSetId])->getContent()) < 1) {
                    $this->api()->create('team-resource', ['team' => $teamEntity->getId(), 'resource' => $itemSetId], [], ['recursive' => $recursive, 'syncSites' => true]);
                }
            }

            // Resource templates
            foreach ($postData['resource_templates'] ?? [] as $templateId) {
                if (count($this->api()->search('team-resource-template', ['team' => $teamEntity->getId(), 'resource-template' => $templateId])->getContent()) < 1) {
                    $this->api()->create('team-resource-template', ['team' => $teamEntity->getId(), 'resource-template' => $templateId]);
                }
            }

            $this->messenger()->addSuccess(sprintf("Successfully added the team: '%s'", $postData['o:name']));
            return $this->redirect()->toUrl($newTeam->getContent()->url());
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
                $successMessage = sprintf("Successfully added the role: '%s'", $data['o:name']);
                $this->messenger()->addSuccess($successMessage);
                return $this->redirect()->toRoute('admin/teams/roles/detail',  ['id' => $newRole->getContent()->id()]);
            } else {
                return $view;
            }
    }
}
