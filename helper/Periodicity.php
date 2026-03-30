<?php

class Periodicity
{
    public ?DateTime $referenceDate; // Referenzdatum, von dem aus die Fälligkeit ermittelt wird. Standardmäßig das aktuelle Datum, kann aber auch explizit gesetzt werden, z.B. auf das Erstellungsdatum eines Tasks.
    public ?string $Type;           // 'wiederkehrend', 'kontinuierlich'
    public ?string $Cycle;          // 'täglich', 'wöchentlich', ...
                                    // -> Wird zur Ermittlung des nächsten Fälligkeitsdatums eingesetzt, ausgehend vom Referenzdatum, bspw.:
                                    //    vierteljährlich und Referenzdatum=15.03.2025 -> 1.4. (Beginn des nächsten Quartals) + $Offset
    public ?string $Offset;         // Anzahl Tage ab Cycle, zu dem das DueDate gilt
    public ?string $LoiteringTime;  // Anzahl Tage vor dem DueDate, ab wann der Task angelegt werden soll

    private ?bool $currentTaskAlreadyCompleted; //gibt an, ob der aktuell bestehende Task bereits erledigt ist. Relevant für die Berechnung des nächsten DueDate bei kontinuierlichen Aufgaben, damit nicht einfach das nächste DueDate basierend auf dem Referenzdatum berechnet wird, sondern geprüft wird, ob das Referenzdatum bereits in der Vergangenheit liegt und der Task noch nicht erledigt ist. In diesem Fall würde das nächste DueDate in der Vergangenheit liegen, obwohl eigentlich ein neuer Task angelegt werden müsste. In diesem Fall würde dann als Referenzdatum für die Berechnung des nächsten DueDate das aktuelle Datum genommen.

    /**
     * Konstruktor 1: Nimmt ein Array oder einen String
     * @param array|string $input Array oder String mit Periodizitätsangaben
     * @param DateTime|null $referenceDate Optionales Referenzdatum für die Berechnung der Fälligkeit. Standardmäßig das aktuelle Datum.
     * @param bool|null $currentTaskAlreadyCompleted Optionaler Parameter, der angibt, ob der aktuell bestehende Task bereits erledigt ist. Relevant für die Berechnung des nächsten DueDate bei kontinuierlichen Aufgaben.
     * Beispiel 1: new Periodicity(['wiederkehrend', 'vierteljährlich', '85', '7'])
     * Beispiel 2: new Periodicity('wiederkehrend,vierteljährlich,85,7')
     * 
     */
    public function __construct($input, ?DateTime $referenceDate = null, ?bool $currentTaskAlreadyCompleted = null) {
        $this->referenceDate = $referenceDate;
        $this->currentTaskAlreadyCompleted = $currentTaskAlreadyCompleted;

        if (is_null($referenceDate)) {
            $this->referenceDate = new DateTime();
        }
        
        if (is_array($input)) {
            $this->setMembersFromArray($input);
        } elseif (is_string($input)) {
            $parts = preg_split('/(,|;)/', $input);
            $this->setMembersFromArray($parts);
        } else {
            throw new InvalidArgumentException('Periodicity expects array or string.');
        }
    }

    /**
     * Setzt die Member-Variablen aus einem Array.
     * @param array $input
     */
    private function setMembersFromArray(array $input) {
        if (is_array($input)) {
            $this->Type          = $input[0] ?? null;
            $this->Cycle         = $input[1] ?? null;
            $this->Offset        = $input[2] ?? null;
            $this->LoiteringTime = $input[3] ?? null;
        }
    }
    
