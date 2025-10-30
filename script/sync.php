#!/usr/bin/env php
<?php
function get_absolute_path($path) {
   $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
   $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
   $absolutes = array();
   foreach ($parts as $part) {
       if ('.' == $part) continue;
       if ('..' == $part) {
           array_pop($absolutes);
       } else {
           $absolutes[] = $part;
       }
   }
   return implode(DIRECTORY_SEPARATOR, $absolutes);
}

define('DOKU_INC', get_absolute_path($_SERVER["SCRIPT_FILENAME"] . '/../../../../../') . '/');
define('NOSESSION', true);
require_once(DOKU_INC . 'inc/init.php');

$plugin = plugin_load('helper', 'kanboardsync');
if ($plugin) {
    $plugin->syncTasks();
    echo "Kanboard Sync erfolgreich ausgeführt\n";
} else {
    echo "KanboardSync Plugin konnte nicht geladen werden\n";
}
