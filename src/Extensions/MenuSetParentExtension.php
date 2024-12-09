<?php

namespace Fromholdio\SuperLinkerMenus\Extensions;

use Fromholdio\SuperLinkerMenus\Model\MenuSet;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Core\Extension;

/**
 * Class \Fromholdio\SuperLinkerMenus\Extensions\MenuSetParentExtension
 *
 * @property SiteConfig|MenuSetParentExtension $owner
 * @method DataList|MenuSet[] MenuSets()
 */
class MenuSetParentExtension extends Extension
{
    private static $menusets_tab_path = 'Root.Menus';

    private static $has_many = [
        'MenuSets' => MenuSet::class
    ];

    private static $cascade_deletes = [
        'MenuSets'
    ];

    private static $cascade_duplicates = [
        'MenuSets'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('MenuSets');

        $tabPath = $this->getOwner()->getMenuSetsTabPath();
        if (!$tabPath) {
            return;
        }

        $fields->addFieldsToTab(
            $tabPath,
            [
                GridField::create(
                    'MenuSets',
                    'Menus',
                    $this->getOwner()->MenuSets(),
                    $config = GridFieldConfig_RecordEditor::create(null, true, false)
                )
            ]
        );

        $config->removeComponentsByType([
            GridFieldAddNewButton::class,
            GridFieldAddExistingAutocompleter::class,
            GridFieldPageCount::class,
            GridFieldPaginator::class,
            GridFieldToolbarHeader::class
        ]);
    }


    public function getMenuSetsTabPath()
    {
        $path = $this->getOwner()->config()->get('menusets_tab_path');
        $this->getOwner()->invokeWithExtensions('updateMenuSetsTabPath');
        return $path;
    }
}