    /**
     * Ermittelt ein Fälligkeitsdatum basierend auf der Periodizität. Das beim Anlegen des Periodicity-Objekts angegebene Referenzdatum wird für die Fälligkeit als Ausgangspunkt verwendet.
     */
    public function getNextDueDate(): ?DateTime {
        $dueDate = $this->referenceDate;
        
        switch ($this->Cycle) {
            case 'täglich':
                $dueDate = $this->referenceDate;
                break;

            case 'wöchentlich':
                // Code für wöchentliche Wiederholung
                $dueDate = $this->getWochenbeginn();
                 // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this week plus offset, move to next week
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+1 week");
                }

                // if the task has already been completed in the current cycle, we want to calculate the due date for the next cycle, not the current one, even if the reference date is still before the due date of the current cycle. Otherwise, if the reference date is before the due date of the current cycle, but the task has already been completed in this cycle, we would end up with a due date in the past and no new task would be created, although actually a new task should be created because the current one is already completed.
                if ($this->currentTaskAlreadyCompleted == true) {
                    $dueDate->modify("+1 week");                    
                    break;
                }

                break;

            case 'monatlich':
                $dueDate = $this->getMonatsbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this month plus offset, move to next month
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+1 month");
                }

                // if the task has already been completed in the current cycle, we want to calculate the due date for the next cycle, not the current one, even if the reference date is still before the due date of the current cycle. Otherwise, if the reference date is before the due date of the current cycle, but the task has already been completed in this cycle, we would end up with a due date in the past and no new task would be created, although actually a new task should be created because the current one is already completed.
                if ($this->currentTaskAlreadyCompleted == true) {
                    $dueDate->modify("month");                    
                    break;
                }

                break;

            case 'vierteljährlich':
                        
                $dueDate = $this->getQuartalsbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                // if the task has already been completed in the current cycle, we want to calculate the due date for the next cycle, not the current one, even if the reference date is still before the due date of the current cycle. Otherwise, if the reference date is before the due date of the current cycle, but the task has already been completed in this cycle, we would end up with a due date in the past and no new task would be created, although actually a new task should be created because the current one is already completed.
                if ($this->currentTaskAlreadyCompleted == true) {
                    $dueDate->modify("+3 months");                    
                    break;
                }

                break;

