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

        if (!$tagHelper) return;

        // Hole alle Wikiseiten mit dem Tag, der in der Konfiguration via 'tasktag' definiert ist
        
        $tag = $this->getConf('tasktag');
        
        $taskPages = $tagHelper->getTopic('',999,$tag);
        
        foreach ($taskPages as $id => $page) {
            
            $this->createTask($page['id'], $page['title'], false);
        }
    }
    
    public function getTaskByQuickcode(string $quickcode) {
        $kanboard_reference = "Quickcode: $quickcode";
        return $this->kanboard->getTaskByReference($this->getConf('project_id'), $kanboard_reference);
    }

    /**
     * Ermittelt Verantwortliche Person einer Wikiseite.
     */
    public function getVerantwortlichePersonFromWikipage(string $pageid) : ?string {
        $pageContent = rawWiki($pageid);
        
        // test for syntax in Task pages
        if (preg_match('/\^ *Verantwortlich:* *\| *\[\[:*'.$this->getConf('namespace_persons').':([^|\]]+)/i', $pageContent, $matches)) {
            return $matches[1];
        } else //test for syntax in Role pages
            if (preg_match('/\^ *Inhaber der Rolle:* *\| *\[\[:*'.$this->getConf('namespace_persons').':([^|\]]+)/i', $pageContent, $matches)) {
            return $matches[1];
        } else //test for syntax in Role pages beginning with 'MFA' -> in that case set generic role MFA
            if (preg_match('/\^ *Inhaber der Rolle:* *\| *MFA *.*\|/i', $pageContent, $matches)) {
                if ($matches[0]) {
                    
                    return "mfa";
                } else {
                    return NULL;
                }
                    
        } else {
            return NULL;
        }
    }
    
    /**
     * Ermittelt Verantwortliche Rolle einer Wikiseite.
     */
    public function getVerantwortlicheRolleFromWikipage(string $pageid) : ?string {
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
    public function getRollenverantwortlicherFromWikipage(string $rolename) : ?string {
        $pageid = $this->getConf('namespace_roles').':'.$rolename;
        
        $pageContent = rawWiki($pageid);
        
        return $this->getVerantwortlichePersonFromWikipage($pageid);
    }
    
    /**
     * Ermittelt Verantwortliche Rolle einer Wikiseite.
     */
    public function getPeriodicityFromWikipage(string $pageid) : ?string {
        $pageContent = rawWiki($pageid);
        
        if (preg_match('/\{\{DOCUMENTTYPE\>AUFGABE:(.*)\}\}/i', $pageContent, $matches)) {
            return $matches[1];
        } else {
            return NULL;
        }
    }
    /**
     * Erstellt einen Kanboard Task für die gegebene Wikiseite, falls noch nicht vorhanden.
     * 
     *  * @param string $pageid Die ID der Wikiseite.
     *  * @param string $pagetitle Der Titel der Wikiseite.
     * 
     * @return string|null Die ID des erstellten Tasks oder null, wenn kein Task erstellt wurde.
     */
    public function createTask(string $pageid, string $pagetitle, ?bool $ignoreLoiteringTime = false): ?string {
        $moHelper = plugin_load('helper', 'mo');
        $quickcode = $moHelper->getQuickcode($pageid);
        $kanboard_reference = "Quickcode: $quickcode";
        
        $task_id = null;
        $task = $this->getTaskByQuickcode($quickcode);
        
        // only create a new task if there is none, yet
        if (is_null($task)) {                
        
            
            $periodicityString = $this->getPeriodicityFromWikipage($pageid);
            require_once(__DIR__ . '/helper/Periodicity.php');
            $periodicity = new Periodicity($periodicityString);
            
            // only create a new task if the task prototype has a defined periodicity!
            if ($periodicity->Type && $periodicity->Cycle) {
                
                // extract person or role responsible for that kind of task 
                $verantwortlicher = $this->getVerantwortlichePersonFromWikipage($pageid);
                
                if (!$verantwortlicher) {
                    $verantwortlicheRolle = $this->getVerantwortlicheRolleFromWikipage($pageid);
                    if ($verantwortlicheRolle) {
                        $verantwortlicher = $this->getRollenverantwortlicherFromWikipage($verantwortlicheRolle);
                    }
                }
                
                if ($verantwortlicher) {
                    $kanboard_user = $this->kanboard->getUserByName($verantwortlicher);
                    
                    if ($kanboard_user) {
                        if ($periodicity->isReadyForCreation() || $ignoreLoiteringTime) {
                        
                            $date_due = $periodicity->getDueDate();
                            
                            $task_id = $this->kanboard->createTask([
                                'title' => $pagetitle,
                                'project_id' => $this->getConf('project_id'),
                                'owner_id' => $kanboard_user->id,
                                'reference' => $kanboard_reference,
                                'date_due' => $this->kanboard->dateToString($date_due)
                            ]);
                            
                            if ($task_id) {
                                msg("Neuer Task erstellt für <b>$pageid</b> mit der Kanboard Task ID: $task_id - Verantwortlich: $kanboard_user->name ($kanboard_user->username) - Fälligkeit: ".$date_due->format('d.m.Y'),1);
                            } else {
                                msg("Erzeugung eines Tasks ist fehlgeschlagen." ,2);
                            }
                        } else {
                            msg("Keinen Task <b>".$pagetitle."</b> (Quickcode: $quickcode) angelegt, da die Vorlaufzeit ($periodicity->LoiteringTime Tage) noch nicht erreicht ist. Vorlaufdatum: ".$periodicity->getLoiteringDate()->format("d.m.Y")." Fälligkeitsdatum: ".$periodicity->getDueDate()->format("d.m.Y"));
                        return null;
                        }
                    } else {
                        msg("Der User <b>$verantwortlicher</b> konnte im Kanboard nicht gefunden werden.");
                        return null;
                    }
                } else {
                    msg("Kein Task angelegt, da kein Verantwortlicher gefunden.",2);
                    return null;
                }
            } else {
                msg("Kein Task angelegt, da keine Periodizität festgestellt werden konnte.",2);
                return null;
            }
        } else {
            msg("Task '$task->reference' existiert bereits: <b>$task->title</b> mit der Kanboard Task ID: <b>$task->id</b>.");
            return null;
        }

        return $task_id;
    }

    public function getKanboardUrlFromTask($task): ?string {
        
        if (is_null($task)) {
            return null;
        } else {
            $kanboard_url = rtrim($this->getConf('kanboard_url'), '/');
            return $kanboard_url . '/task/' . $task->id;
        }
    }
}
