<?php

namespace Fromholdio\SuperLinkerMenus\Model;

use Fromholdio\GridFieldLimiter\Forms\GridFieldLimiter;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\SiteConfig\SiteConfigLeftAndMain;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class \Fromholdio\SuperLinkerMenus\Model\MenuSet
 *
 * @property string $Key
 * @property string $Name
 * @property string $CustomTitle
 * @property bool $IsTitleEnabled
 * @property bool $IsHighlightEnabled
 * @property int $ItemsLimit
 * @property int $MaxDepth
 * @property int $Sort
 * @property int $ParentID
 * @method SiteConfig Parent()
 * @method DataList|MenuItem[] Items()
 */
class MenuSet extends DataObject implements PermissionProvider
{
    private static $table_name = 'MenuSet';
    private static $singular_name = 'Menu';
    private static $plural_name = 'Menus';

    private static $db = [
        'Key' => 'Varchar(50)',
        'Name' => 'Varchar',
        'CustomTitle' => 'Varchar',
        'IsTitleEnabled' => 'Boolean',
        'IsHighlightEnabled' => 'Boolean',
        'ItemsLimit' => 'Int',
        'MaxDepth' => 'Int',
        'Sort' => 'Int'
    ];

    private static $has_many = [
        'Items' => MenuItem::class
    ];

    private static $owns = [
        'Items'
    ];

    private static $cascade_deletes = [
        'Items'
    ];

    private static $cascade_duplicates = [
        'Items'
    ];

    private static $defaults = [
        'IsTitleEnabled' => false,
        'IsHighlightEnabled' => false,
        'ItemsLimit' => 0,
        'MaxDepth' => 0,
        'Sort' => 1
    ];

    private static $summary_fields = [
        'CMSTitle' => 'Title',
        'Items.Count' => 'Items'
    ];

    private static $default_sort = 'Sort';

    private static $menu_item_class = MenuItem::class;

    public function MenuItems()
    {
        if (($items = $this->Items()) && $items->exists()) {
            $output = ArrayList::create();
            foreach ($items as $item) {
                if ($item->isLinkValid()) {
                    $output->push($item);
                }
            }
            return $output;
        }
        return null;
    }

    public static function get_parent_class()
    {
        $class = static::singleton()->getRelationClass('Parent');
        if (!$class) {
            throw new \Exception(
                'You must define a has_one relation named "Parent" on your MenuSet class.'
            );
        }
        return $class;
    }

    public static function get_by_key($key, $parentID)
    {
        $parentClass = self::get_parent_class();
        $parent = $parentClass::get()->byID($parentID);
        if ($parent && $parent->exists()) {
            return $parent->MenuSets()->find('Key', $key);
        }
        return null;
    }

    public static function update_menusets($parent)
    {
        $menuSets = $parent->MenuSets();
        if ($parent->hasMethod('getMenuSetsThemes')) {
            $themes = $parent->getMenuSetsThemes();
        } else {
            $themes = SSViewer::get_themes();
        }

        $updatedMenuSetIDs = [];

        $configMenuSets = self::get_config_menusets($themes);
        if ($configMenuSets) {
            foreach ($menuSets as $menuSet) {
                if (isset($configMenuSets[$menuSet->Key])) {
                    $data = $configMenuSets[$menuSet->Key];
                    $menuSet = $menuSet->updateFromConfig($data);
                    $updatedMenuSetIDs[] = $menuSet->ID;
                    unset($configMenuSets[$menuSet->Key]);
                } else {
                    if ($menuSet->isVersioned()) {
                        $menuSet->doArchive();
                    } else {
                        $menuSet->delete();
                    }
                }
            }
            foreach ($configMenuSets as $key => $data) {
                $menuSet = self::create();
                $menuSet->Key = $key;
                $menuSet->ParentID = $parent->ID;
                $menuSet = $menuSet->updateFromConfig($data);
                $updatedMenuSetIDs[] = $menuSet->ID;
            }
        } else {
            if ($menuSets->count() > 0) {
                foreach ($menuSets as $menuSet) {
                    if ($menuSet->isVersioned()) {
                        $menuSet->doArchive();
                    } else {
                        $menuSet->delete();
                    }
                }
            }
        }
    }

