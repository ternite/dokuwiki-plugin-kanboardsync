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
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'allowMyAction');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'handle_sync');
    }
    
    /**
     * Ensure the action is recognized to avoid the message "Failed to handle action: kanboard_sync"
     * see implementation hints from: https://www.dokuwiki.org/devel:event:tpl_act_unknown
     */
    public function allowMyAction(Doku_Event $event, $param) {
        if($event->data != 'kanboard_sync' && $event->data != 'kanboard_create_task') return; 
        $event->preventDefault();
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
            case 'kanboard_create_task':
                $event->preventDefault();
                $event->stopPropagation();
                
                $pageid = $INPUT->str('pageid');

                if (strlen($pageid) > 0) {
                    $pageTitle = p_get_metadata($pageid)['title'];
                    $taskid = $kanboard_helper->createTask($pageid, $pageTitle, true);
                    
                    if (is_null($taskid)) {
                        msg('Kanboard Task "'.$pageTitle.'" konnte nicht angelegt werden.');
                    } else {
                        msg("Kanboard Task mit ID $taskid angelegt.", 1);
                    }
                }

            default:
                return;
        }
        
    }
}
