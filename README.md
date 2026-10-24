# omniticket/ticketmaster

[omniscient/omniticket](https://github.com/glitchr-studio/omniticket)'s Ticketmaster, under any
PHP host: public events from the Discovery API - dates, venue, price range, sale status and the
purchase link. A catalogue, read only: the tickets are Ticketmaster's, bought at the event's
`url`; there are no attendees and no check.

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

## Verified, and not

- Verified (the core's harness, PHP 8.4): a search and its parameters, statuses, price range and
  venue, dates with and without a time, the deep-paging limit, one event and an unknown one, the
  absence of attendees and check - against answers composed from the fields the API documents, not
  recorded; every class loading with the package's requirements alone (`BareTest`).
- Not verified live: no Ticketmaster key was used.

## Licence

MIT. An unofficial client, affiliated with Ticketmaster in no way.
