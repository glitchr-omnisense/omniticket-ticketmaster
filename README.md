# omniticket/ticketmaster

[omnisense/omniticket](https://github.com/glitchr-omnisense/omniticket)'s Ticketmaster, under any
PHP host: public events from the Discovery API - dates, venue, price range, sale status and the
purchase link. A catalogue, read only: the tickets are Ticketmaster's, bought at the event's
`url`; there are no attendees and no check.

## Install and a first call

```sh
composer require omniticket/ticketmaster
```

No account for this first call: the Discovery API's answer is recorded
(`Omniticket\Testing\RecordedHttpClient` answers in place of the API), so nothing leaves the process.
A real call needs an API key: a free app on developer.ticketmaster.com.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omniticket\Testing\RecordedHttpClient;
use Omniticket\Ticketmaster\TicketmasterFactory;

// The Discovery API's answer, recorded: nothing sent. HttpClient::create() (symfony/http-client) for a real call.
$http = RecordedHttpClient::routes([
    'GET /events.json' => '{"_embedded": {"events": [{"name": "Example Band - World Tour", "id": "vvG1zZ9KbqA0Ex", "url": "https://www.ticketmaster.fr/fr/manifestation/example-band-billet/idmanif/000000", "dates": {"start": {"localDate": "2036-06-20", "localTime": "20:00:00", "dateTime": "2036-06-20T18:00:00Z"}, "timezone": "Europe/Paris", "status": {"code": "onsale"}}, "priceRanges": [{"currency": "EUR", "min": 45.5, "max": 89.0}]}]}, "page": {"size": 50, "totalElements": 1, "totalPages": 1, "number": 0}}',
]);
$ticketmaster = (new TicketmasterFactory($http))->create(['api_key' => 'recorded', 'country_code' => 'FR']);

foreach ($ticketmaster->events() as $event) {
    echo $event->title, ' ', $event->start?->format('d/m/Y H:i'), ' ', $event->url, "\n";   // Example Band - World Tour 20/06/2036 18:00 (UTC) https://www.ticketmaster.fr/...
}
```

With your key, through the family's registry:

```php
use Omniticket\Registry;
use Omniticket\Ticketmaster\TicketmasterFactory;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new TicketmasterFactory(HttpClient::create())], [
    'concerts' => ['factory' => 'ticketmaster', 'options' => ['api_key' => '...', 'country_code' => 'FR', 'attraction_id' => '...']],
]);
```

Options: `api_key` (an app on developer.ticketmaster.com), and filters every search carries:
`country_code`, `city`, `venue_id`, `attraction_id`, `classification_name`, `locale`; `url`.

## The API

Discovery API v2 (<https://developer.ticketmaster.com>): `GET /events.json` (`startDateTime`,
`endDateTime`, `keyword`, `sort=date,asc`, `size` 50, `page`) and `/events/{id}.json`, the key as
`apikey`. Status codes: onsale, offsale, cancelled, postponed, rescheduled.

**Terms** (<https://developer.ticketmaster.com/support/terms-of-use/>): the default quota is
5,000 calls a day and 5 a second per key; a search serves no further than its 1000th result
(size x page < 1000: this package stops there); link to the event's `url` to buy; display and
caching follow Ticketmaster's terms of use.

No maintained PHP client of the Discovery API was found to reuse.

## Usage policy

Declared as data in `policy.json` (omnisense/contracts' schema), read on 2026-11-10: 5,000 calls a
day and 5 a second per key ([Getting
started](https://developer.ticketmaster.com/products-and-docs/apis/getting-started/)); commercial
use forbidden (no revenue from the API, [Terms of
Use](https://developer.ticketmaster.com/support/terms-of-use/), 2023-06-27), caching allowed for
reasonable periods, storage beyond it and redistribution forbidden; no attribution in the terms. A
listing counts one call, its pages are Ticketmaster's calls each. Through omniticket's registry
every call is checked against it, a 429 holds the next calls, a commercial site is refused at once,
and a site may tighten it, never loosen it.

## Verified, and not

- Verified (the core's harness, PHP 8.4): a search and its parameters, statuses, price range and
  venue, dates with and without a time, the deep-paging limit, one event and an unknown one, the
  absence of attendees and check - against answers composed from the fields the API documents, not
  recorded; every class loading with the package's requirements alone (`BareTest`).
- Not verified live: no Ticketmaster key was used.

## Licence

MIT. An unofficial client, affiliated with Ticketmaster in no way.
