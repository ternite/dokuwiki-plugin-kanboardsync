<?php
/**
 * Model-Klasse für eine Kanboard-Aufgabe, basierend auf einer Wikiseite
 */

class KanboardTask
{
    private KanboardClient $client;
    private string $projectId;
    private string $referencePrefix;
    private string $pageId;
    private string $pageTitle;

    private ?string $quickcode = null;
    private ?string $responsibleUser = null;
    private ?Periodicity $periodicity = null;

    public function __construct(
        KanboardClient $client,
        string $projectId,
        string $referencePrefix,
        string $pageId,
        string $pageTitle
    ) {
        $this->client          = $client;
        $this->projectId       = $projectId;
        $this->referencePrefix = $referencePrefix;
        $this->pageId          = $pageId;
        $this->pageTitle       = $pageTitle;
    }

    /**
     * Initialisiert die model-relevanten Daten aus der Wiki-Seite.
     */
    public function loadFromWiki(callable $quickcodeLoader,
                                 callable $personLoader,
                                 callable $roleLoader,
                                 callable $periodicityLoader): void {

        $this->quickcode   = $quickcodeLoader($this->pageId);
        $this->responsibleUser = $personLoader($this->pageId);

        if (!$this->responsibleUser) {
            $role = $roleLoader($this->pageId);
            if ($role) {
                $this->responsibleUser = $personLoader($role);
            }
        }

        $periodicityString = $periodicityLoader($this->pageId);
        $this->periodicity = new Periodicity($periodicityString);
    }

    private function reference(): string {
        return $this->referencePrefix . $this->quickcode;
    }

    /**
     * Prüft ob noch kein offener Task existiert.
     */
    public function hasExistingTask(): bool {
        $existing = $this->client->getTaskWithUnreachedDueDateByReference(
            (int)$this->projectId,
            $this->reference()
        );
        return count($existing) > 0;
    }

    /**
     * Erstellt einen neuen Kanboard-Task.
     */
    public function create(bool $ignoreLoitering = false): ?string {

        if (!$this->periodicity->Type || !$this->periodicity->Cycle) {
            msg("Keine Periodizität für {$this->pageTitle} gefunden.", 2);
            return null;
        }

        if (!$this->responsibleUser) {
            msg("Kein Verantwortlicher für '{$this->pageTitle}' gefunden.", 2);
            return null;
        }

        $user = $this->client->getUserByName($this->responsibleUser);
        if (!$user) {
            msg("Verantwortlicher Benutzer '{$this->responsibleUser}' existiert nicht in Kanboard.", 2);
            return null;
        }

        if (!$this->periodicity->isReadyForCreation() && !$ignoreLoitering) {
            msg("Vorlaufzeit noch nicht erreicht für {$this->pageTitle}.", 2);
            return null;
        }

        $due = $this->client->dateToString($this->periodicity->getDueDate());

        return $this->client->createTask(
            $this->pageTitle,
            (int)$this->projectId,
            (int)$user->id,
            $this->reference(),
            $due
        );
    }

    /**
     * Task schließen
     */
    public function close(string $taskId): bool {
        return $this->client->closeTask($taskId);
    }
}
