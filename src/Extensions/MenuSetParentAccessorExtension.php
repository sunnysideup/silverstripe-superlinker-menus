<?php

namespace Fromholdio\SuperLinkerMenus\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Class \Fromholdio\SuperLinkerMenus\Extensions\MenuSetParentAccessorExtension
 *
 * @property Controller|MenuSetParentAccessorExtension $owner
 */
class MenuSetParentAccessorExtension extends Extension
{
    public function getCurrentMenuSetParent(): SiteConfig
    {
        $parent = SiteConfig::current_site_config();
        $this->getOwner()->invokeWithExtensions('updateCurrentMenuSetParent', $parent);
        return $parent;
    }


}
