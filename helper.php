<?php
/**
 * DokuWiki Plugin kanboardsync (Helper Component)
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Thomas Schäfer <thomas@hilbershome.de>
 */
if (!defined('DOKU_INC')) die();

use dokuwiki\Extension\Plugin;

class helper_plugin_kanboardsync extends Plugin {

    private $kanboard;

    public function __construct() {
        require_once(__DIR__ . '/helper/KanboardClient.php');
        $this->kanboard = new KanboardClient(
            $this->getConf('kanboard_url'),
            $this->getConf('kanboard_user'),
            $this->getConf('kanboard_token'),
            $this->getConf('ssl_verifypeer')
        );
    }

    /**
     * Synchronisiert alle mit dem definierten Tag markierten Seiten mit Kanboard
     */
    public function syncTasks() {
        global $conf;
        $tag = $this->getConf('tasktag');

        // Nutze das Tag Plugin API, um alle Seiten mit Tag "todo" zu finden
        /** @var helper_plugin_tag $tagHelper */
        $tagHelper = plugin_load('helper', 'tag');
        $moHelper = plugin_load('helper', 'mo');
        if (!$tagHelper) return;

        // Hole alle Wikiseiten mit dem Tag, der in der Konfiguration via 'tasktag' definiert ist
        $pages = $tagHelper->getTopic('',999,$tag);
        
        foreach ($pages as $id => $page) {
            //msg("<b>id</b>:<br>".$page['id']);
            
            $quickcode = $moHelper->getQuickcode($page['id']);
            $kanboard_reference = "Quickcode: $quickcode";
            
            //msg("<b>kanboard_reference</b>:<br>".$kanboard_reference);
                        
            $task = $this->kanboard->getTaskByReference($this->getConf('project_id'), $kanboard_reference);
            
            if (is_null($task)) {
                $this->kanboard->createTask([
                    'title' => $title,
                    'project_id' => $this->getConf('project_id'),
                    'kanboard_reference' => $kanboard_reference
                ]);
                msg("Neuer Task erstellt für $id", 1);
            } else {
                msg("Aufgabe '$task->reference' existiert bereits: $task->title.");
            }
        }
    }
}
