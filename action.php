<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 * DokuWiki Plugin kanboardsync (Action Component)
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Thomas Schäfer <thomas@hilbershome.de>
 */
class action_plugin_kanboardsync extends ActionPlugin {
    /** @inheritDoc */
    public function register(EventHandler $controller) {
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'handle_sync'); // register our actions
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'allowMyAction'); // ensure action kanboard_sync is recognized
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'handle_create'); // register action kanboard_create_task -> use TPL_ACT_RENDER to have the current page output, as well as the create message 
    }
    
    /**
     * Ensure the action is recognized to avoid the message "Failed to handle action: kanboard_sync"
     * see implementation hints from: https://www.dokuwiki.org/devel:event:tpl_act_unknown
     */
    public function allowMyAction(Doku_Event $event, $param) {
        if ($event->data == 'kanboard_sync') {
            $event->preventDefault(); // don't show the usual page output
        } else if ($event->data == 'kanboard_create_task' || $event->data == 'kanboard_close_task') {
            $event->data = 'show'; // perform action AND show page output
        }
    }
  
    /**
     * Event handler for ACTION_ACT_PREPROCESS
     *
     * @see https://www.dokuwiki.org/devel:event:ACTION_ACT_PREPROCESS
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handle_sync(Event $event, $param) {
        global $INPUT;

        // Hilfsplugin laden und ausführen
        $kanboard_helper = plugin_load('helper', 'kanboardsync');
        
        if (is_null($kanboard_helper)) {
            msg('KanboardSync Helper konnte nicht geladen werden', -1);
            return;
        }

        // Prüfe, ob die Action die unsere ist
        switch ($INPUT->str('do')) {
            case 'kanboard_sync':
                $event->preventDefault();
                $event->stopPropagation();
                
                $kanboard_helper->syncTasks();
                break;
            default:
                return;
        }
    }

    /**
     * Event handler for TPL_ACT_RENDER
     *
     * @see https://www.dokuwiki.org/devel:event:TPL_ACT_RENDER
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when event was registered
     * @return void
     */
    public function handle_create(Event $event, $param) {
        global $INPUT;

        // Hilfsplugin laden und ausführen
        $kanboard_helper = plugin_load('helper', 'kanboardsync');
        
        if (is_null($kanboard_helper)) {
            msg('KanboardSync Helper konnte nicht geladen werden', -1);
            return;
        }

        // Prüfe, ob die Action die unsere ist
        switch ($INPUT->str('do')) {
            case 'kanboard_create_task':
                //$event->preventDefault();
                
                $pageid = $INPUT->str('pageid');

                if (strlen($pageid) > 0) {
                    $pageTitle = p_get_metadata($pageid)['title'];
                    $taskid = $kanboard_helper->createTaskIfNecessary($pageid, $pageTitle, true);
                    
                    if (is_null($taskid)) {
                        //msg('Kanboard Task "'.$pageTitle.'" konnte nicht angelegt werden.');
                    } else {
                        //msg("Kanboard Task mit ID $taskid angelegt.", 1);
                    }
                }
            case 'kanboard_close_task':
                //$event->preventDefault();
                
                $taskid = $INPUT->str('taskid');

                if (!is_null($taskid) && $taskid != 0)  {
                    $success = $kanboard_helper->closeTask($taskid, true);
                    
                    if ($success) {
                        msg('Die Aufgabe wurde auf erledigt gesetzt.');
                    } else {
                        msg('Kanboard Task mit ID "'.$taskid.'" konnte nicht auf erledigt gesetzt werden.');
                    }
                }

            default:
                return;
        }
    }
}