    public static function get_config_menusets($themes = null)
    {
        $menuSets = [];
        $config = Config::inst()->get(self::class, 'theme_menus');
        if (is_array($config)) {
            if ($themes) {
                if (!is_array($themes)) {
                    $themes = [$themes];
                }
                foreach ($themes as $theme) {
                    if (isset($config[$theme])) {
                        foreach ($config[$theme] as $key => $data) {
                            $menuSets[$key] = $data;
                        }
                    }
                }
            }
        } else {
            $config = Config::inst()->get(self::class, 'menus');
            if (is_array($config)) {
                foreach ($config as $key => $data) {
                    $menuSets[$key] = $data;
                }
            }
        }

        if (count($menuSets) < 1) {
            return null;
        }
        return $menuSets;
    }

    public function requireDefaultRecords()
    {
        $parentClass = self::get_parent_class();
        $parents = $parentClass::get();
        $parentIDs = $parents->columnUnique('ID');
        if (count($parentIDs)) {
            $menuSets = static::get()->exclude([
                'ParentID' => $parentIDs
            ]);
            foreach ($menuSets as $menuSet) {
                if ($menuSet->isVersioned()) {
                    $menuSet->doArchive();
                } else {
                    $menuSet->delete();
                }
            }
        }
        foreach ($parents as $parent) {
            self::update_menusets($parent);
        }
        $this->extend('requireDefaultRecords', $dummy);
    }

    public function getCMSTitle()
    {
        return $this->getTitle();
    }

    public function getTitle()
    {
        $title = null;
        if ($this->IsTitleEnabled && $this->CustomTitle) {
            $title = $this->CustomTitle;
        }
        $curr = Controller::curr();
        if (is_a($curr, LeftAndMain::class)) {
            if ($title) {
                $title = $this->Name . ' - ' . $title;
            } else {
                $title = $this->Name;
            }
        }
        $this->extend('updateTitle', $title);
        return $title;
    }

    public function updateFromConfig($data)
    {
        if (is_string($data)) {
            if ($data !== $this->Name) {
                $this->Name = $data;
                $this->write();
                if ($this->isVersioned()) {
                    $this->publishSingle();
                }
            }
            return $this;
        }

        $doWrite = false;

        if (isset($data['class'])) {
            $class = $data['class'];
            if (!is_a($class, MenuSet::class, true)) {
                $class = MenuSet::class;
            }
        } else {
            $class = MenuSet::class;
        }

        if (get_class($this) !== $class) {
            $this->ClassName = $class;
            $doWrite = true;
        }

        if ($data['name'] !== $this->Name) {
            $this->Name = $data['name'];
            $doWrite = true;
        }

        if (isset($data['enable_title'])) {
            $isTitleEnabled = (bool) $data['enable_title'];
            if ($isTitleEnabled && !$this->IsTitleEnabled) {
                $this->IsTitleEnabled = true;
                $doWrite = true;
            } elseif (!$isTitleEnabled && $this->IsTitleEnabled) {
                $this->IsTitleEnabled = false;
                $doWrite = true;
            }
        } elseif ($this->IsTitleEnabled) {
            $this->IsTitleEnabled = false;
            $doWrite = true;
        }

        if (isset($data['enable_highlight'])) {
            $isHighlightEnabled = (bool) $data['enable_highlight'];
            if ($isHighlightEnabled && !$this->IsHighlightEnabled) {
                $this->IsHighlightEnabled = true;
                $doWrite = true;
            } elseif (!$isHighlightEnabled && $this->IsHighlightEnabled) {
                $this->IsHighlightEnabled = false;
                $doWrite = true;
            }
        } elseif ($this->IsHighlightEnabled) {
            $this->IsHighlightEnabled = false;
            $doWrite = true;
        }

        if (isset($data['limit'])) {
            $limit = (int) $data['limit'];
            if ($limit !== (int) $this->ItemsLimit) {
                $this->ItemsLimit = $limit;
                $doWrite = true;
            }
        }

        if (isset($data['max_depth'])) {
            $maxDepth = (int) $data['max_depth'];
            if ($maxDepth !== (int) $this->MaxDepth) {
                $this->MaxDepth = $maxDepth;
                $doWrite = true;
            }
        }

        if (isset($data['sort'])) {
            $sort = (int) $data['sort'];
            if ($sort !== (int) $this->Sort) {
                $this->Sort = $sort;
                $doWrite = true;
            }
        }

        if ($doWrite) {
            $this->write();
            if ($this->isVersioned()) {
                $this->publishSingle();
            }
        }

        return $this;
    }

