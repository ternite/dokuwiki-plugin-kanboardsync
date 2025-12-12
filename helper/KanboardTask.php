<?php
declare(strict_types=1);

require_once DOKU_PLUGIN . 'mo/classes/WikiTask.php';

/**
 * Model-Klasse für eine Kanboard-Aufgabe, basierend auf einer Wikiseite
 */

class KanboardTask extends WikiTask
{
    private KanboardClient $kanboardClient;
    private mixed $moPlugin;
    private string $projectId;
    private string $referencePrefix;
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
        ResponsibilityResolver $resolver
    ) {
        parent::__construct($pageId, $resolver);

        $this->kanboardClient  = $kanboardClient;
        $this->moPlugin        = $moPlugin;
        $this->projectId       = $projectId;
        $this->referencePrefix = $referencePrefix;

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

        $this->quickcode   = $quickcodeLoader($this->getPageId());
        $this->responsibleUser = $personLoader($this->getPageId());

        $periodicityString = $periodicityLoader($this->getPageId());
        if (!is_null($periodicityString)) {
            $this->periodicity = new Periodicity($periodicityString);
        }
    }

    private function reference(): string {
        return $this->referencePrefix . $this->quickcode;
    }

    /**
     * Ermittelt den existierenden Kanboard-Task – falls vorhanden.
     */
    public function getKanboardTask(): ?stdClass {

        if ($this->kanboardTask === null) {
            // zuerst nach offenen Tasks suchen
            $result = $this->kanboardClient->getOpenTasksByReference(
                (int)$this->projectId,
                $this->reference()
            );

            if (sizeof($result) > 0) {
                $this->kanboardTask = $result[0];
                return $this->kanboardTask;
            }

            // wenn keine offenen Tasks gefunden wurden, dann nach Tasks mit unerreichtem Fälligkeitsdatum suchen
            $result = $this->kanboardClient->getTaskWithUnreachedDueDateByReference(
                (int)$this->projectId,
                $this->reference()
            );

            if (sizeof($result) > 0) {
                $this->kanboardTask = $result[0];
                return $this->kanboardTask;
            }
        }

        return $this->kanboardTask;
    }

    /**
     * Erstellt einen neuen Kanboard-Task.
     */
    public function createKanboardTask(bool $ignoreLoitering = false): ?stdClass {

        if (!$this->periodicity->Type || !$this->periodicity->Cycle) {
            $title = $this->getTitle() ?? $this->getPageId();
            msg("Keine Periodizität für {$title} gefunden.", 2);
            return null;
        }

        if (!$this->responsibleUser) {
            $title = $this->getTitle() ?? $this->getPageId();
            msg("Kein Verantwortlicher für '{$title}' gefunden.", 2);
            return null;
        }

        $user = $this->kanboardClient->getUserByName($this->responsibleUser);
        if (!$user) {
            msg("Verantwortlicher Benutzer '{$this->responsibleUser}' existiert nicht in Kanboard.", 2);
            return null;
        }

        if (!$this->periodicity->isReadyForCreation() && !$ignoreLoitering) {
            
            $wikitaskurl = DOKU_URL . 'doku.php?id=' . $this->getPageId();
            $cycle = "Zyklus";
            switch ($this->periodicity->Cycle) {
                //case "täglich":
                //    $cycle = "des Tages";
                //    break;
                case "wöchentlich":
                    $cycle = "der Woche";
                    break;
                case "monatlich":
                    $cycle = "des Monats";
                    break;
                case "vierteljährlich":
                    $cycle = "des Quartals";
                    break;
                case "halbjährlich":
                    $cycle = "des Halbjahres";
                    break;
                case "jährlich":
                    $cycle = "des Jahres";
                    break;
                case "zweijährlich":
                    $cycle = "der zwei Jahre";
                    break;
            }
            $title = $this->getTitle() ?? $this->getPageId();
            msg("Vorlaufzeit noch nicht erreicht für <a href='$wikitaskurl'>{$title}</a> (" . $this->periodicity->Cycle . " mit " . $this->periodicity->LoiteringTime . " Tagen Vorlaufzeit zum " . $this->periodicity->Offset + 1 . ". Tag $cycle).", 2);
            return null;
        }

        $due = $this->kanboardClient->dateToString($this->periodicity->getNextDueDate());

        $id = $this->kanboardClient->createTask(
            $this->getTitle() ?? $this->getPageId(),
            (int)$this->projectId,
            (int)$user->id,
            $this->reference(),
            $due
        );

        // now add external link to wiki page
        $this->kanboardClient->createExternalTaskLink(
            strval($id),
            DOKU_URL . 'doku.php?id=' . $this->getPageId(),
            'Zugehörige Aufgabenbeschreibung im Praxiswiki',
            type: "attachment",
            title: "Aufgabe: " . $this->getTitle()
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

        if (preg_match('/\{\{DOCUMENTTYPE\>AUFGABE[:|](.*)\}\}/i', $pageContent, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}