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

    /**
     * Konstruktor 1: Nimmt ein Array oder einen String
     * @param array|string $input Array oder String mit Periodizitätsangaben
     * Beispiel 1: new Periodicity(['wiederkehrend', 'vierteljährlich', '85', '7'])
     * Beispiel 2: new Periodicity('wiederkehrend,vierteljährlich,85,7')
     * 
     */
    public function __construct($input, ?DateTime $referenceDate = null) {
        $this->referenceDate = $referenceDate;
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

                break;

            case 'monatlich':
                $dueDate = $this->getMonatsbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this month plus offset, move to next month
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+1 month");
                }
                break;

            case 'vierteljährlich':
                        
                $dueDate = $this->getQuartalsbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                break;

            case 'jährlich':
                $dueDate = $this->getJahresbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

                //if the reference date is already past this year plus offset, move to next year
                if ($dueDate <= $this->referenceDate) {
                    $dueDate->modify("+1 year");
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
                    $dueDate->modify("+2 year");
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
                    $dueDate->modify("+2 year");
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
