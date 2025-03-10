<?php
declare(strict_types=1);

namespace Robert2\Tests;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Illuminate\Support\Carbon;
use Robert2\API\Models\Setting;

final class CalendarTest extends ApiTestCase
{
    public function testPublic()
    {
        Carbon::setTestNow(Carbon::create(2019, 02, 01, 12, 00, 00));

        //
        // - Doit renvoyer une 404 si l'UUID n'est pas le bon.
        //

        $this->client->get('/calendar/public/-unknown-.ics');
        $this->assertStatusCode(StatusCode::STATUS_NOT_FOUND);

        //
        // - Doit renvoyer le calendrier.
        //

        $responseStream = $this->client->get('/calendar/public/dfe7cd82-52b9-4c9b-aaed-033df210f23b.ics');
        $this->assertStatusCode(StatusCode::STATUS_OK);
        $this->assertTrue($responseStream->isReadable());
        $this->assertMatchesTextSnapshot($responseStream->getContents());

        //
        // - Doit renvoyer une 404 si le calendrier public est désactivé.
        //

        Setting::findOrFail('calendar.public.enabled')->update(['value' => false]);

        $this->client->get('/calendar/public/dfe7cd82-52b9-4c9b-aaed-033df210f23b.ics');
        $this->assertStatusCode(StatusCode::STATUS_NOT_FOUND);
    }
}
