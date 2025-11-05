<?php

class Periodicity
{
    public ?string $Type;
    public ?string $Cycle;
    public ?string $Offset;

    /**
     * Konstruktor 1: Nimmt ein Array
     * Beispiel: new Periodicity(['A', 'vierteljährlich', '+2 Tage'])
     */
    public function __construct(array|string $input) {
        if (is_array($input)) {
            $this->Type   = $input[0] ?? null;
            $this->Cycle  = $input[1] ?? null;
            $this->Offset = $input[2] ?? null;
        } elseif (is_string($input)) {
            $parts = explode(',',$input,3);
             $this->Type = $parts[0];
            if (sizeof($input)>1) {
                $this->Cycle = $parts[1];
                if (sizeof($input)>2) {
                    $this->Offset = $parts[2];
                }
            }
        } else {
            throw new InvalidArgumentException('Periodicity expects array or string.');
        }
    }

    /**
     * 
     */
    public function getNewDueDate(DateTime $baseDate): ?DateTime {
        
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
                        
                $baseDate = $this->getQuartalsbeginn($baseDate);
                
                // add Offset to date
                $baseDate->modify("+$this->Offset days");

            case 'jährlich':
                // TODO: Code für jährliche Wiederholung
                break;

            default:
                // Unbekannter Cycle
                return null;
        }

        // Temporär einfach das Basisdatum zurückgeben
        return $baseDate;
    }
    
    /**
     * Ermittelt relativ zum übergebenen Datum den Zeitpunkt des betreffenden Quartalsbeginns.
     */
    public function getQuartalsbeginn(DateTime $datetime): DateTime {
        // Monat als Zahl (1–12)
        $month = (int)$datetime->format('n');

        // Quartal berechnen (1–4)
        $quarter = (int)ceil($month / 3);

        // Ersten Monat des Quartals bestimmen
        $quarterStartMonth = ($quarter - 1) * 3 + 1;

        // Neues DateTime-Objekt für den Quartalsbeginn
        $quarterStart = new DateTime(
            sprintf('%d-%02d-01 00:00:00', $datetime->format('Y'), $quarterStartMonth)
        );

        return $quarterStart;
    }
}
