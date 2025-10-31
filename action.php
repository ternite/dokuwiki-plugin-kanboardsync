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
     * Ensure the action is recognized to avoid the message "Failed to handle action: sync_kanboard"
     * see implementation hints from: https://www.dokuwiki.org/devel:event:tpl_act_unknown
     */
    public function allowMyAction(Doku_Event $event, $param) {
        if($event->data != 'sync_kanboard') return; 
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

        // Prüfe, ob die Action die unsere ist
        if ($INPUT->str('do') !== 'sync_kanboard') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();
        
        
        //$event->data = 'show';

        // Hilfsplugin laden und ausführen
        $helper = plugin_load('helper', 'kanboardsync');
        if ($helper) {
            $helper->syncTasks();
            msg('Kanboard Sync erfolgreich ausgeführt', 1);
        } else {
            msg('KanboardSync Helper konnte nicht geladen werden', -1);
        }

        // Optionale Ausgabe
        //echo '<div class="dokuwiki">Kanboard-Synchronisierung abgeschlossen.</div>';
    }
}