    public function getMenuItemClass()
    {
        $class = $this->config()->get('menu_item_class');
        $this->extend('updateMenuItemClass', $class);
        return $class;
    }

    public function isVersioned()
    {
        return $this->hasExtension(Versioned::class);
    }

    public function validate()
    {
        $result = parent::validate();

        if ($this->Key) {
            $sameKeyMenuSets = self::get()
                ->filter([
                    'Key' => $this->Key,
                    'ParentID' => $this->ParentID
                ])
                ->exclude('ID', $this->ID);
            if ($sameKeyMenuSets->count() > 0) {
                $result->addFieldError('Key', 'You must use a unique key');
            }
        } else {
            $result->addFieldError('Key', 'You must provide a key');
        }

        if (!$this->Name) {
            $result->addFieldError('Name', 'You must provide a name');
        }

        return $result;
    }

    public function getCMSFields()
    {
        $fields = FieldList::create(
            TabSet::create(
                'Root',
                $mainTabSet = TabSet::create(
                    'MainTabSet',
                    'Main',
                    $menuItemsTab = Tab::create(
                        'MenuItemsTab',
                        'Menu Items',
                        HeaderField::create('NameHeader', $this->Name, 2)
                    )
                )
            )
        );

        if ($this->IsTitleEnabled) {
            $menuItemsTab->push(
                TextField::create('CustomTitle', $this->fieldLabel('Title'))
            );
        }

        $itemsField = GridField::create(
            'Items',
            $this->fieldLabel('Items'),
            $this->Items(),
            $itemsConfig = GridFieldConfig_RecordEditor::create(null, true, false)
        );

        $itemsConfig
            ->removeComponentsByType([
                GridFieldAddExistingAutocompleter::class,
                GridFieldPageCount::class,
                GridFieldPaginator::class,
                GridFieldToolbarHeader::class
            ])
            ->addComponent(new GridFieldOrderableRows());

        if ($this->ItemsLimit > 0) {
            $itemsLimiter = new GridFieldLimiter($this->ItemsLimit, 'before', true);
            $itemsConfig->addComponent($itemsLimiter);
        }

        $menuItemsTab->push($itemsField);
        $menuItemsTab->push(HiddenField::create('ID', false));

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function CMSEditLink()
    {
        $link = null;
        if ($this->ParentID) {
            if (is_a($this->Parent(), SiteTree::class)) {
                $link = Controller::join_links(
                    singleton(CMSPageEditController::class)->Link('EditForm'),
                    $this->ParentID,
                    'field/MenuSets/item',
                    $this->ID,
                    'edit'
                );
            } elseif (is_a($this->Parent(), SiteConfig::class)) {
                $link = Controller::join_links(
                    singleton(SiteConfigLeftAndMain::class)->Link('EditForm'),
                    'field/MenuSets/item',
                    $this->ID,
                    'edit'
                );
            }
        }
        $this->extend('updateCMSEditLink', $link);
        return $link;
    }

    public function canCreate($member = null, $context = null)
    {
        return Permission::checkMember($member, 'CREATE_MENUSETS');
    }

    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'DELETE_MENUSETS');
    }

    public function canEdit($member = null)
    {
        $can = Permission::checkMember($member, 'MANAGE_MENUSETS');
        if ($can) {
            $can = $this->Parent()->canEdit($member);
        }
        return $can;
    }

    public function canView($member = null)
    {
        $can = Permission::checkMember($member, 'MANAGE_MENUSETS');
        if ($can) {
            $can = $this->Parent()->canView($member);
        }
        return $can;
    }

    public function providePermissions()
    {
        return [
            'MANAGE_MENUSETS' => array(
                'name' => 'Manage menu sets',
                'category' => 'Menus',
            ),
            'CREATE_MENUSETS' => array(
                'name' => 'Create menu sets',
                'category' => 'Menus',
            ),
            'DELETE_MENUSETS' => array(
                'name' => 'Delete menu sets',
                'category' => 'Menus',
            )
        ];
    }
}
