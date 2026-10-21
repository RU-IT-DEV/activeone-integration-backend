<?php
// Supported actions are defined per registered route. Never trust arbitrary action names from clients.
return [
 '/activeone/dashboard' => ['can_view'],
 '/activeone/orders' => ['can_view','can_view_history','can_rerun_ic_transaction','can_validate'],
 '/activeone/products' => ['can_view','can_create','can_update','can_delete'],
 '/activeone/navigations' => ['can_view','can_create','can_update','can_delete'],
 '/activeone/roles' => ['can_view','can_create','can_update','can_delete'],
];