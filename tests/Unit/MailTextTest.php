<?php

namespace Tests\Unit;

use App\Services\Mail\MailText;
use App\Services\Mail\RitmParser;
use PHPUnit\Framework\TestCase;

class MailTextTest extends TestCase
{
    public function test_servicenow_email_is_cleaned_of_css_footer_and_rules(): void
    {
        $raw = file_get_contents(__DIR__.'/../fixtures/servicenow-zbox.txt');
        $clean = MailText::forDescription($raw);

        $this->assertStringContainsString('Z-BOX: CZ-02M3V, ID: 13931', $clean);
        $this->assertStringContainsString('D10 : ServiceNow slot 69 inventory order', $clean);
        foreach (['webkit', 'padding', '{', 'Unsubscribe', 'ServiceNow Inc', 'PRAVDILA', 'TO-DO LIST', 'Lawson Lane'] as $junk) {
            $this->assertStringNotContainsString($junk, $clean, $junk);
        }
        $this->assertDoesNotMatchRegularExpression('/\n\s*\n\s*\n/', $clean); // žádné bloky prázdných řádků
    }

    public function test_format2_from_real_email(): void
    {
        $d = (new RitmParser)->parse(file_get_contents(__DIR__.'/../fixtures/servicenow-zbox.txt'));

        $this->assertSame('CZ-02M3V', $d['locker_code']);
        $this->assertSame('13931', $d['locker_id']);
        $this->assertSame('Slot content check', $d['service_type']);
        $this->assertStringContainsString('Z2017250507', $d['description']);
        $this->assertSame(['city' => 'Bruntál', 'street' => 'tř. Práce 1960/1 (Kaufland)', 'zip' => '79201'], (new RitmParser)->splitAddress($d['location']));
    }
}
