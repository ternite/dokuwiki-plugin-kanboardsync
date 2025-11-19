<?php
/**
 * Model-Klasse für eine Kanboard-Aufgabe, basierend auf einer Wikiseite
 */

class KanboardTask
{
    private KanboardClient $kanboardClient;
    private mixed $moPlugin;
    private string $projectId;
    private string $referencePrefix;
    private string $pageId;
    private string $pageTitle;
    private mixed $kanboardTask = null;

    private ?string $quickcode = null;
    private ?string $responsibleUser = null;
    private ?Periodicity $periodicity = null;

    public function __construct(
        KanboardClient $kanboardClient,
        mixed $moPlugin,
        string $projectId,
        string $referencePrefix,
        string $pageId,
        string $pageTitle
    ) {
        $this->kanboardClient  = $kanboardClient;
        $this->moPlugin        = $moPlugin;
        $this->projectId       = $projectId;
        $this->referencePrefix = $referencePrefix;
        $this->pageId          = $pageId;
        $this->pageTitle       = $pageTitle;

        // -- Initialisiere Model aus der Wiki-Seite
        $this->loadFromWiki(
            fn($id) => $this->moPlugin->getQuickcode($id),
            fn($id) => $this->moPlugin->getVerantwortlichePersonFromWikipage($id),
            fn($id) => $this->getPeriodicityFromWikipage($id)
        );

        $this->kanboardTask = $this->getKanboardTask();
    }

    /**
     * Initialisiert die model-relevanten Daten aus der Wiki-Seite.
     * @param callable $quickcodeLoader Funktion zum Laden des Quickcodes
     * @param callable $personLoader Funktion zum Laden der verantwortlichen Person
     * @param callable $periodicityLoader Funktion zum Laden der Periodizität
     */
    public function loadFromWiki(callable $quickcodeLoader,
                                 callable $personLoader,
                                 callable $periodicityLoader): void {

        $this->quickcode   = $quickcodeLoader($this->pageId);
        $this->responsibleUser = $personLoader($this->pageId);

        $periodicityString = $periodicityLoader($this->pageId);
        $this->periodicity = new Periodicity($periodicityString);
    }

    private function reference(): string {
        return $this->referencePrefix . $this->quickcode;
    }

    /**
     * Ermittelt den existierenden Kanboard-Task – falls vorhanden.
     */
    public function getKanboardTask(): ?stdClass {

        if ($this->kanboardTask === null) {
            $result = $this->kanboardClient->getTaskWithUnreachedDueDateByReference(
                (int)$this->projectId,
                $this->reference()
            );

            if (sizeof($result) > 0) {
                $this->kanboardTask = $result[0];
            }
        }

        return $this->kanboardTask;
    }

    /**
     * Erstellt einen neuen Kanboard-Task.
     */
    public function createKanboardTask(bool $ignoreLoitering = false): ?stdClass {

        if (!$this->periodicity->Type || !$this->periodicity->Cycle) {
            msg("Keine Periodizität für {$this->pageTitle} gefunden.", 2);
            return null;
        }

        if (!$this->responsibleUser) {
            msg("Kein Verantwortlicher für '{$this->pageTitle}' gefunden.", 2);
            return null;
        }

        $user = $this->kanboardClient->getUserByName($this->responsibleUser);
        if (!$user) {
            msg("Verantwortlicher Benutzer '{$this->responsibleUser}' existiert nicht in Kanboard.", 2);
            return null;
        }

        if (!$this->periodicity->isReadyForCreation() && !$ignoreLoitering) {
            
            $wikitaskurl = DOKU_URL . 'doku.php?id=' . $this->pageId;
            msg("Vorlaufzeit noch nicht erreicht für <a href='$wikitaskurl'>$this->pageTitle</a> (" . $this->periodicity->Cycle . " mit " . $this->periodicity->LoiteringTime . " Tagen Vorlaufzeit).", 2);
            return null;
        }

        $due = $this->kanboardClient->dateToString($this->periodicity->getDueDate());

        $id = $this->kanboardClient->createTask(
            $this->pageTitle,
            (int)$this->projectId,
            (int)$user->id,
            $this->reference(),
            $due
        );

        return $this->getKanboardTask();
    }

    /**
     * Task schließen
     */
    public function close(string $taskId): bool {
        return $this->kanboardClient->closeTask($taskId);
    }

    /**
     * Extrahiere Periodizität aus Dokument-Type Syntax.
     */
    public function getPeriodicityFromWikipage(string $pageid): ?string {
        $pageContent = rawWiki($pageid);

        if (preg_match('/\{\{DOCUMENTTYPE\>AUFGABE:(.*)\}\}/i', $pageContent, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
