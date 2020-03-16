<?php
namespace Teams\Controller\SiteAdmin;

use Doctrine\ORM\EntityManager;
use Omeka\Db\Event\Subscriber\Entity;
use Omeka\Form\ConfirmForm;
use Omeka\Form\SiteForm;
use Omeka\Form\SitePageForm;
use Omeka\Form\SiteSettingsForm;
use Omeka\Mvc\Exception;
use Omeka\Site\Navigation\Link\Manager as LinkManager;
use Omeka\Site\Navigation\Translator;
use Omeka\Site\Theme\Manager as ThemeManager;
use Teams\Entity\TeamSite;
use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * @var ThemeManager
     */
    protected $themes;

    /**
     * @var LinkManager
     */
    protected $navLinks;

    /**
     * @var Translator
     */
    protected $navTranslator;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(ThemeManager $themes, LinkManager $navLinks,
        Translator $navTranslator, EntityManager $entityManager
    ) {
        $this->themes = $themes;
        $this->navLinks = $navLinks;
        $this->navTranslator = $navTranslator;
        $this->entityManager = $entityManager;
    }

    public function changeCurrentTeamAction($user_id)
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->redirect()->toRoute('admin');
        } else {
            $data = $request->getPost();
            $em = $this->entityManager;
            $team_user = $em->getRepository('Teams\Entity\TeamUser');
            $old_current = $team_user->findOneBy(['user' => $user_id, 'is_current' => true]);
            $new_current = $team_user->findOneBy(['user'=> $user_id, 'team'=>$data['team_id']]);
            if ($old_current){
                $old_current->setCurrent(null);
                //no idea why, but this function fails in this controller only unless the action is flushed first
                $em->flush();}
            $new_current->setCurrent(true);
            $em->flush();


        }
    }

    public function teamResources($resource_type, $page, $user_id, $active = true, $team_id = null)
    {
        //get the get the user's team_user with the is_current identifier
        $team_user = $this->entityManager->getRepository('Teams\Entity\TeamUser')->findOneBy(['user' => $user_id, 'is_current' => 1 ]);

        //get their current team
        $team = $team_user->getTeam();

        //get that team's sites
        $team_sites = $team->getTeamSites();

        $sites_api_obj = array();


        //TODO refactor so that this foreach starts at the appropriate index so we are only fetching what is needed
        // for the requested page
        foreach ($team_sites as $site):
            $sites_api_obj[] =  $this->api()->read($resource_type, $site->getSite()->getId())->getContent();
        endforeach;



        //this logic looks at which page they requested, how many items per page, and collects the appropriate results.
        $per_page = 10;

        //get what page of the results is requested, if null set to page 1
        if($page == null){
            $page = 1;
        }



        //starting index for that page
        $start_i = ($per_page * $page) - $per_page;

        //max index for all results sentinel
        $max_i = count($sites_api_obj);

        //get per_page number of results unless on the last page
        if ($max_i < $start_i + $per_page){
            $end_i = $max_i;
        }else{$end_i = $start_i + $per_page;}

        //an array to hold the resources for just the page
        $page_resources = array();
        for ($i = $start_i; $i < $end_i; $i++) {

            $page_resources[] = $sites_api_obj[$i];
        }


        return array('page_resources'=>$page_resources, 'team_resources'=>$sites_api_obj);



    }


    public function indexAction()
    {
        $user_id = $this->identity()->getId();
        $response = $this->teamResources('sites', $this->params()->fromQuery('page'),$user_id);


        $this->setBrowseDefaults('title', 'asc');
        $this->paginator(count($response['team_resources']), $this->params()->fromQuery('page'));

        $request = $this->getRequest();
        if ($request->isPost()){
            $this->changeCurrentTeamAction($user_id);
            return $this->redirect()->toRoute('admin/site',['controller'=>'index', 'action'=>'browse']);

        }

        $view = new ViewModel;
        $view->setVariable('sites', $response['page_resources']);
        return $view;
    }

    public function addAction()
    {
        $form = $this->getForm(SiteForm::class);
        $themes = $this->themes->getThemes();
        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $itemPool = $formData;
            unset($itemPool['csrf'], $itemPool['o:is_public'], $itemPool['o:title'], $itemPool['o:slug'],
                $itemPool['o:theme']);
            $formData['o:item_pool'] = $itemPool;
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->create('sites', $formData);
                $em  = $this->entityManager;

                $site_id = $response->getContent()->id();
                $site = $em->getRepository('Omeka\Entity\Site')->findOneBy(['id'=>$site_id]);

                foreach ($formData['team'] as $team_id):
                    $team = $em->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team_id]);
                    $trt = new TeamSite($team, $site);
                    $em->persist($trt);
                endforeach;
                $em->flush();


                if ($response) {
                    $this->messenger()->addSuccess('Site successfully created'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('themes', $themes);
        return $view;
    }

    public function editAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SiteForm::class);
        $form->setData($site->jsonSerialize());

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site successfully updated'); // @translate
                    // Explicitly re-read the site URL instead of using
                    // refresh() so we catch updates to the slug
                    return $this->redirect()->toUrl($site->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('form', $form);
        return $view;
    }

    public function settingsAction()
    {
        $site = $this->currentSite();
        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site settings'
            );
        }
        $form = $this->getForm(SiteSettingsForm::class);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $data = $form->getData();
                $fieldsets = $form->getFieldsets();
                unset($data['csrf']);
                foreach ($data as $id => $value) {
                    if (array_key_exists($id, $fieldsets) && is_array($value)) {
                        // De-nest fieldsets.
                        foreach ($value as $fieldsetId => $fieldsetValue) {
                            $this->siteSettings()->set($fieldsetId, $fieldsetValue);
                        }
                    } else {
                        $this->siteSettings()->set($id, $value);
                    }
                }
                $this->messenger()->addSuccess('Settings successfully updated'); // @translate
                return $this->redirect()->refresh();
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function addPageAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(SitePageForm::class, ['addPage' => $site->userIsAllowed('update')]);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();
                $formData['o:site']['o:id'] = $site->id();
                $response = $this->api($form)->create('site_pages', $formData);
                if ($response) {
                    $page = $response->getContent();
                    if (isset($formData['add_to_navigation']) && $formData['add_to_navigation']) {
                        // Add page to navigation.
                        $navigation = $site->navigation();
                        $navigation[] = [
                            'type' => 'page',
                            'links' => [],
                            'data' => ['id' => $page->id(), 'label' => null],
                        ];
                        $this->api()->update('sites', $site->id(), ['o:navigation' => $navigation], [], ['isPartial' => true]);
                    }
                    $this->messenger()->addSuccess('Page successfully created'); // @translate
                    return $this->redirect()->toRoute(
                        'admin/site/slug/page/default',
                        [
                            'page-slug' => $page->slug(),
                            'action' => 'edit',
                        ],
                        true
                    );
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        return $view;
    }

    public function navigationAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $jstree = json_decode($formData['jstree'], true);
            $formData['o:navigation'] = $this->navTranslator->fromJstree($jstree);
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Navigation successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('navTree', $this->navTranslator->toJstree($site));
        $view->setVariable('form', $form);
        $view->setVariable('site', $site);
        return $view;
    }

    public function resourcesAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $itemPool = $formData;
                unset($itemPool['form_csrf']);
                unset($itemPool['site_item_set']);

                $itemSets = isset($formData['o:site_item_set']) ? $formData['o:site_item_set'] : [];

                $updateData = ['o:item_pool' => $itemPool, 'o:site_item_set' => $itemSets];
                $response = $this->api($form)->update('sites', $site->id(), $updateData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site resources successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $itemCount = $this->api()
            ->search('items', ['limit' => 0, 'site_id' => $site->id()])
            ->getTotalResults();
        $itemSets = [];
        foreach ($site->siteItemSets() as $siteItemSet) {
            $itemSet = $siteItemSet->itemSet();
            $owner = $itemSet->owner();
            $itemSets[] = [
                'id' => $itemSet->id(),
                'title' => $itemSet->displayTitle(),
                'email' => $owner ? $owner->email() : null,
            ];
        }

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('itemCount', $itemCount);
        $view->setVariable('itemSets', $itemSets);
        return $view;
    }

    public function usersAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('User permissions successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $users = $this->api()->search('users', ['sort_by' => 'name']);

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('form', $form);
        $view->setVariable('users', $users->getContent());
        return $view;
    }

    public function themeAction()
    {
        $site = $this->currentSite();
        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site theme settings'
            );
        }
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-theme-form');
        if ($this->getRequest()->isPost()) {
            $formData = $this->params()->fromPost();
            $form->setData($formData);
            if ($form->isValid()) {
                $response = $this->api($form)->update('sites', $site->id(), $formData, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Site theme successfully updated'); // @translate
                    return $this->redirect()->refresh();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('site', $site);
        $view->setVariable('themes', $this->themes->getThemes());
        $view->setVariable('currentTheme', $this->themes->getCurrentTheme());
        return $view;
    }

    public function themeSettingsAction()
    {
        $site = $this->currentSite();

        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site theme settings'
            );
        }

        $theme = $this->themes->getTheme($site->theme());
        $config = $theme->getConfigSpec();

        $view = new ViewModel;
        if (!($config && $config['elements'])) {
            return $view;
        }

        /** @var Form $form */
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        foreach ($config['elements'] as $elementSpec) {
            $form->add($elementSpec);
        }

        // Fix to manage empty values for selects and multicheckboxes.
        $inputFilter = $form->getInputFilter();
        foreach ($form->getElements() as $element) {
            if ($element instanceof \Zend\Form\Element\MultiCheckbox
                || ($element instanceof \Zend\Form\Element\Select
                    && $element->getOption('empty_option') !== null)
            ) {
                $inputFilter->add([
                    'name' => $element->getName(),
                    'allow_empty' => true,
                ]);
            }
        }

        $oldSettings = $this->siteSettings()->get($theme->getSettingsKey());
        if ($oldSettings) {
            $form->setData($oldSettings);
        }

        $view->setVariable('form', $form);
        $view->setVariable('theme', $theme);
        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();
        $form->setData($postData);
        if ($form->isValid()) {
            $data = $form->getData();
            unset($data['form_csrf']);
            $this->siteSettings()->set($theme->getSettingsKey(), $data);
            $this->messenger()->addSuccess('Theme settings successfully updated'); // @translate
            return $this->redirect()->refresh();
        }

        $this->messenger()->addFormErrors($form);

        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('sites', ['slug' => $this->params('site-slug')]);
                if ($response) {
                    $this->messenger()->addSuccess('Site successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/site');
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $view = new ViewModel;
        $view->setVariable('site', $site);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $site = $this->currentSite();
        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resourceLabel', 'site'); // @translate
        $view->setVariable('partialPath', 'omeka/site-admin/index/show-details');
        $view->setVariable('resource', $site);
        return $view;
    }

    public function navigationLinkFormAction()
    {
        $site = $this->currentSite();
        $link = $this->navLinks->get($this->params()->fromPost('type'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate($link->getFormTemplate());
        $view->setVariable('data', $this->params()->fromPost('data'));
        $view->setVariable('site', $site);
        $view->setVariable('link', $link);
        return $view;
    }

    public function sidebarItemSelectAction()
    {
        $this->setBrowseDefaults('created');
        $site = $this->currentSite();

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        $response = $this->api()->search('items', $query);
        $items = $response->getContent();
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('omeka/admin/item/sidebar-select');
        $view->setVariable('search', $this->params()->fromQuery('search'));
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('items', $items);
        $view->setVariable('showDetails', false);
        return $view;
    }
}
