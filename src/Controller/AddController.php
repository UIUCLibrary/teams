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
        $view = new ViewModel(
            [
                'form' => $form,
                'itemsetForm' => $itemsetForm,
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
            if (isset($request->getPost('itemset')['itemset']['o:itemset'])) {
                foreach ($request->getPost('itemset')['itemset']['o:itemset'] as $item_set_id):
                    if ((int)$item_set_id > 0) {
                        $item_set_id = (int)$item_set_id;

                        //add all items belonging to itemset
                        foreach ($this->api()->search('items', ['item_set_id' => $item_set_id, 'bypass_team_filter' => true])->getContent() as $item):
                            $resource_array[$item->id()] = true;

                        //add all media belonging to item
                        foreach ($this->api()->search('media', ['item_id' => $item->id(), 'bypass_team_filter' => true])->getContent() as $media):
                                $resource_array[$media->id()] = true;
                        endforeach;
                        endforeach;
                    }
                //add itemset itself
                $resource = $this->entityManager->getRepository('Omeka\Entity\Resource')
                        ->findOneBy(['id' => $item_set_id]);
                $team_resource = new TeamResource($team, $resource);

                $this->entityManager->persist($team_resource);
                endforeach;
            }
            if (isset($request->getPost('itemset')['itemset']['o:user'])) {
                foreach ($request->getPost('itemset')['itemset']['o:user'] as $user_id):
                    if ((int)$user_id > 0) {
                        $user_id = (int)$user_id;

                        //add all of that users items and their media
                        foreach ($this->api()->search('items', ['owner_id' => $user_id, 'bypass_team_filter' => true])->getContent() as $item):
                            $resource_array[$item->id()] = true;
                            foreach ($this->api()->search('media', ['item_id' => $item->id(), 'bypass_team_filter' => true])->getContent() as $media):
                                    $resource_array[$media->id()] = true;
                            endforeach;
                        endforeach;

                        //add all of that user's item sets
                        foreach ($this->api()->search('item_sets', ['owner_id' => $user_id, 'bypass_team_filter' => true])->getContent() as $itemset):
                            $resource_array[$itemset->id()] = true;
                        endforeach;

                        //add all of that user's resource templates
                        $rts = $this->entityManager->getRepository('Omeka\Entity\ResourceTemplate')->findBy(['owner' => $user_id]);
                        foreach ($rts as $rt):
                            $resource_template_array[$rt->getId()] = true;
                        endforeach;

                        //add all fo that user's assets
                        $assets = $this->entityManager->getRepository('Omeka\Entity\Asset')->findBy(['owner' => $user_id]);
                            foreach ($assets as $asset):
                                $asset_array[$asset->getId()] = true;
                            endforeach;

                    }
                endforeach;
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
