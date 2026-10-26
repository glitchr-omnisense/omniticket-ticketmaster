# omniticket/ticketmaster

Ticketmaster for [omnisense/omniticket](https://github.com/glitchr-omnisense/omniticket): public
events searched and read through Ticketmaster's Discovery API v2
(`https://app.ticketmaster.com/discovery/v2`, documented at `https://developer.ticketmaster.com`),
the app's key as `apikey`. A public catalogue: no attendee, no check - the tickets are
Ticketmaster's, bought at the event's `url`. No maintained PHP client of the Discovery API was
found to reuse.

| Operation | Discovery API v2 |
|---|---|
| `events()` | `GET /events.json` (the configured filters, `startDateTime`, `endDateTime`, `keyword`, `sort=date,asc`, `size=50`, `page`) |
| `event($id)` | `GET /events/{id}.json` |

## Installation

```sh
composer require omniticket/ticketmaster symfony/http-client
```

Requires PHP 8.2, `omnisense/omniticket` (1.x) and `symfony/http-client-contracts` (^3.0).

## Credentials

The **consumer key** of an app on developer.ticketmaster.com.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the app's consumer key |
| `country_code` | string | none | no | ISO 3166-1 alpha-2, every search narrowed to it (`countryCode`) |
| `city` | string | none | no | `city` |
| `venue_id` | string | none | no | `venueId`: a venue's events |
| `attraction_id` | string | none | no | `attractionId`: an artist's events |
| `classification_name` | string | none | no | `classificationName` |
| `locale` | string | none | no | `locale` |
| `url` | string | `https://app.ticketmaster.com/discovery/v2` | no | |

The filters set are sent with every search.

## Capabilities

| Contract | Supported |
|---|---|
| `EventCatalogInterface` | yes |
| `AttendeeListInterface`, `TicketCheckInterface` | no: the registry refuses them (`NotSupportedException`) |

`capabilities()`: catalog only; terms `https://developer.ticketmaster.com/support/terms-of-use/`;
limits: default quota 5,000 calls a day, 5 a second per key; deep paging no further than the
1000th result of a search; link to the event's url to buy; display and caching follow
Ticketmaster's terms of use.

## Behaviour

**Search.** `startDateTime` is the query's `from`, else now (not with `past`), and `endDateTime`
its `to`, both in UTC (`Y-m-d\TH:i:s\Z`); its keyword is sent as `keyword`; the core's filter then
applies the whole query. Pages of 50 while `page.totalPages` says there are more, stopping at the
20th page: the API serves no further than the 1000th result.

**An event.** `id`, `name`, the start (`dates.start.dateTime`, else `dates.start.localDate` in
`dates.timezone`), the end (`dates.end.dateTime`), `url`, the first venue's name and city, the
first price range (`min`, `max` in units of its currency) as `priceFrom` and `priceTo`. Status:
`onsale` - upcoming when the public sale starts later, else on sale; `offsale` sales closed;
`cancelled`, `canceled` cancelled; `postponed` postponed; `rescheduled` on sale; anything else
unknown. No rates, sessions or places left.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omniticket\Model\EventQuery;
use Omniticket\Registry;
use Omniticket\Ticketmaster\TicketmasterFactory;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new TicketmasterFactory(HttpClient::create(['timeout' => 10]))], [
    'concerts' => ['factory' => 'ticketmaster', 'options' => ['api_key' => getenv('TICKETMASTER_API_KEY'), 'country_code' => 'FR', 'city' => 'Paris']],
]);

foreach ($registry->catalog('concerts')->events(new EventQuery(keyword: 'jazz', limit: 10)) as $event) {
    printf("%s %s, %s - %s\n", $event->start?->format('d/m'), $event->title, $event->venue, $event->url);
}
```

Not run live (no key). An unknown event, on a recorded answer on 2026-10-26: `Ticketmaster has
no event "9": Resource not found with provided criteria (locale=en-us, id=9)`.

## Errors

- 401, 403: `InvalidKeyException`; 404: `NotFoundException` with Ticketmaster's detail; another
  status of 400 or more (a 429 past the quota), or an answer that is not JSON:
  `ProviderException`, the status as its code.
- A network failure: `UnreachableException`.

## Verified, and not

`Tests/TicketmasterProviderTest.php`, against answers composed from the fields the API documents,
not recorded: a search and its parameters, statuses, price range and venue, dates with and without
a time, the deep-paging limit, one event and an unknown one, the absence of attendees and check;
`BareTest`. Green in the family's harness on 2026-10-26. Not run live: no Ticketmaster key was
used.

## Responsibility

This package is an independent, unofficial client, affiliated with Ticketmaster in no way.
Whoever runs it keeps Ticketmaster's [terms of
use](https://developer.ticketmaster.com/support/terms-of-use/); see the README.
