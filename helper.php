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

        // Nutze das Tag Plugin API, um alle Seiten mit Tag "todo" zu finden
        /** @var helper_plugin_tag $tagHelper */
        $tagHelper = plugin_load('helper', 'tag');
        $moHelper = plugin_load('helper', 'mo');
        if (!$tagHelper) return;

        // Hole alle Wikiseiten mit dem Tag, der in der Konfiguration via 'tasktag' definiert ist
        
        $tag = $this->getConf('tasktag');
        $cycle = "";
        $date_due_if_new = new DateTime();
        $date_due_if_new->modify('+90 days');
        $date_due_if_new = $date_due_if_new->format('Y-m-d H:i');
        
        $pages_cyclic_quarterly = $tagHelper->getTopic('',999,"$tag +Zyklus_vierteljährlich");
        
        foreach ($pages_cyclic_quarterly as $id => $page) {
            //msg("<b>id</b>:<br>".$page['id']);
            
            $quickcode = $moHelper->getQuickcode($page['id']);
            $kanboard_reference = "Quickcode: $quickcode";
            
            //msg("<b>kanboard_reference</b>:<br>".$kanboard_reference);
                        
            $task = $this->kanboard->getTaskByReference($this->getConf('project_id'), $kanboard_reference);
            
            if (is_null($task)) {                
                // extract person or role responsible for that kind of task 
                $verantwortlicher = $this->getVerantwortlichePerson($page['id']);
                
                if (!$verantwortlicher) {
                    $verantwortlicheRolle = $this->getVerantwortlicheRolle($page['id']);
                    if ($verantwortlicheRolle) {
                        $verantwortlicher = $this->getRollenverantwortlicher($verantwortlicheRolle);
                    } else {
                        msg("Kein Verantwortlicher gefunden.",2);
                    }
                }
                
                if ($verantwortlicher) {
                    $kanboard_user = $this->kanboard->getUserByName($verantwortlicher);
                                        
                    $task_id = $this->kanboard->createTask([
                        'title' => $page['title'],
                        'project_id' => $this->getConf('project_id'),
                        'owner_id' => $kanboard_user->id,
                        'reference' => $kanboard_reference,
                        'date_due' => $date_due_if_new
                    ]);
                    if ($task_id) {
                        msg("Neuer Task erstellt für <b>$id</b> mit der Kanboard Task ID: $task_id - Verantwortlich: $kanboard_user->name ($kanboard_user->username)", 0);
                    } else {
                        msg("Erzeugung eines Tasks ist fehlgeschlagen.".date('YYYY-MM-DD HH:MM',$date_due_if_new) ,2);
                    }
                }
            } else {
                msg("Aufgabe '$task->reference' existiert bereits: <b>$task->title</b> mit der Kanboard Task ID: <b>$task->id</b>.");
            }
        }
    }
    
    /**
     * Ermittelt Verantwortliche Person einer Wikiseite.
     */
    public function getVerantwortlichePerson(string $pageid) {
        $pageContent = rawWiki($pageid);
        
        // test for syntax in Task pages
        if (preg_match('/\^ *Verantwortlich:* *\| *\[\[:*'.$this->getConf('namespace_persons').':([^|\]]+)/i', $pageContent, $matches)) {
            return $matches[1];
        } else //test for syntax in Role pages
            if (preg_match('/\^ *Inhaber der Rolle:* *\| *\[\[:*'.$this->getConf('namespace_persons').':([^|\]]+)/i', $pageContent, $matches)) {
            return $matches[1];
        } else {
            return NULL;
        }
    }
    
    /**
     * Ermittelt Verantwortliche Rolle einer Wikiseite.
     */
    public function getVerantwortlicheRolle(string $pageid) {
        $pageContent = rawWiki($pageid);
        
        if (preg_match('/\^ *Verantwortlich: *\| *\[\[:*'.$this->getConf('namespace_roles').':([^|\]]+)/i', $pageContent, $matches)) {
            return $matches[1];
        } else {
            return NULL;
        }
    }
    
    /**
     * Ermittelt Verantwortliche Person einer Rolle.
     */
    public function getRollenverantwortlicher(string $rolename) {
        $pageid = $this->getConf('namespace_roles').':'.$rolename;
        
        $pageContent = rawWiki($pageid);
        
        return $this->getVerantwortlichePerson($pageid);
    }
}
