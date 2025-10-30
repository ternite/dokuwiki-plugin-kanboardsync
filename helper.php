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
            $this->getConf('kanboard_token')
        );
    }

    /**
     * Synchronisiert alle mit dem definierten Tag markierten Seiten mit Kanboard
     */
    public function syncTasks() {
        global $conf;
        $tag = $this->getConf('tag');

        // Nutze das Tag Plugin API, um alle Seiten mit Tag "todo" zu finden
        /** @var helper_plugin_tag $tagHelper */
        /*$tagHelper = plugin_load('helper', 'tag');
        if (!$tagHelper) return;

        $pages = $tagHelper->getTopicList($tag);
        foreach ($pages as $id => $title) {
            $pageContent = rawWiki($id);

            // einfache Titel/Referenzbildung
            $reference = "wiki:$id";

            $existing = $this->kanboard->getTaskByReference($this->getConf('project_id'), $reference);

            if (is_null($existing)) {
                $this->kanboard->createTask([
                    'title' => $title,
                    'project_id' => $this->getConf('project_id'),
                    'reference' => $reference
                ]);
                msg("Neuer Task erstellt für $id", 1);
            }
        }*/
    }
}
