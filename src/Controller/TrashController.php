<?php


namespace Teams\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Omeka\Form\ConfirmForm;
use Teams\Form\TrashForms\DeleteAllForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class TrashController extends AbstractActionController
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

    public function createNamedParameter(
        QueryBuilder $qb,
        $value,
        $prefix = 'omeka_'
    ) {
        $index = 0;
        $placeholder = $prefix . $index;
        $index++;
        $qb->setParameter($placeholder, $value);
        return ":$placeholder";
    }

    public function indexAction()
    {

        if ($this->request->isPost()) {
            if ($this->identity()->getRole() == 'global_admin') {
                if ($this->getRequest()->getPost('submit') == 'Delete Selected') {
                    foreach ($this->getRequest()->getPost('resource_ids') as $id):
                        $id = (int) $id;
                    $this->api()->delete('items', ['id' =>$id]);
                    endforeach;
                } elseif ($this->getRequest()->getPost('submit') == 'Delete All') {
                    $qb1 = $this->entityManager->createQueryBuilder();
                    $qb1->select('r')
                        ->from('Omeka\Entity\Item ', 'r')
                        ->leftJoin(
                            'Teams\Entity\TeamResource',
                            'tr',
                            \Doctrine\ORM\Query\Expr\Join::WITH,
                            'r.id = tr.resource'
                        )
                        ->where('tr.team is NULL');

                    $orphans = $qb1->getQuery()->getResult();

                    foreach ($orphans as $resource):
                        $this->api()->delete('items', $resource->getId());
                    endforeach;
                }
            }
        }
        $this->browse()->setDefaults('items');
        $params = $this->params()->fromQuery();
        $params['orphans'] = true;
        $params['bypass_team_filter'] = true;
        $response = $this->api()->search('items', $params);
        $this->paginator($response->getTotalResults());

        $orphans =  $response->getContent();
        $returnQuery = $this->params()->fromQuery();
        unset($returnQuery['page']);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/trash', ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Delete Selected'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(DeleteAllForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Delete All'); 
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $view->setVariable('items', $orphans);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        $view->setVariable('resources', $orphans);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        $view->setVariable('returnQuery', $returnQuery);

        return $view;
    }
}
