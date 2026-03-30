<?php

namespace dokuwiki\plugin\kanboardsync\helper\test;

require_once __DIR__ . '\..\helper\Periodicity.php';

use DateTime;

/**
 * @group plugin_kanboardsync
 * @group plugins
 */
class PeriodicityTest extends \DokuWikiTest {
 
    protected $pluginsEnabled = ['kanboardsync', 'mo'];
 
    public static function setUpBeforeClass() : void {
        parent::setUpBeforeClass();
        // copy our own config files to the test directory
        //\TestUtils::rcopy(dirname(DOKU_CONF), dirname(__FILE__) . '\..\conf');
    }
 
    public function testExample() {
        $this->assertTrue(true, 'if this fails your computer is broken');
    }

    public function testDailyDueDateIsReferenceDate()
    {
        $ref = new DateTime('2026-03-09 10:00:00');

        $p = new \Periodicity(['wiederkehrend','täglich','0','0'], $ref);

        $due = $p->getNextDueDate();

        $this->assertEquals('2026-03-09 23:59:59', $due->format('Y-m-d H:i:s'));
    }

    public function testWeeklyDueDateWithOffset()
    {
        $ref = new DateTime('2025-03-12 10:00:00'); // Mittwoch

        $p = new \Periodicity(['wiederkehrend','wöchentlich','2','0'], $ref);

        $due = $p->getNextDueDate();

        // Wochenbeginn = Montag 10.03.2025
        // +2 Tage = Mittwoch 12.03.2025
        // Da Referenzdatum gleich -> nächste Woche
        $this->assertEquals('2025-03-19 23:59:59', $due->format('Y-m-d H:i:s'));
    }

    public function testMonthlyDueDateOffset()
    {
        $ref = new DateTime('2025-03-05');

        $p = new \Periodicity(['wiederkehrend','monatlich','10','0'], $ref);

        $due = $p->getNextDueDate();

        $this->assertEquals('2025-03-11 23:59:59', $due->format('Y-m-d H:i:s'));
    }

    public function testMonthlyDueDateMovesToNextMonthIfPast()
    {
        $ref = new DateTime('2025-03-20');

        $p = new \Periodicity(['wiederkehrend','monatlich','5','0'], $ref);

        $due = $p->getNextDueDate();

        // Monatsbeginn 01.03 +5 = 06.03 -> schon vorbei
        // daher April
        $this->assertEquals('2025-04-06 23:59:59', $due->format('Y-m-d H:i:s'));
    }

    public function testQuarterlyDueDate()
    {
        $ref = new DateTime('2025-05-10');

        $p = new \Periodicity(['wiederkehrend','vierteljährlich','10','0'], $ref);

        $due = $p->getNextDueDate();

        // Quartal Q2 -> 01.04 +10 Tage
        $this->assertEquals('2025-04-11 23:59:59', $due->format('Y-m-d H:i:s'));
    }

    public function testYearlyDueDate()
    {
        $ref = new DateTime('2025-03-15');

        $p = new \Periodicity(['wiederkehrend','jährlich','30','0'], $ref);

        $due = $p->getNextDueDate();

        $this->assertEquals('2025-01-31 23:59:59', $due->format('Y-m-d H:i:s'));
    }

    public function testLoiteringDateCalculation()
    {
        $ref = new DateTime('2025-03-01');

        $p = new \Periodicity(['wiederkehrend','monatlich','10','5'], $ref);

        $loiter = $p->getLoiteringDate();

        // DueDate = 11.03 -> minus 5 Tage = 06.03
        $this->assertEquals('2025-03-06 00:00:00', $loiter->format('Y-m-d H:i:s'));
    }

    public function testLoiteringDateDefaultWhenNull()
    {
        $ref = new DateTime('2025-03-01');

        $p = new \Periodicity(['wiederkehrend','monatlich','10',null], $ref);

        $loiter = $p->getLoiteringDate();

        $this->assertEquals('1970-01-01 00:00:00', $loiter->format('Y-m-d H:i:s'));
    }

    public function testIsReadyForCreationTrueInsideWindow()
    {
        $ref = new DateTime('2025-03-08');

        $p = new \Periodicity(['wiederkehrend','monatlich','10','5'], $ref);

        $this->assertTrue($p->isReadyForCreation());
    }

    public function testIsReadyForCreationFalseBeforeWindow()
    {
        $ref = new DateTime('2025-03-01');

        $p = new \Periodicity(['wiederkehrend','monatlich','10','5'], $ref);

        $this->assertFalse($p->isReadyForCreation());
    }

    public function testIsReadyForCreationFalseAfterDueDate()
    {
        $ref = new DateTime('2025-03-20');

        $p = new \Periodicity(['wiederkehrend','monatlich','5','3'], $ref);

        $this->assertFalse($p->isReadyForCreation());
    }
}
