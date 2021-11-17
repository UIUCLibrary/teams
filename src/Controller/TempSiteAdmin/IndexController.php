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
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

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

    public function indexAction()
    {
        $this->setBrowseDefaults('title', 'asc');
        $response = $this->api()->search('sites', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        $view = new ViewModel;
        $view->setVariable('sites', $response->getContent());
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

        $user_id = $this->identity()->getId();
        $em = $this->entityManager;

        $teams = $em->getRepository('Teams\Entity\TeamUser');
        if ($teams->findOneBy(['user'=>$user_id, 'is_current'=>1])){
            $default_team = $teams->findOneBy(['user'=>$user_id, 'is_current'=>1]);
        } elseif ($teams->findBy(['user' => $user_id])){
            $default_team = $teams->findOneBy(['user' => $user_id], ['name']);
        } else {
            $default_team = null;
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('themes', $themes);
        $view->setVariable('default_team', $default_team);
        if ($this->request->isPost()){
            $view->setVariable('item_pool',$itemPool['item_set_id']);
        }
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
                //db not updating with just the form. Handeling this error after long absence from covid, but thought that
                //this was being taken care of through the api so I wouldn't have to do it in each controller
                //on update, remove all teams associated with the site
                $team_sites = $this->entityManager->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site->id()]);
                foreach ($team_sites as $team_site):
                    $this->entityManager->remove($team_site);
                endforeach;
                $this->entityManager->flush();

                //add teams to the site for each team listed in the form
                foreach ($formData['team'] as $team):
                    $team_site = new TeamSite($this->entityManager->getRepository('Teams\Entity\Team')->findOneBy(['id' => $team]),
                    $this->entityManager->getRepository('Omeka\Entity\Site')->findOneBy(['id' => $site->id()]));
                    $this->entityManager->persist($team_site);
                endforeach;
                $this->entityManager->flush();

                //TODO errors from the teams being updated will not show up here
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
        $em = $this->entityManager;
        $site_id = $this->currentSite()->id();
        $team_sites = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $current_teams = array();
        foreach ($team_sites as $team_site):
            $current_teams[] = $team_site->getTeam()->getId();
        endforeach;

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('form', $form);
        $view->setVariable('current_teams' , $current_teams);
        return $view;
    }

    public function settingsAction()
    {
        $site = $this->currentSite();
        if (!$site->userIsAllowed('update')) {
            throw new Exception\PermissionDeniedException(
                'User does not have permission to edit site settings' // @translate
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
        $form->add([
            'name' => 'o:homepage[o:id]',
            'type' => 'Omeka\Form\Element\SitePageSelect',
            'options' => [
                'label' => 'Homepage', // @translate
                'empty_option' => '',
            ],
            'attributes' => [
                'value' => $site->homepage() ? $site->homepage()->id() : null,
                'class' => 'chosen-select',
                'data-placeholder' => 'First page in navigation', // @translate
            ],
        ]);
        $form->getInputFilter()->add([
            'name' => 'o:homepage[o:id]',
            'allow_empty' => true,
        ]);

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
        $site_id = $site->id();
        $em = $this->entityManager;
        $site_teams = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $site_resources = array();
        $site_resource_templates = array();
        $get_item_sets = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\ItemSet");
        $get_items = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Item");
        $get_media = $this->entityManager->createQuery("SELECT resource.id FROM Omeka\Entity\Resource resource WHERE resource INSTANCE OF Omeka\Entity\Media");

        //thought that a scalar would be easier but from what I can tell in this case getScalarResult===getArrayResult===getResult
        //leaving it this way because it sounds closest to the kind of data I actually want to get out
        $all_item_sets = $get_item_sets->getScalarResult();
        $all_items = $get_items->getScalarResult();
        $all_media = $get_media->getScalarResult();

        //combine all of the resources from all of the teams the site is associated with
        //maintaining the distinction between resources and resource templates from core omeka
        foreach ($site_teams as $site_team):
            $team_resources = $site_team->getTeam()->getTeamResources()->toArray();
            $team_resource_templates = $site_team->getTeam()->getTeamResourceTemplates()->toArray();
            $site_resources = array_merge($site_resources, $team_resources);
            $site_resource_templates = array_merge($site_resource_templates, $team_resource_templates);

        endforeach;

        //returns the id when either a TeamResource object or row from a doctrine result set is provided
        $getIds = function ($resource){
            if (is_object($resource)){
                return $resource->getResource()->getId();
            }elseif(is_array($resource)){
                return $resource['id'];
            }else{return null;}
        };

        //if there is a way to do this by testing TeamResources with INSTANCE OF it would be simpler, but I was not
        //able to get that to work. So using an array of Omeka itemsets as discriminator

        //get just the resource ids from TeamResources associated with the site
        $team_res_ids = array_map($getIds, $site_resources);

        //get the ids from all omeka item sets
        $all_item_set_ids = array_map($getIds, $all_item_sets);

        //get the ids from all omeka items
        $all_item_ids = array_map($getIds, $all_items);

        //get the ids from all omeka media
        $all_media_ids = array_map($getIds, $all_media);

        //filter out just the site's itemsets
        $site_item_sets = array_intersect($all_item_set_ids, $team_res_ids);

        //filterout just the site's items
        $site_items = array_intersect($all_item_ids, $team_res_ids);

        //filter out just the site's media
        $site_media = array_intersect($all_media_ids, $team_res_ids);




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
        $view->setVariable('site_resources', $site_resources);
        $view->setVariable('site_media', $site_media);
        $view->setVariable('site_items', $site_items);
        $view->setVariable('site_item_sets', $site_item_sets);
        $view->setVariable('site_teams', $site_teams);
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
        if (!$theme->isConfigurable()) {
            throw new Exception\RuntimeException(
                'The current theme is not configurable.'
            );
        }

        $config = $theme->getConfigSpec();
        $view = new ViewModel;

        /** @var Form $form */
        $form = $this->getForm(Form::class)->setAttribute('id', 'site-form');

        foreach ($config['elements'] as $elementSpec) {
            $form->add($elementSpec);
        }

        // Fix to manage empty values for selects and multicheckboxes.
        $inputFilter = $form->getInputFilter();
        foreach ($form->getElements() as $element) {
            if ($element instanceof \Laminas\Form\Element\MultiCheckbox
                || ($element instanceof \Laminas\Form\Element\Select
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
        $site_id = $site->id();
        $em = $this->entityManager;
        $site_teams = $em->getRepository('Teams\Entity\TeamSite')->findBy(['site'=>$site_id]);
        $view = new ViewModel;

        $view->setVariable('site', $site);
        $view->setVariable('site_teams', $site_teams);
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
        $this->paginator($response->getTotalResults());

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('omeka/admin/item/sidebar-select');
        $view->setVariable('search', $this->params()->fromQuery('search'));
        $view->setVariable('resourceClassId', $this->params()->fromQuery('resource_class_id'));
        $view->setVariable('itemSetId', $this->params()->fromQuery('item_set_id'));
        $view->setVariable('id', $this->params()->fromQuery('id'));
        $view->setVariable('items', $items);
        $view->setVariable('showDetails', false);
        return $view;
    }
}
