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
use Laminas\Filter\Boolean;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Teams\Service;

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

    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('items', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

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
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('items', $this->params('id'));

        $view = new ViewModel;
        $item = $response->getContent();
        $view->setVariable('item', $item);
        $view->setVariable('resource', $item);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool)$this->params()->fromQuery('link-title', true);
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
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('items', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

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
        $linkTitle = (bool)$this->params()->fromQuery('link-title', true);
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
            $data = $this->mergeValuesJson($data);
            $form->setData($data);
            if ($form->isValid()) {
                $fileData = $this->getRequest()->getFiles()->toArray();
                $response = $this->api($form)->create('items', $data, $fileData);
                $em = $this->entityManager;
                $resource = $em->getRepository('Omeka\Entity\Item')->findOneBy(['id' => $response->getContent()->id()]);
                $media = $resource->getMedia();


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
            $data = $this->mergeValuesJson($data);

            $form->setData($data);
            if ($form->isValid()) {
                $fileData = $this->getRequest()->getFiles()->toArray();
                $response = $this->api($form)->update('items', $this->params('id'), $data, $fileData);


                $em = $this->entityManager;
                $entity = $this->entityManager->getRepository('Teams\Entity\TeamResource')
                    ->findBy(['resource' => $response->getContent()->id()]);
                $media_ids = [];

                //if user added media, the form will include the ingester [0]['o:ingester']
                if (array_key_exists('o:media', $data)) {
                    if ($data['o:media']) {

                        foreach ($response->getContent()->media() as $media):
                            $media_ids[] = $media->id();
                        endforeach;
                    }
                }
                foreach ($entity as $e):
                    $this->entityManager->remove($e);
                endforeach;
                $em->flush();

                $resource = $em->getRepository('Omeka\Entity\Resource')
                    ->findOneBy(['id' => $response->getContent()->id()]);

                foreach ($data['team'] as $team_id):
                    $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                    $team_res = new TeamResource($team, $resource);
                    $em->persist($team_res);
                    foreach ($media_ids as $media_id):
                        $media_res = $em->getRepository('Omeka\Entity\Resource')
                            ->findOneBy(['id' => $media_id]);
                        //don't add the media to a team where it already exists
                        if (!$em->getRepository('Teams\Entity\TeamResource')
                            ->findOneBy(['resource' => $media_id, 'team'=> $team_id])){
                            $team_res = new TeamResource($team, $media_res);
                            $em->persist($team_res);
                        }
                    endforeach;
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

        $resources = [];
        foreach ($resourceIds as $resourceId) {
            $resources[] = $this->api()->read('items', $resourceId)->getContent();
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
