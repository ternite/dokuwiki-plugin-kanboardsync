<?php

class Periodicity
{
    public ?DateTime $referenceDate;
    public ?string $Type;           // 'wiederkehrend', 'kontinuierlich'
    public ?string $Cycle;          // 'täglich', 'wöchentlich', ...
    public ?string $Offset;         // Anzahl Tage ab Cycle, zu dem das DueDate gilt
    public ?string $LoiteringTime;  // Anzahl Tage vor dem DueDate, ab wann der Task angelegt werden soll

    /**
     * Konstruktor 1: Nimmt ein Array
     * Beispiel: new Periodicity(['A', 'vierteljährlich', '+2 Tage'])
     */
    public function __construct(DateTime $referenceDate, array|string $input) {
        $this->referenceDate = $referenceDate;
        if (is_null($referenceDate)) {
            $this->referenceDate = new DateTime();
        }
        
        if (is_array($input)) {
            $this->setMembersFromArray($input);
        } elseif (is_string($input)) {
            $parts = explode(',',$input);
            $this->setMembersFromArray($parts);
        } else {
            throw new InvalidArgumentException('Periodicity expects array or string.');
        }
    }

    private function setMembersFromArray(array $input) {
        if (is_array($input)) {
            $this->Type          = $input[0] ?? null;
            $this->Cycle         = $input[1] ?? null;
            $this->Offset        = $input[2] ?? null;
            $this->LoiteringTime = $input[3] ?? null;
        }
    }
    
    /**
     * 
     */
    public function getDueDate(): ?DateTime {
        
        switch ($this->Cycle) {
            case 'täglich':
                // TODO: Code für tägliche Wiederholung
                break;

            case 'wöchentlich':
                // TODO: Code für wöchentliche Wiederholung
                break;

            case 'monatlich':
                // TODO: Code für monatliche Wiederholung
                break;

            case 'vierteljährlich':
                        
                $dueDate = $this->getQuartalsbeginn();
                
                // add Offset to date
                $dueDate->modify("+$this->Offset days");

            case 'jährlich':
                // TODO: Code für jährliche Wiederholung
                break;

            default:
                // Unbekannter Cycle
                return null;
        }

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
    
    public function getLoiteringDate(): ?DateTime {
        $dueDate = $this->getDueDate($this->referenceDate);
        if (is_null($this->LoiteringTime)) {
            $loiteringDate = new DateTime("01.01.1970");
        } else {
            $loiteringDate = $dueDate->modify("-$this->LoiteringTime days");
        }
        
        return $loiteringDate;
    }
    
    public function isReadyForCreation(): bool {
        $dueDate = $this->getDueDate($this->referenceDate);
        $loiteringDate = $this->getLoiteringDate($this->referenceDate);
        
        msg("LoiteringTime: ".$this->LoiteringTime." --- referenceDate: ".$this->referenceDate->format('d.m.Y')." --- dueDate: ".$dueDate->format('d.m.Y')." --- LoiteringDate: ".$loiteringDate->format('d.m.Y'),2);
        if ($loiteringDate <= $this->referenceDate && $this->referenceDate <= $dueDate) {
            return true;
        } else {
            return false;
        }
    }
}
