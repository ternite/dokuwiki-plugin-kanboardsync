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
    private string $reference_prefix = "Quickcode-";

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

        // Nutze das Tag Plugin API, um alle Seiten mit Tag "todo" zu finden
        /** @var helper_plugin_tag $tagHelper */
        $tagHelper = plugin_load('helper', 'tag');

        if (!$tagHelper) return;

        // Hole alle Wikiseiten mit dem Tag, der in der Konfiguration via 'tasktag' definiert ist
        
        $tag = $this->getConf('tasktag');
        
        $taskPages = $tagHelper->getTopic('',999,$tag);
        
        foreach ($taskPages as $id => $page) {
            
            $this->createTaskIfNecessary($page['id'], $page['title'], false);
        }
    }
    
    public function getActiveTaskByQuickcode(string $quickcode) {
        $kanboard_reference = $this->reference_prefix.$quickcode;
        return $this->kanboard->getActiveTaskByReference($this->getConf('project_id'), $kanboard_reference);
    }

    /**
     * Holt alle Tasks mit unerreichtem Fälligkeitsdatum anhand des Quickcodes
     *  @param string $quickcode Der Quickcode der Wikiseite
     *  @return array Ein Array von Task-Objekten - gibt es keine, dann ein leeres Array zurück
     */
    public function getTasksWithUnreachedDueDateByQuickcode(string $quickcode) {
        $kanboard_reference = $this->reference_prefix.$quickcode;
        return $this->kanboard->getTaskWithUnreachedDueDateByReference($this->getConf('project_id'), $kanboard_reference);
    }

    /**
     * Ermittelt Verantwortliche Person einer Wikiseite.
     * @param string $pageid Die ID der Wikiseite
     * @return string|null Der Benutzername der verantwortlichen Person oder null, wenn keine gefunden wurde
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
     * @param string $pageid Die ID der Wikiseite
     * @return string|null Der Name der verantwortlichen Rolle oder null, wenn keine gefunden wurde
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
     * @param string $rolename Der Name der Rolle
     * @return string|null Der Benutzername der verantwortlichen Person oder null, wenn keine gefunden wurde
     */
    public function getRollenverantwortlicherFromWikipage(string $rolename) : ?string {
        $pageid = $this->getConf('namespace_roles').':'.$rolename;
        
        $pageContent = rawWiki($pageid);
        
        return $this->getVerantwortlichePersonFromWikipage($pageid);
    }
    
    /**
     * Ermittelt Verantwortliche Rolle einer Wikiseite.
     * @param string $pageid Die ID der Wikiseite
     * @return string|null Die Periodizität als String oder null, wenn keine gefunden wurde
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
    public function createTaskIfNecessary(string $pageid, string $pagetitle, bool $ignoreLoitering = false): ?string {
    
    require_once(__DIR__ . '/helper/KanboardTask.php');

    $task = new KanboardTask(
        $this->kanboard,
        $this->getConf('project_id'),
        $this->reference_prefix,
        $pageid,
        $pagetitle
    );

    // Model mit Loader-Closures initialisieren
    $task->loadFromWiki(
        fn($id) => plugin_load('helper', 'mo')->getQuickcode($id),
        fn($id) => $this->getVerantwortlichePersonFromWikipage($id),
        fn($id) => $this->getVerantwortlicheRolleFromWikipage($id),
        fn($id) => $this->getPeriodicityFromWikipage($id)
    );

    if ($task->hasExistingTask()) {
        msg("Task existiert bereits – kein neuer Task erzeugt.");
        return null;
    }

    $taskId = $task->create($ignoreLoitering);

    if ($taskId) {
        msg("Neuer Task $taskId für '$pagetitle' erzeugt.", 1);
    }

    return $taskId;
}

    public function closeTask(string $taskid): bool {
        $success = $this->kanboard->closeTask($taskid);

        return $success;
    }


    public function getKanboardUrlFromTask($task): ?string {
        
        if (is_null($task)) {
            return null;
        } else {
            return $this->getKanboardUrlFromTaskID($task->id);
        }
    }

    public function getKanboardUrlFromTaskID($taskid): ?string {
        
        if ($taskid > 0) {
            $kanboard_url = rtrim($this->getConf('kanboard_url'), '/');
            return $kanboard_url . '/task/' . $taskid;
        } else {
            return null;
        }
    }
}