            case 'jährlich':
                $dueDate = $this->getJahresbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this year plus offset, move to next year
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+1 year");
                }

                // if the task has already been completed in the current cycle, we want to calculate the due date for the next cycle, not the current one, even if the reference date is still before the due date of the current cycle. Otherwise, if the reference date is before the due date of the current cycle, but the task has already been completed in this cycle, we would end up with a due date in the past and no new task would be created, although actually a new task should be created because the current one is already completed.
                if ($this->currentTaskAlreadyCompleted == true) {
                    $dueDate->modify("+1 year");                    
                    break;
                }

                break;

            case 'zweijährlich (gerade Jahreszahl)':
                
                $dueDate = $this->getJahresbeginn();

                $year = (int)$dueDate->format('Y');
                if ($year % 2 == 0) {
                    // Even year: due date is January 1st of the current year
                    $dueDate = $dueDate; //nothing changes
                } else {
                    // Odd year: due date is January 1st of the next year
                    $dueDate->modify("+1 years");
                }
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this year plus offset, move to next cycle
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+2 years");
                }

                // if the task has already been completed in the current cycle, we want to calculate the due date for the next cycle, not the current one, even if the reference date is still before the due date of the current cycle. Otherwise, if the reference date is before the due date of the current cycle, but the task has already been completed in this cycle, we would end up with a due date in the past and no new task would be created, although actually a new task should be created because the current one is already completed.
                if ($this->currentTaskAlreadyCompleted == true) {
                    $dueDate->modify("+2 years");                    
                    break;
                }

                break;

            case 'zweijährlich (ungerade Jahreszahl)':
                
                $dueDate = $this->getJahresbeginn();

                $year = (int)$dueDate->format('Y');
                if ($year % 2 == 0) {
                    // Even year: due date is January 1st of the next year
                    $dueDate->modify("+1 years");
                } else {
                    // Odd year: due date is January 1st of the current year
                    $dueDate = $dueDate; //nothing changes
                }
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this year plus offset, move to next cycle
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+2 years");
                }

                // if the task has already been completed in the current cycle, we want to calculate the due date for the next cycle, not the current one, even if the reference date is still before the due date of the current cycle. Otherwise, if the reference date is before the due date of the current cycle, but the task has already been completed in this cycle, we would end up with a due date in the past and no new task would be created, although actually a new task should be created because the current one is already completed.
                if ($this->currentTaskAlreadyCompleted == true) {
                    $dueDate->modify("+2 years");                    
                    break;
                }

                break;

            default:
                msg("Unknown cycle type in AUFGABE: $this->Cycle",-1);
                return $this->referenceDate;
        }

        //strip time portion
        $dueDate->setTime(23, 59, 59);

        // Temporär einfach das Basisdatum zurückgeben
        return $dueDate;
    }
    
    /**
     * Ermittelt relativ zum übergebenen Datum den Zeitpunkt des betreffenden Quartalsbeginns.
     */
    public function getQuartalsbeginn(): DateTime {
        $dateTime = $this->referenceDate;
        
        // Monat als Zahl (1–12)
        $month = (int)$dateTime->format('n');

        // Quartal berechnen (1–4)
        $quarter = (int)ceil($month / 3);

        // Ersten Monat des Quartals bestimmen
        $quarterStartMonth = ($quarter - 1) * 3 + 1;

        // Neues DateTime-Objekt für den Quartalsbeginn
        $quarterStart = new DateTime(
            sprintf('%d-%02d-01 00:00:00', $dateTime->format('Y'), $quarterStartMonth)
        );

        return $quarterStart;
    }

    /**
     * Ermittelt relativ zum übergebenen Datum den Zeitpunkt des betreffenden Jahresbeginns.
     */
    public function getJahresbeginn(): DateTime {
        $dateTime = $this->referenceDate;

        // Jahr als Zahl
        $year = (int)$dateTime->format('Y');

         // Neues DateTime-Objekt für den Jahresbeginn
        $yearStart = new DateTime(
            sprintf('%02d-01-01 00:00:00', $dateTime->format('Y'), $year)
        );
        
        return $yearStart;
    }

    public function getMonatsbeginn(): DateTime {
        $dateTime = $this->referenceDate;

        // Monat als Zahl (1–12)
        $month = (int)$dateTime->format('n');

        // Neues DateTime-Objekt für den Monatsbeginn
        $monthStart = new DateTime(
            sprintf('%d-%02d-01 00:00:00', $dateTime->format('Y'), $month)
        );

        return $monthStart;
    }

    public function getWochenbeginn(): DateTime {
        $dateTime = $this->referenceDate;

        // Wochentag als Zahl (1 für Montag, 7 für Sonntag)
        $weekday = (int)$dateTime->format('N');

        // Berechnung des Wochenbeginns (Montag)
        $weekStart = clone $dateTime;
        $weekStart->modify('-' . ($weekday - 1) . ' days');
        $weekStart->setTime(0, 0, 0);

        return $weekStart;
    }
    
    /**
     * Ermittelt das Datum, ab dem der Task angelegt werden soll (Fälligkeitsdatum minus LoiteringTime).
     */
    public function getLoiteringDate(): ?DateTime {
        $dueDate = $this->getNextDueDate();
        if (is_null($this->LoiteringTime)) {
            $loiteringDate = new DateTime("01.01.1970");
        } else {
            $loiteringDate = (clone $dueDate)->modify("-" . $this->LoiteringTime . " days");
        }

        $loiteringDate->setTime(0, 0, 0);
        
        return $loiteringDate;
    }
    
    /**
     * Ermittelt, ob wir uns mit dem Referenzdatum in dem Zeitraum befinden, in dem der Task angelegt werden soll.
     * @return bool
     */
    public function isReadyForCreation(): bool {
        $dueDate = $this->getNextDueDate();
        $loiteringDate = $this->getLoiteringDate();
        
        //msg("LoiteringTime: ".$this->LoiteringTime." --- referenceDate: ".$this->referenceDate->format('d.m.Y h:m:s')." --- dueDate: ".$dueDate->format('d.m.Y h:m:s')." --- LoiteringDate: ".$loiteringDate->format('d.m.Y h:m:s'));
        if ($loiteringDate <= $this->referenceDate && $this->referenceDate <= $dueDate) {
            return true;
        } else {
            return false;
        }
    }
}
