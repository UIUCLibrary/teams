<?php
namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr;
use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use Teams\Entity\TeamResource;
use Teams\Module;
use Zend\Filter\Boolean;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Teams\Model;

class ItemController extends AbstractActionController
{
    //begin edits: adding in the entity manager
    /**
     * @var entityManager
     */
    protected $entityManager;


    /**
     * @var Manager
     */
    protected $mediaIngesters;

    /**
     * @param Manager $mediaIngesters
     * @param EntityManager $entityManager
     */
    public function __construct(Manager $mediaIngesters, EntityManager $entityManager)
    {
        $this->mediaIngesters = $mediaIngesters;
        $this->entityManager = $entityManager;
    }
    //end edits
    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function teamResources($resource_type, $query, $user_id, $active = true, $team_id = null)
    {
        if ($active){
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

        } else{
            $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'team' => $team_id ]);
        }

        $resources = array();
        if ($team_user) {

            $active_team_id = $team_user->getTeam()->getId();

            $team_entity = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $active_team_id]);




            $q = $this->entityManager->createQuery("SELECT resource FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Item");
            $item_sets = $q->getArrayResult();
            $team_resources = array();
            foreach ($team_entity->getTeamResources() as $team_resource):
                //obv here would be a place where you could just use the discriminator to see if it is an item
                if (array_search($team_resource->getResource()->getId(), array_column($item_sets, 'id')) ){
                    $team_resources[] = $team_resource;
                }
            endforeach;
            $per_page = 10;
            $page = $query['page'];
            $start_i = ($per_page * $page) - $per_page;
//            $tr = $team_entity->getTeamResources();
            $max_i = count($team_resources);
            if ($max_i < $start_i + $per_page){
                $end_i = $max_i;
            }else{$end_i = $start_i + $per_page;}
//            $tr = $team_entity->getTeamResources();
            for ($i = $start_i; $i < $end_i; $i++) {

                $resources[] = $this->api()->read($resource_type, $team_resources[$i]->getResource()->getId())->getContent();}

        }else{$team_resources=null;}

        return array('page_resources'=>$resources, 'team_resources'=>$team_resources, 'team_entity' => $team_entity);



    }











    public function browseAction()
    {
        $this->setBrowseDefaults('created');

        //get the user's id
        $user_id = $this->identity()->getId();
        $current_team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user'=>$user_id, 'is_current'=>1]);
        $team_id = $current_team_user->getTeam()->getId();
        $params = $this->params()->fromQuery();
        $params['team_id'] = $team_id;
        $response = $this->api()->search('items', $params);


//        //TODO: these needs to be moved to the advanced search panel I think
//        if (count($this->params()->fromQuery('team_id'))>0){
//            $this->changeCurrentTeamAction($user_id, $this->params()->fromQuery());
//        }


//        $team_items = $this->teamResources('items', $this->params()->fromQuery(), $user_id);
//        $items = $team_items['page_resources'];
//        $total_team_resources = $team_items['team_resources'];
        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id, $request->getPost());
            return $this->redirect()->toRoute('admin/default',['controller'=>'item', 'action'=>'browse']);


        }
        ///////stopped here, in the middle of trying to apply some of the advanced search filtering options. Will be way
        /// easier to just do this all in the api, but want to work out some of the kinks here first
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));
        $this->setBrowseDefaults('created');
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);


        $view = new ViewModel;
        $items = $response->getContent();
        $view->setVariable('items', $items);
        $view->setVariable('resources', $items);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        $view->setVariable('params', $this->params()->fromQuery());
        return $view;





        return $view;
        //end edits
    }

    public function showAction()
    {
        $response = $this->api()->read('items', $this->params('id'));
        $current_user = $this->identity()->getId();

        $team_resource = $this->entityManager->getRepository('Teams\Entity\TeamResource')->findBy(['resource'=> $this->params('id')]);
        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findBy(['user'=>$current_user]);

        $resource_in_teams = array();
        $user_in_teams = array();
        foreach ($team_resource as $r):
            $resource_in_teams[] = $r->getTeam()->getId();
        endforeach;

        foreach ($team_user as $u):
            $user_in_teams[] = $u->getTeam()->getId();
        endforeach;

        if (array_intersect($user_in_teams, $resource_in_teams)) {

            $denied = false;
            $view = new ViewModel;
            $item = $response->getContent();
            $view->setVariable('item', $item);
            $view->setVariable('resource', $item);
            $view->setVariable('denied', $denied);

            return $view;
        }else{
            $denied = true;
            return $this->redirect()->toRoute('admin/default', ['controller'=>'item']);
        }

    }

    public function changeCurrentTeamAction($user_id, $data)
    {
//        $request = $this->getRequest();
//        if (!$request->isPost()) {
//            return $this->redirect()->toRoute('admin');
//        } else {
//            $data = $request->getPost();
            $em = $this->entityManager;
            $team_user = $em->getRepository('Teams\Entity\TeamUser');
            $old_current = $team_user->findOneBy(['user' => $user_id, 'is_current' => true]);
            $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);
            $old_current->setCurrent(null);
            $new_current->setCurrent(true);
            $em->flush();


