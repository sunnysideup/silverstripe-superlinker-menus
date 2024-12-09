# silverstripe-superlinker-menus

Requires Silverstripe 5+

## Summary

A SilverStripe menu management built using the Superlinker foundation

## Requirements

* [silverstripe-framework](https://github.com/silverstripe/silverstripe-framework) ^5

## Installation

`composer require fromholdio/silverstripe-superlinker-menus`

## Todos

* Validation
* Finalise naming, consolidate & finalise
* Proper i18n/_t()/translations
* Documentation/readme

## How to use

Setup the structure

```yml
---
Name: fromholdio-superlinker-menus-example
---
Fromholdio\SuperLinkerMenus\Model\MenuSet:
  has_one:
    Parent: SilverStripe\SiteConfig\SiteConfig
  menus:
    footer:
      name: 'Footer Menu'
      enable_title: true
      limit: 7
      max_depth: 1

SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Fromholdio\SuperLinkerMenus\Extensions\MenuSetParentExtension

Fromholdio\SystemLinks\SystemLinks:
  links:
    login:
      url: /Security/login
      title: Login
    logout:
      url: /Security/logout
      title: Logout
    lostpassword:
      url: /Security/lostpassword
      title: Lost Password
    members:
      url: '/members'
      title: Member Section

```

add to your template

```ss
<% with $MenuSet(footer) %>
<% if $MenuItems %>
<ul>
<% loop $MenuItems %>
    <li>
        <a href="$Link">$Title</a>
        <% if $ChildMenuItems %>
        <ul>
        <% loop $ChildMenuItems %>
            <li>
                <a href="$Link">$Title</a>
            </li>
        <% end_loop %>
        </ul>
        <% end_if %>
    </li>
<% end_loop %>
</ul>
<% end_if %>
<% end_with %>
```

```

```
