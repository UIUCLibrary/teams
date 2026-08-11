<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Teams\Form\SecondaryResourcesForm;
use Teams\Form\TeamForm;
use Teams\Form\TeamResourcesForm;
use Teams\Form\TeamSitesAddRemoveForm;
use Teams\Service\SitePermissionManager;


class UpdateController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var SitePermissionManager
     */
    protected SitePermissionManager $sitePermissionManager;

    /**
     * @param EntityManager $entityManager
     * @param SitePermissionManager $sitePermissionManager
     */
    public function __construct(EntityManager $entityManager, SitePermissionManager $sitePermissionManager)
    {
        $this->entityManager = $entityManager;
        $this->sitePermissionManager = $sitePermissionManager;
    }

    public function teamUpdateAction()
    {
        $teamId = $this->params()->fromRoute('id');

        // Authorization check before any POST processing.
        if (! $this->teamAuth()->teamAuthorized($this->identity(), 'update', 'team_user', $teamId)) {
            $this->messenger()->addError("You aren't authorized to edit this team");
            return $this->redirect()->toRoute('admin/teams/detail', ['id' => $teamId]);
        }

        $teamRepresentation = $this->api()->read('team', ['id' => $teamId])->getContent();
        $team = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $teamId]);
        $currentSites = $this->api()->search('team-site', ['team' => $teamId], ['returnScalar' => 'site'])->getContent();

        /** @var TeamForm $form */
        $form = $this->getForm(TeamForm::class);
        $form->setAttribute('id', 'team-update-form');
        $form->setAttribute('action', $this->url()->fromRoute('admin/teams/detail/update', ['id' => $teamId]));

        // Pre-populate the form with the current team data.
        $form->setData($teamRepresentation->getJsonLd());

        $resourceForm = $this->getForm(TeamResourcesForm::class)->setAttribute('id', 'team-resources-form');
        $secondaryResourcesForm = $this->getForm(SecondaryResourcesForm::class, ['team_id' => $teamId]);
        $sitesForm = $this->getForm(TeamSitesAddRemoveForm::class, ['team_id' => $teamId]);

        $bypassTeamFilterRoles = $this->settings()->get('teams_filter_bypass_roles');

        $view = new ViewModel([
            'team' => $team,
            'teamRepresentation' => $teamRepresentation,
            'form' => $form,
            'resourceForm' => $resourceForm,
            'secondaryResourcesForm' => $secondaryResourcesForm,
            'sitesForm' => $sitesForm,
            'bypassTeamFilterRoles' => $bypassTeamFilterRoles,
            'id' => $teamId,
            'user' => $this->identity(),
        ]);

        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);
        if (! $form->isValid()) {
            $this->messenger()->addFormErrors($form);
            return $view;
        }

        $this->api($form)->update('team', $teamId, [
            'o:name' => $postData['o:name'],
            'o:description' => $postData['o:description'],
        ]);

        // Remove users no longer in the submitted list, then add or update the rest.
        $teamUsers = $request->getPost('o:team_users', []);
        $formTeamUserIds = array_column(array_column($teamUsers, 'o:user'), 'o:id');

        $oldTeamUserIds = $this->api()->search('team-user', ['team' => $teamId], ['returnScalar' => 'user'])->getContent();
        foreach ($oldTeamUserIds as $oldUserId) {
            if (! in_array($oldUserId, $formTeamUserIds)) {
                $this->api()->delete('team-user', ['team' => $teamId, 'user' => $oldUserId]);
            }
        }
        foreach ($teamUsers as $teamUser) {
            $userId = $teamUser['o:user']['o:id'];
            $roleId = $teamUser['o:team_role']['o:id'];
            $exists = $this->api()->search('team-user', ['team' => $teamId, 'user' => $userId])->getContent();
            if ($exists) {
                $this->api()->update('team-user', ['team' => $teamId, 'user' => $userId], ['role' => $roleId]);
            } else {
                $this->api()->create('team-user', ['team' => $teamId, 'user' => $userId, 'role' => $roleId]);
            }
        }

        // Dispatch a background job for item assignment if an action was requested.
        $resourceForm->setData($postData);
        parse_str($postData['item_pool'] ?? '', $itemPool);
        if (! empty($postData['item_assignment_action']) && $postData['item_assignment_action'] !== 'no_action') {
            $this->jobDispatcher()->dispatch('Teams\Job\UpdateTeamResources', [
                'teams' => [$teamId => $itemPool],
                'action' => $postData['item_assignment_action'],
            ]);
            $this->messenger()->addSuccess('Item assignment in progress. To see the new item count, refresh the page.'); // @translate
        }

        // Add or remove item sets and resource templates.
        $secondaryResourcesForm->setData($postData);
        $recursive = $postData['recursive_item_sets'] ?? false;
        foreach ($postData['remove_item_sets'] ?? [] as $itemSetId) {
            $this->api()->delete('team-resource', [], ['team' => $teamId, 'resource' => $itemSetId], ['recursive' => $recursive, 'syncSites' => true]);
        }
        foreach ($postData['item_sets'] ?? [] as $itemSetId) {
            if (count($this->api()->search('team-resource', ['team' => $teamId, 'resource' => $itemSetId])->getContent()) < 1) {
                $this->api()->create('team-resource', ['team' => $teamId, 'resource' => $itemSetId], [], ['recursive' => $recursive, 'syncSites' => true]);
            }
        }

        // Process resource templates.
        foreach ($postData['remove_resource_templates'] ?? [] as $templateId) {
            $this->api()->delete('team-resource-template', [], ['team' => $teamId, 'resource-template' => $templateId]);
        }
        foreach ($postData['resource_templates'] ?? [] as $templateId) {
            if (count($this->api()->search('team-resource-template', ['team' => $teamId, 'resource-template' => $templateId])->getContent()) < 1) {
                $this->api()->create('team-resource-template', ['team' => $teamId, 'resource-template' => $templateId]);
            }
        }

        // Add or remove site associations.
        $postSites = $postData['teamSites']['o:site'] ?? [];
        foreach ($postSites as $siteId) {
            if (! in_array($siteId, $currentSites)) {
                $this->api()->create('team-site', ['team' => $teamId, 'site' => $siteId]);
            }
        }
        foreach ($currentSites as $siteId) {
            if (! in_array($siteId, $postSites)) {
                $this->api()->delete('team-site', ['team' => $teamId, 'site' => $siteId]);
            }
        }

        $this->sitePermissionManager->syncAllSitePermissions();
        $this->messenger()->addSuccess(sprintf("Successfully updated the %s team", $team->getName()));

        return $this->redirect()->toRoute('admin/teams/detail', ['id' => $teamId]);
    }

    /**
     * Switches the user's active team and updates their default item sites.
     */
    public function currentTeamAction()
    {
        $user_id = $this->identity()->getId();
        $request = $this->getRequest();

        if ($request->isPost()) {
            $data = $request->getPost();
            $em = $this->entityManager;
            $teamUserRepo = $em->getRepository('Teams\Entity\TeamUser');

            $old_current = $teamUserRepo->findOneBy(['user' => $user_id, 'is_current' => 1]);
            $new_current = $teamUserRepo->findOneBy(['user' => $user_id, 'team' => $data['team_id']]);

            if ($old_current) {
                $old_current->setCurrent(null);
                $em->flush();
            }
            if ($new_current) {
                $new_current->setCurrent(true);
                $em->flush();
                $this->sitePermissionManager->updateUserDefaultSites($user_id);
            } else {
                $this->messenger()->addError("Team not found");
            }

            return $this->redirect()->toUrl($data['return_url']);
        }
    }
}
