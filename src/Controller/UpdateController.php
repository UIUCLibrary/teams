<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Omeka\Api\Request;
use Teams\Entity\TeamSite;
use Teams\Entity\TeamUser;
use Teams\Form\SecondaryResourcesForm;
use Teams\Form\TeamItemsetAddRemoveForm;
use Teams\Form\TeamResourcesForm;
use Teams\Form\TeamSitesAddRemoveForm;
use Teams\Form\TeamDetailsForm;
use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ArrayObject;
use Laminas\View\Model\ViewModel;
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

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws NonUniqueResultException
     */
    public function teamUpdateAction()
    {

        $team_id = $this->params()->fromRoute('id');
        $team = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
        $team_sites = $this->entityManager
            ->getRepository('Teams\Entity\TeamSite')
            ->findBy(['team'=>$team_id]);

        $current_sites = [];
        $valueOptions = [];

//        get current sites for the team and populate the sites chosen select element
//        set up the sites form TODO:refactor most of this into the form, which is only used here
        foreach ($team_sites as $team_site) {
            $current_sites[] = $team_site->getSite()->getId();
        }

        $all_sites = $this->api()->search('sites', ['bypass_team_filter'=>true])->getContent();
        foreach ($all_sites as $site) {
            if ($site->owner()) {
                $owner = $site->owner()->name();
            } else {
                $owner = 'No One';
            }
            $site_name = $site->title() . ' (' . $owner . ')';
            $site_id = $site->id();


            $valueOption = [];
            $valueOption['value'] = $site_id;
            $valueOption['label'] = $site_name;
            if (in_array($site_id, $current_sites)) {
                $valueOption['attributes'] = ['selected' => true];
            }
            $valueOptions[] = $valueOption;
        }

        $sitesForm = $this->getForm(TeamSitesAddRemoveForm::class);
        $sites = $sitesForm->get('teamSites')->get('o:site');
        $sites->setAttribute('multiple', true);
        $sites->setAttribute('id', 'sites');

        $sites->setEmptyOption('None');
        $sites->setValueOptions($valueOptions);


        //set up the item set form
        $itemsetForm = $this->getForm(TeamItemsetAddRemoveForm::class);
        $user = $this->identity();
        //TODO rename this to TeamDetail form or find a way to string these all together
        $teamDetailsForm = $this->getForm(TeamDetailsForm::class);
        $data = $this->api()->read('team', ['id'=>$team_id])->getContent();

        //TODO (refactor) this is probably a stupid way to do this

        //get all of the users and put them in an associative array id:name
        $all_u_array = array();
        $all_u_collection = $this->api()->search('users')->getContent();
        foreach ($all_u_collection as $u):
            $all_u_array[$u->id()] = $u->name();
        endforeach;

        //get the team's users and put them in an associative array id:name
        $team_u_array = array();
        $team_u_collection = $this->api()->read('team', ['id'=>$team_id])->getContent()->teamUsers();
        foreach ($team_u_collection as $team_user):
            $team_u_array[$team_user->getUser()->getId()] = $team_user->getUser()->getName();
        endforeach;

        //get the users available to be added to the team
        $available_u_array = array_diff($all_u_array, $team_u_array);

        // Build a name => id map for the view's role selector.
        // Using api()->search() avoids loading partial entities into the Doctrine
        // identity map, which was the root cause of the canAddSitePages=false bug.
        $roleRepresentations = $this->api()->search('team-role')->getContent();
        $roles = [];
        foreach ($roleRepresentations as $roleRep) {
            $roles[$roleRep->name()] = $roleRep->id();
        }

        //create an array object to hold the contents to pre-fill the form with
        //TODO (emulate) this is the procedure to use to populate forms. Copy this.
        $fill = new ArrayObject;
        $fill['o:name'] = $data->getJsonLd()['o:name'];
        $fill['o:description'] = $data->getJsonLd()['o:description'];
        $teamDetailsForm->bind($fill);

        //is it a post request?
        //TODO (refactor) clean up this, only send what is needed
        $request = $this->getRequest();

        $resourceForm = $this->getForm(TeamResourcesForm::class)->setAttribute('id', 'team-resources-form');
        $secondaryResourcesForm = $this->getForm(SecondaryResourcesForm::class,
        ['team_id'=>$team_id]
        );

        $bypass_team_filter_roles = $this->settings()->get('teams_filter_bypass_roles');
        $view = new ViewModel([
            'team'=>$team,
            'form' => $teamDetailsForm,
            'resourceForm' => $resourceForm,
            'secondaryResourcesForm' => $secondaryResourcesForm,
            'bypassTeamFilterRoles' => $bypass_team_filter_roles,
            'id' => $team_id,
            'roles'=> $roles,
            'all_u_collection' => $all_u_collection,
            'team_u_collection' => $team_u_collection,
            'team_u_array'=>$team_u_array,
            'available_u_array'=>$available_u_array,
            'user' => $user,
            'itemsetForm' => $itemsetForm,
            'sitesForm' => $sitesForm,
        ]);
        if (! $request->isPost()) {
            return $view;
        }


        $post_data = $request->getPost();
        if (!$this->teamAuth()->teamAuthorized($this->identity(), 'update', 'team_user', $team_id)) {
            $this->messenger()->addError("You aren't authorized to change the team details");
            return $view;
        } else {
            $this->api()->update('team', $team_id, ['o:name' => $post_data['o:name'], 'o:description' => $post_data['o:description']]);
        }
        if (!$this->teamAuth()->teamAuthorized($this->identity(), 'update', 'team_user', $team_id)) {
            $this->messenger()->addError("You aren't authorized to change team members");
            return $view;
        } else {
            $teamUsers = $request->getPost('o:team_users');
            //remove team users not in the form
            $formTeamUsers = array();
            foreach ($teamUsers as $teamUser){
                $formTeamUsers[] = $teamUser['o:user']['o:id'];
            }

            $oldTeamUsers= $this->api()->search('team-user', ['team'=>$team_id], ['returnScalar'=>'user'])->getContent();
            foreach ($oldTeamUsers as $oldTeamUser) {
                if (!in_array($oldTeamUser,$formTeamUsers)) {
                    $this->api()->delete('team-user',['team'=>$team_id, 'user'=>$oldTeamUser]);
                }
            }
            //add team users or update permissions
            foreach ($teamUsers as $teamUser) {
                //using search instead of read because read will throw a not found error instead of returning empty
                $teamUserExists = $this->api()->search('team-user', ['team'=>$team_id, 'user'=>$teamUser['o:user']['o:id']])->getContent();

                if ($teamUserExists){
                    $this->api()->update('team-user', ['team' => $team_id, 'user' => $teamUser['o:user']['o:id']], ['role' => $teamUser['o:team_role']['o:id']]);
                } else {
                    $this->api()
                        ->create('team-user',
                            [
                                'team'=>$team_id,
                                'user'=>$teamUser['o:user']['o:id'],
                                'role'=>$teamUser['o:team_role']['o:id']
                            ]);
                }
            }
        }

        //TODO:need to update this for users who have the update and bypass_team_filter
        if (! $this->teamAuth()->teamAuthorized($this->identity(), 'update', 'team', $team_id)){
            $this->messenger()->addError("You aren't authorized to change this team");
            return $view;
        } else {

            //process items
            $formData = $this->params()->fromPost();
            $resourceForm->setData($formData);
            parse_str($formData['item_pool'], $itemPool);
            if ($formData['item_assignment_action'] && $formData['item_assignment_action'] !== 'no_action') {
                $this->jobDispatcher()->dispatch('Teams\Job\UpdateTeamResources', [
                    'teams' => [$team_id => $itemPool],
                    'action' => $formData['item_assignment_action'],
                ]);
                $this->messenger()->addSuccess('Item assignment in progress. To see the new item count, refresh the page.'); // @translate
            }

            //process item sets and resource templates

            //remove item sets
            $secondaryResourcesForm->setData($formData);

            $recursive = $formData['recursive_item_sets'] ?? false;
            if (isset($formData['remove_item_sets'])){
                $remove_item_sets = $formData['remove_item_sets'];
                foreach ($remove_item_sets as $item_set_id) {
                    //todo: delete expects the id to be in the second parameter, for now just leaving empty because team resource uses a composite key
                    $this->api()->delete('team-resource', [], ['team' => $team_id, 'resource' => $item_set_id],['recursive'=>$recursive, 'syncSites' => true]);
                }
            }
            //add item sets
            if (isset($formData['item_sets'])) {
                foreach ($formData['item_sets'] as $item_set_id) {
                    //todo: implement the read operation
                    $exists = $this->api()->search('team-resource', ['team' => $team_id, 'resource' => $item_set_id]);
                    if (count($exists->getContent()) < 1) {
                        $this->api()->create('team-resource', ['team' => $team_id, 'resource' => $item_set_id], [], ['recursive' => $recursive, 'syncSites' => true]);
                    }
                }
            }

            //remove resource templates
            $secondaryResourcesForm->setData($formData);
            if (isset($formData['remove_resource_templates'])){
                foreach ($formData['remove_resource_templates'] as $resource_template_id) {
                    //todo: delete expects the id to be in the second parameter, for now just leaving empty because team resource uses a composite key
                    $this->api()->delete('team-resource-template', [], ['team' => $team_id, 'resource-template' => $resource_template_id]);
                }
            }
            //add resource templates
            if (isset($formData['resource_templates'])){
                foreach ($formData['resource_templates'] as $resource_template_id) {
                    if (count($this->api()->search('team-resource-template', ['team'=>$team_id, 'resource-template'=>$resource_template_id])->getContent())<1){
                        $this->api()->create('team-resource-template', ['team'=>$team_id, 'resource-template'=>$resource_template_id]);
                    }
                }
            }

            $em = $this->entityManager;
            //handle new sites
            foreach ($post_data['teamSites']['o:site'] as $site) {
                if (!in_array($site, $current_sites)) {
                    $site = $em->getRepository('Omeka\Entity\Site')->findOneBy(['id'=>$site]);
                    $ts = new TeamSite($team, $site);
                    $request = new Request('create', 'team_site');
                    $event = new Event('api.hydrate.pre', $this, [
                        'entity' => $ts,
                        'request' => $request,
                    ]);
                    $this->getEventManager()->triggerEvent($event);

                    $em->persist($ts);
                }
            }

            //handle removed sites
            foreach ($current_sites as $site) {
                if (!in_array($site, $post_data['teamSites']['o:site'])) {
                    $ts = $em->getRepository('Teams\Entity\TeamSite')->findOneBy(['team'=>$team_id, 'site'=>$site]);
                    $request = new Request('delete', 'team_site');
                    $event = new Event('api.hydrate.pre', $this, [
                        'entity' => $ts,
                        'request' => $request,
                    ]);
                    $this->getEventManager()->triggerEvent($event);
                    $em->remove($ts);
                }
            }
            $em->flush();
        }
        $this->sitePermissionManager->syncAllSitePermissions();
        $successMessage = sprintf("Successfully updated the %s team", $team->getName());
        $this->messenger()->addSuccess($successMessage);

        return $this->redirect()->toRoute('admin/teams/detail',['id'=>$team_id]);
    }

    /**
     * Adds a user to teams via the user-edit form.
     *
     * TODO: Role id=1 is hardcoded here. Should be made configurable or
     * resolved to a named default role rather than relying on a magic integer.
     */
    public function userAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $user_teams = $data['user-information']['o-module-teams:Team'];
            $user_id = $this->params('id');
            $em = $this->entityManager;

            foreach ($user_teams as $team_id):
                $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['id' => $user_id]);
                // TODO: resolve a proper default role instead of hardcoding id=1
                $role = $em->getRepository('Teams\Entity\TeamRole')->findOneBy(['id' => 1]);
                $team_user = new TeamUser($team, $user, $role);
                $team_user->setCurrent(null);
                $em->persist($team_user);
            endforeach;
            $em->flush();
            return $this->redirect()->toUrl($request->getHeader('referer'));
        }
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
