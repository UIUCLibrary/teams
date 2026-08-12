<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Teams\Form\TeamForm;
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

        /** @var TeamForm $form */
        $form = $this->getForm(TeamForm::class, ['team_id' => $teamId]);
        $form->setAttribute('id', 'team-update-form');
        $form->setAttribute('action', $this->url()->fromRoute('admin/teams/detail/update', ['id' => $teamId]));

        // Pre-populate the form with the current team name and description.
        $form->setData($teamRepresentation->getJsonLd());

        $bypassTeamFilterRoles = $this->settings()->get('teams_filter_bypass_roles');

        $view = new ViewModel([
            'team' => $team,
            'teamRepresentation' => $teamRepresentation,
            'form' => $form,
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
            'o:name'               => $postData['o:name'],
            'o:description'        => $postData['o:description'],
            'o:team_users'         => $postData['o:team_users'] ?? [],
            'o:team_sites'         => $postData['team_sites'] ?? [],
            'o:item_sets'          => $postData['item_sets'] ?? [],
            'o:recursive_item_sets' => $postData['recursive_item_sets'] ?? false,
            'o:resource_templates' => $postData['resource_templates'] ?? [],
        ]);

        // Dispatch a background job for item assignment if an action was requested.
        parse_str($postData['item_pool'] ?? '', $itemPool);
        if (! empty($postData['item_assignment_action']) && $postData['item_assignment_action'] !== 'no_action') {
            $this->jobDispatcher()->dispatch('Teams\Job\UpdateTeamResources', [
                'teams' => [$teamId => $itemPool],
                'action' => $postData['item_assignment_action'],
            ]);
            $this->messenger()->addSuccess('Item assignment in progress. To see the new item count, refresh the page.'); // @translate
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