//        }
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('items', $this->params('id'));
        $item = $response->getContent();
        $values = $item->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $item);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function sidebarSelectAction()
    {


        $response = $this->api()->search('items', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('items', $response->getContent());
        $view->setVariable('search', $this->params()->fromQuery('search'));
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('id', $this->params()->fromQuery('id'));
        $view->setVariable('showDetails', true);
        $view->setTerminal(true);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('items', $this->params('id'));
        $item = $response->getContent();
        $values = $item->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $item);
        $view->setVariable('resourceLabel', 'item'); // @translate
        $view->setVariable('partialPath', 'omeka/admin/item/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('item', $item);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('items', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Item successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/default',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one item to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('items', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Items successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $job = $this->jobDispatcher()->dispatch('Omeka\Job\BatchDelete', [
                'resource' => 'items',
                'query' => $query,
            ]);
            $this->messenger()->addSuccess('Deleting items. This may take a while.'); // @translate
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function addAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('action', $this->url()->fromRoute(null, [], true));
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('id', 'add-item');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $fileData = $this->getRequest()->getFiles()->toArray();
                $response = $this->api($form)->create('items', $data, $fileData);
                $user_id = $this->identity()->getId();

//                $media = $this->api()->read('items', $this->params('id'))->media();
                $em = $this->entityManager;
                $resource = $em->getRepository('Omeka\Entity\Item')->findOneBy(['id' => $response->getContent()->id()]);
                $media = $resource->getMedia();

                if (array_key_exists('team', $data)){
                foreach ($data['team'] as $team_id):
                    $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
                    $team_resource = new TeamResource($team, $resource);
                    $em->persist($team_resource);
                    if (count($media)>0) {
                        foreach ($media as $m):
                            $tr = new TeamResource($team, $m);
                            $em->persist($tr);
                        endforeach;
                    }
                    endforeach;
                $em->flush();}


                //right now this doesn't care about the input it just add it to the users current team
                //get the get the user's team_user with the is_current identifier
//                $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

                //get their current team
//                $team = $team_user->getTeam();
//                $team = $this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$data['team']]);
//                $resource = $this->entityManager->getRepository('Omeka\Entity\Resource')->findOneBy(['id' => $response->getContent()->id()]);
//                $team_res = new TeamResource($team, $resource);
//                $em = $this->entityManager;
//                $em->persist($team_res);
//                $em->flush();
                if ($response) {
                    $message = new Message(
                        'Item successfully created. %s', // @translate
                        sprintf(
                            '<a href="%s">%s</a>',
                            htmlspecialchars($this->url()->fromRoute(null, [], true)),
                            $this->translate('Add another item?')
                        ));
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('mediaForms', $this->getMediaForms());
        return $view;
    }

    public function editAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('action', $this->url()->fromRoute(null, [], true));
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('id', 'edit-item');
        $item = $this->api()->read('items', $this->params('id'))->getContent();

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $fileData = $this->getRequest()->getFiles()->toArray();
                $response = $this->api($form)->update('items', $this->params('id'), $data, $fileData);


                    $em = $this->entityManager;
                    $entity = $this->entityManager->getRepository('Teams\Entity\TeamResource')->findBy(['resource' => $response->getContent()->id()]);
                    foreach ($entity as $e):

                        $this->entityManager->remove($e);
                    endforeach;
                    $em->flush();
                    $resource = $em->getRepository('Omeka\Entity\Resource')->findOneBy(['id' => $response->getContent()->id()]);

                    foreach ($data['team'] as $team_id):
                        $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id'=>$team_id]);
                        $team_res = new TeamResource($team, $resource);
                        $em = $this->entityManager;
                        $em->persist($team_res);
                        endforeach;
                    $em->flush();
                if ($response) {
                    $this->messenger()->addSuccess('Item successfully updated'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('item', $item);
        $view->setVariable('resource', $item);
        $view->setVariable('mediaForms', $this->getMediaForms());
        return $view;
    }

    /**
     * Batch update selected items.
     */
    public function batchEditAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one item to batch edit.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resources = [];
        foreach ($resourceIds as $resourceId) {
            $resources[] = $this->api()->read('items', $resourceId)->getContent();
        }

        $form = $this->getForm(ResourceBatchUpdateForm::class, ['resource_type' => 'item']);
        $form->setAttribute('id', 'batch-edit-item');
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->preprocessData();

                foreach ($data as $collectionAction => $properties) {
                    $this->api($form)->batchUpdate('items', $resourceIds, $properties, [
                        'continueOnError' => true,
                        'collectionAction' => $collectionAction,
                    ]);
                }

                $this->messenger()->addSuccess('Items successfully edited'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('resources', $resources);
        $view->setVariable('query', []);
        $view->setVariable('count', null);
        return $view;
    }

    /**
     * Batch update all items returned from a query.
     */
    public function batchEditAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);
        $count = $this->api()->search('items', ['limit' => 0] + $query)->getTotalResults();

        $form = $this->getForm(ResourceBatchUpdateForm::class, ['resource_type' => 'item']);
        $form->setAttribute('id', 'batch-edit-item');
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->preprocessData();

                $job = $this->jobDispatcher()->dispatch('Omeka\Job\BatchUpdate', [
                    'resource' => 'items',
                    'query' => $query,
                    'data' => isset($data['replace']) ? $data['replace'] : [],
                    'data_remove' => isset($data['remove']) ? $data['remove'] : [],
                    'data_append' => isset($data['append']) ? $data['append'] : [],
                ]);

                $this->messenger()->addSuccess('Editing items. This may take a while.'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setTemplate('omeka/admin/item/batch-edit.phtml');
        $view->setVariable('form', $form);
        $view->setVariable('resources', []);
        $view->setVariable('query', $query);
        $view->setVariable('count', $count);
        return $view;
    }

    protected function getMediaForms()
    {
        $mediaHelper = $this->viewHelpers()->get('media');
        $forms = [];
        foreach ($this->mediaIngesters->getRegisteredNames() as $ingester) {
            $forms[$ingester] = [
                'label' => $this->mediaIngesters->get($ingester)->getLabel(),
                'form' => $mediaHelper->form($ingester),
            ];
        }
        return $forms;
    }
}
