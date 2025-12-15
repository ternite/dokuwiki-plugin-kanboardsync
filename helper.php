<?php
/**
 * DokuWiki Plugin kanboardsync (Helper Component)
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  
 */
if (!defined('DOKU_INC')) die();

use dokuwiki\Extension\Plugin;

class helper_plugin_kanboardsync extends Plugin {

    private KanboardClient $kanboard;
    private string $reference_prefix = "Quickcode-";

    public function __construct() {
        require_once(__DIR__ . '/helper/KanboardClient.php');
        require_once(__DIR__ . '/helper/KanboardTask.php');
        require_once(__DIR__ . '/helper/Periodicity.php');

        $this->kanboard = new KanboardClient(
            $this->getConf('kanboard_url'),
            $this->getConf('kanboard_user'),
            $this->getConf('kanboard_token'),
            $this->getConf('ssl_verifypeer')
        );
    }

    /**
     * Führt die Synchronisation aller Wikiseiten mit dem konfigurierten Task-Tag durch.
     */
    public function syncTasks() {
        /** @var helper_plugin_tag $tagHelper */
        $tagHelper = plugin_load('helper', 'tag');
        if (!$tagHelper) return;

        $tag = $this->getConf('tasktag');
        $taskPages = $tagHelper->getTopic('', 999, $tag);

        foreach ($taskPages as $entry) {
            $this->createTaskIfNecessary($entry['id'], $entry['title'], false);
        }
    }

    /**
     * Erstellt einen KanboardTask für die gegebenen Seite – falls nötig.
     * @param string $pageid Die Seiten-ID der Wikiseite
     * @param string $pagetitle Der Titel der Wikiseite
     * @param bool $ignoreLoitering Ob die Vorlaufzeit ignoriert werden soll
     * @return stdClass|null Das Task-Objekt des erstellten Tasks oder null, falls kein Task erstellt wurde
     */
    public function createTaskIfNecessary(string $pageid, string $pagetitle, bool $ignoreLoitering = false): ?stdClass {

        $moHelper = plugin_load('helper','mo');

        $task = new KanboardTask(
            $this->kanboard,
            plugin_load('helper', 'mo'),
            $this->getConf('project_id'),
            $this->reference_prefix,
            $pageid,
            $moHelper->getResponsbilityResolver()
        );

        // -- Prüfe, ob es bereits einen Task gibt
        $kanboardTask = $task->getKanboardTask();
        if ($kanboardTask) {
            $kanboardtaskurl = $this->getKanboardUrlFromTaskID($kanboardTask->id);
            $wikitaskurl = DOKU_URL . 'doku.php?id=' . $pageid;
            $statusText = ($kanboardTask->is_active) ? "offen" : "erledigt";
            msg("Ein  Task für <a href='$wikitaskurl'>$pagetitle</a> (" . $statusText . ") existiert bereits (Task-ID $kanboardTask->id). <a href='$kanboardtaskurl'>Task im Kanboard öffnen</a>", 0);
            return null;
        }

        // -- Erzeuge neuen Task
        $kanboardTask = $task->createKanboardTask($ignoreLoitering);

        if ($kanboardTask) {
            $kanboardtaskurl = $this->getKanboardUrlFromTaskID($kanboardTask->id);
            $wikitaskurl = DOKU_URL . 'doku.php?id=' . $pageid;
            msg("Neuer Task für <a href='$wikitaskurl'>$pagetitle</a> erzeugt (Task-ID $kanboardTask->id). <a href='$kanboardtaskurl'>Task im Kanboard öffnen</a>", 1);
            //expire the cache of the page
            p_set_metadata($pageid, array('cache'=>'expire'),false,false);
        }

        return $kanboardTask;
    }
    
    public function getKanboardTask(string $pageid): WikiTask {
        
        require_once DOKU_PLUGIN . 'mo/classes/ResponsibilityResolver.php';
        require_once(__DIR__ . '/KanboardTask.php');

        $mo_helper = $this->loadHelper('mo');

        return new KanboardTask($pageid, $mo_helper->getResponsbilityResolver);
    }


    /**
     * Schließt einen Task.
     */
    public function closeTask(string $taskid): bool {
        return $this->kanboard->closeTask($taskid);
    }

    /**
     * Kanboard-URL für ein Task-Objekt.
     */
    public function getKanboardUrlFromTask(stdClass $task): ?string {
        return $task ? $this->getKanboardUrlFromTaskID($task->id) : null;
    }

    /**
     * Kanboard-URL für Task-ID.
     */
    public function getKanboardUrlFromTaskID($taskid): ?string {
        if ($taskid > 0) {
            $base = rtrim($this->getConf('kanboard_url'), '/');
            return "$base/task/$taskid";
        }
        return null;
    }

    public function getKanboardUrlForUser($userid): ?string {
        if ($taskid > 0) {
            $base = rtrim($this->getConf('kanboard_url'), '/');
            return "$base/?controller=TaskListController&action=show&plugin=&project_id=1&search=status%3Aopen+assignee%3A" . urlencode("\"$userid\"");
        }
        return null;
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
     * Holt alle offenen Tasks anhand des Quickcodes
     * 
     *  @param string $quickcode Der Quickcode der Wikiseite
     *  @return array Ein Array von Task-Objekten - gibt es keine, dann ein leeres Array zurück
     */
    public function getOpenTasksByQuickcode(string $quickcode) {
        $kanboard_reference = $this->reference_prefix.$quickcode;
        return $this->kanboard->getOpenTasksByReference($this->getConf('project_id'), $kanboard_reference);
    }

    /**
     * Holt von Kanboard alle offenen Tasks für den übergebenen Bearbeiter.
     * 
     * @param string $assignee_userid Benutzer-ID des Bearbeiters (sollte in Dokuwiki und Kanboard übereinstimmen)
     */
    public function getOpenTasksByAssignee(string $assignee_userid) {
        return $this->kanboard->getOpenTasksByAssignee($this->getConf('project_id'), $assignee_userid);
    }
}
