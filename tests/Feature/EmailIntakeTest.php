<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\InboundEmail;
use App\Models\Location;
use App\Models\Mailbox;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\Mail\MailboxImporter;
use App\Services\Mail\MailboxReader;
use App\Services\Mail\ParsedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mime\Email;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

class FakeMailboxReader implements MailboxReader
{
    /** @var list<ParsedEmail> */
    public array $messages = [];

    public function fetchNew(Mailbox $mailbox, int $limit = 30): iterable
    {
        return array_values(array_filter($this->messages, fn ($m) => $m->uid > $mailbox->last_uid));
    }

    public function test(Mailbox $mailbox): int
    {
        return count($this->messages);
    }
}

/** Kontrolní body 3.3 a 3.4 – příjem požadavků e-mailem */
class EmailIntakeTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    private FakeMailboxReader $reader;

    private Mailbox $mailbox;

    /** @var list<Email> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
        $this->locA->update(['code' => 'ZB-1234', 'street' => 'Nádražní 196', 'city' => 'Ostrava', 'zip' => '702 00']);
        $this->locB->update(['code' => 'ZB-5678', 'street' => 'Plzeňská 8', 'city' => 'Praha']);

        $this->reader = new FakeMailboxReader;
        $this->app->instance(MailboxReader::class, $this->reader);

        $this->mailbox = Mailbox::create([
            'name' => 'Zásilkovna servis', 'project_id' => $this->zas->id,
            'imap_host' => 'imap.test', 'imap_username' => 'servis@test.cz', 'imap_password' => 'tajne',
            'smtp_host' => 'smtp.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
            'smtp_username' => 'servis@test.cz', 'smtp_password' => 'tajne', 'from_address' => 'servis@test.cz',
        ]);

        config(['pap.confirmation_transport' => 'array']);
        Event::listen(MessageSent::class, fn (MessageSent $e) => $this->sent[] = $e->message);
    }

    private function msg(int $uid, string $id, string $subject, string $text, array $extra = []): ParsedEmail
    {
        return new ParsedEmail(
            uid: $uid, messageId: $id, inReplyTo: $extra['inReplyTo'] ?? null, references: $extra['references'] ?? null,
            fromEmail: 'provoz@zakaznik.cz', fromName: 'Jana Provozní', subject: $subject, text: $text,
            date: Carbon::now(), attachments: $extra['attachments'] ?? [],
        );
    }

    private function runImport(): void
    {
        app(MailboxImporter::class)->import($this->mailbox->fresh());
    }

    public function test_email_creates_one_ticket_and_one_confirmation_in_same_thread(): void
    {
        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'Nefunguje Z-BOX ZB-1234', "Dobrý den,\nZ-BOX ZB-1234 nejde otevřít schránka 12.\nDěkuji");
        $this->runImport();

        $ticket = Ticket::sole();
        $this->assertSame('email', $ticket->source);
        $this->assertSame($this->locA->id, $ticket->location_id);
        $this->assertFalse($ticket->needs_review);
        $this->assertSame('provoz@zakaznik.cz', $ticket->reporter_email);

        $this->assertCount(1, $this->sent);
        $mail = $this->sent[0];
        $this->assertStringContainsString($ticket->number, $mail->getSubject());
        $this->assertStringContainsString($ticket->number, $mail->getTextBody());
        $this->assertSame('provoz@zakaznik.cz', $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('abc-1@zakaznik.cz', $mail->getHeaders()->get('In-Reply-To')->getBodyAsString());
        $this->assertNotNull(InboundEmail::sole()->confirmation_sent_at);
    }

    public function test_reading_same_message_again_does_not_duplicate(): void
    {
        $m = $this->msg(10, '<abc-1@zakaznik.cz>', 'ZB-1234 nefunguje', 'ZB-1234 nejde otevřít');
        $this->reader->messages[] = $m;
        $this->runImport();

        // schránka vrátí stejnou zprávu znovu (např. po resetu UID)
        $this->mailbox->update(['last_uid' => 0]);
        $this->runImport();
        app(MailboxImporter::class)->store($this->mailbox, $m);

        $this->assertSame(1, InboundEmail::count());
        $this->assertSame(1, Ticket::count());
        $this->assertCount(1, $this->sent);
    }

    public function test_reply_with_photo_is_attached_to_the_right_ticket(): void
    {
        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'ZB-1234 nefunguje', 'ZB-1234 nejde otevřít');
        $this->runImport();
        $ticket = Ticket::sole();
        $confirmationId = InboundEmail::sole()->confirmation_message_id;

        // zákazník odpoví na naše potvrzení a přiloží fotku
        $this->reader->messages[] = $this->msg(11, '<abc-2@zakaznik.cz>', 'Re: ZB-1234 nefunguje ['.$ticket->number.']', "Posílám fotku.\n\n> Dobrý den, potvrzujeme…", [
            'inReplyTo' => $confirmationId,
            'references' => '<abc-1@zakaznik.cz> '.$confirmationId,
            'attachments' => [['name' => 'foto.jpg', 'mime' => 'image/jpeg', 'content' => 'JPEGDATA']],
        ]);
        $this->runImport();

        $this->assertSame(1, Ticket::count());
        $reply = InboundEmail::where('uid', 11)->sole();
        $this->assertSame('appended', $reply->status);
        $this->assertSame($ticket->id, $reply->ticket_id);
        $att = Attachment::where('attachable_type', 'ticket')->where('attachable_id', $ticket->id)->sole();
        $this->assertSame('photo', $att->kind);
        $this->assertStringContainsString('Posílám fotku', $ticket->history()->reorder()->latest('id')->value('note'));
        $this->assertStringNotContainsString('potvrzujeme', $ticket->history()->reorder()->latest('id')->value('note'));
        $this->assertCount(1, $this->sent); // na odpověď se další potvrzení neposílá

        // dispečer fotku vidí a stáhne
        Sanctum::actingAs($this->dispecer);
        $this->get("/api/v1/attachments/{$att->uuid}/download")->assertOk();
    }

    public function test_reply_matched_by_ticket_number_in_subject(): void
    {
        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'ZB-1234 nefunguje', 'ZB-1234');
        $this->runImport();
        $ticket = Ticket::sole();

        // jiný klient pošle novou zprávu (bez In-Reply-To), ale s číslem v předmětu
        $this->reader->messages[] = $this->msg(11, '<xyz@jinde.cz>', 'Doplnění k '.$ticket->number, 'Ještě nejde ani schránka 13.');
        $this->runImport();

        $this->assertSame(1, Ticket::count());
        $this->assertSame('appended', InboundEmail::where('uid', 11)->value('status'));
    }

    public function test_unclear_location_goes_to_dispatcher_review(): void
    {
        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'Porucha', 'Na naší pobočce nejde boxík, prosím o servis.');
        $this->runImport();

        $ticket = Ticket::sole();
        $this->assertNull($ticket->location_id);
        $this->assertTrue($ticket->needs_review);
        $this->assertSame('review', InboundEmail::sole()->status);
        $this->assertCount(1, $this->sent); // číslo zákazník dostane i tak

        Sanctum::actingAs($this->dispecer);
        $this->getJson('/api/v1/tickets?needs_review=1')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonPath('data.needs_review', true)->assertJsonPath('data.email.subject', 'Porucha');
        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['location_id' => $this->locB->id])->assertOk();
        $this->assertFalse($ticket->fresh()->needs_review);
    }

    public function test_failed_save_sends_no_confirmation(): void
    {
        Ticket::creating(fn () => throw new \RuntimeException('DB je pryč'));
        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'ZB-1234', 'ZB-1234 nejde');
        $this->runImport();

        $this->assertSame(0, Ticket::count());
        $this->assertSame('error', InboundEmail::sole()->status);
        $this->assertCount(0, $this->sent);
    }

    public function test_ai_hints_find_location_by_address(): void
    {
        Setting::putSecret('anthropic_api_key', 'sk-test');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [[
            'type' => 'tool_use', 'name' => 'zapsat_pozadavek',
            'input' => [
                'is_request' => true, 'service_type' => 'servis', 'priority' => 'urgent', 'language' => 'cs',
                'title' => 'Nejde otevřít schránka', 'summary' => 'Zákazník nemůže vyzvednout zásilku.',
                'location_hints' => ['city' => 'Praha', 'street' => 'Plzeňská 8'], 'confidence' => 0.9, 'reason' => 'adresa v textu',
            ],
        ]]])]);

        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'Porucha Smíchov', 'Box na Plzeňské 8 v Praze nejde otevřít, spěchá!');
        $this->runImport();

        $ticket = Ticket::sole();
        $this->assertSame($this->locB->id, $ticket->location_id);
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame('Nejde otevřít schránka', $ticket->title);
        Http::assertSent(fn ($r) => $r['tool_choice']['name'] === 'zapsat_pozadavek' && $r->hasHeader('x-api-key', 'sk-test'));
    }

    public function test_non_request_in_shared_mailbox_is_ignored(): void
    {
        $this->mailbox->update(['dedicated' => false]);
        Setting::putSecret('anthropic_api_key', 'sk-test');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [[
            'type' => 'tool_use', 'name' => 'zapsat_pozadavek',
            'input' => ['is_request' => false, 'service_type' => 'servis', 'priority' => 'normal', 'language' => 'cs',
                'title' => 'Faktura', 'summary' => '', 'location_hints' => [], 'confidence' => 0, 'reason' => 'faktura'],
        ]]])]);

        $this->reader->messages[] = $this->msg(10, '<inv@dodavatel.cz>', 'Faktura 2026/11', 'V příloze zasíláme fakturu.');
        $this->runImport();

        $this->assertSame(0, Ticket::count());
        $this->assertSame('ignored', InboundEmail::sole()->status);
        $this->assertCount(0, $this->sent);

        // dispečer ho může přesto převést na zakázku
        Sanctum::actingAs($this->dispecer);
        $this->postJson('/api/v1/emails/'.InboundEmail::sole()->id.'/action', ['action' => 'reprocess'])->assertOk();
        $this->assertSame(1, Ticket::count());
    }

    private function ritmBody(string $locker = 'CZ-OST1234', string $id = '98765'): string
    {
        return "Dobrý den,\n\nService type: Výměna zámku schránky\nLocker: {$locker} ID: {$id}\n"
            ."Location: Ostrava, Nádražní 196, 702 00 (GPS: 49.8353 18.2928)\nPriority: 2 - High\n"
            ."Description: Schránka č. 12 nejde otevřít.\nZákazník čeká.\n\nClick here to view the request";
    }

    public function test_ritm_email_creates_ticket_and_location_without_ai(): void
    {
        $this->mailbox->update(['parser' => 'zasilkovna_ritm', 'auto_reply' => false]);
        $this->reader->messages[] = $this->msg(10, '<r1@packeta>', 'RITM0012345 - Service request', $this->ritmBody());
        $this->runImport();

        $ticket = Ticket::sole();
        $this->assertSame('RITM0012345', $ticket->external_ref);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('Výměna zámku schránky – CZ-OST1234', $ticket->title);
        $this->assertStringContainsString('Schránka č. 12 nejde otevřít', $ticket->description);
        $this->assertFalse($ticket->needs_review);

        $loc = Location::find($ticket->location_id);
        $this->assertSame('CZ-OST1234', $loc->code);
        $this->assertSame('98765', $loc->external_id);
        $this->assertSame('Ostrava', $loc->city);
        $this->assertSame('Nádražní 196', $loc->street);
        $this->assertSame('702 00', $loc->zip);
        $this->assertEqualsWithDelta(49.8353, $loc->lat, 0.0001);
        $this->assertCount(0, $this->sent); // potvrzení vypnuté
    }

    public function test_same_ritm_twice_appends_and_existing_box_is_reused(): void
    {
        $this->mailbox->update(['parser' => 'zasilkovna_ritm']);
        $this->locA->update(['external_id' => '98765']);

        $this->reader->messages[] = $this->msg(10, '<r1@packeta>', 'RITM0012345 - Service request', $this->ritmBody('CZ-JINY', '98765'));
        $this->reader->messages[] = $this->msg(11, '<r2@packeta>', 'RITM0012345 - Update', "Status changed to In progress\n\n".$this->ritmBody('CZ-JINY', '98765'));
        $this->reader->messages[] = $this->msg(12, '<x@packeta>', 'Newsletter Packeta', 'Novinky…');
        $this->runImport();

        $ticket = Ticket::sole();
        $this->assertSame($this->locA->id, $ticket->location_id); // box podle ID, nová provozovna nevznikla
        $this->assertSame(3, Location::count());
        $this->assertSame('appended', InboundEmail::where('uid', 11)->value('status'));
        $this->assertSame('ignored', InboundEmail::where('uid', 12)->value('status'));
    }

    public function test_unreadable_ritm_is_error_and_can_be_forced_by_dispatcher(): void
    {
        $this->mailbox->update(['parser' => 'zasilkovna_ritm']);
        $this->reader->messages[] = $this->msg(10, '<r1@packeta>', 'RITM0099999', 'Úplně jiný formát bez lockeru, box ZB-1234 nejde.');
        $this->runImport();

        $this->assertSame(0, Ticket::count());
        $email = InboundEmail::sole();
        $this->assertSame('error', $email->status);

        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/emails/{$email->id}/action", ['action' => 'reprocess'])->assertOk()->assertJsonPath('data.status', 'ticket');
        $this->assertSame($this->locA->id, Ticket::sole()->location_id); // obecné zpracování našlo kód ZB-1234
    }

    public function test_mailbox_admin_and_email_access(): void
    {
        Sanctum::actingAs($this->dispecer);
        $this->getJson('/api/v1/admin/mailboxes')->assertForbidden();
        $this->getJson('/api/v1/emails')->assertOk();

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/mailboxes')->assertOk();
        $this->assertArrayNotHasKey('imap_password', $res->json('data.0'));
        $this->assertTrue($res->json('data.0.has_imap_password'));
        $this->assertNotSame('tajne', Mailbox::first()->getRawOriginal('imap_password')); // uloženo šifrovaně

        // prázdné heslo při úpravě = beze změny
        $this->patchJson("/api/v1/admin/mailboxes/{$this->mailbox->id}", ['name' => 'Servis ZAS', 'imap_password' => ''])->assertOk();
        $this->assertSame('tajne', $this->mailbox->fresh()->imap_password);

        $this->putJson('/api/v1/admin/ai', ['api_key' => 'sk-ant-123456789'])->assertOk()
            ->assertJsonPath('has_key', true)->assertJsonPath('key_hint', '…6789');

        // dispečer cizího projektu e-maily nevidí
        $alg = $this->makeUser('dispalg', [Role::Dispecer]);
        $alg->projects()->attach($this->alg->id);
        $this->reader->messages[] = $this->msg(10, '<abc-1@zakaznik.cz>', 'ZB-1234', 'ZB-1234');
        $this->runImport();
        Sanctum::actingAs($alg);
        $this->getJson('/api/v1/emails')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/emails/'.InboundEmail::sole()->id)->assertForbidden();
    }
}
