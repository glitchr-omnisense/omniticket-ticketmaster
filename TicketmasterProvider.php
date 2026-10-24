<?php

namespace Omniticket\Ticketmaster;

use Omniticket\Capabilities;
use Omniticket\EventCatalogInterface;
use Omniticket\Http\JsonApi;
use Omniticket\Model\Event;
use Omniticket\Model\EventQuery;
use Omniticket\Model\EventStatus;
use Omniticket\Model\Money;
use Omniticket\Support\Data;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ticketmaster's Discovery API v2 (https://developer.ticketmaster.com):
 * GET /events.json and /events/{id}.json with the app's key. A public
 * catalogue: no attendee, no check - the tickets are Ticketmaster's, bought
 * at the event's url.
 */
final class TicketmasterProvider implements EventCatalogInterface
{
    private const SIZE = 50;

    /** The Discovery API serves no further than the 1000th result of a search (size x page < 1000). */
    private const DEEPEST = 1000;

    private readonly JsonApi $api;

    /** @param array<string, string> $filters search parameters every listing carries (countryCode, venueId...) */
    public function __construct(
        HttpClientInterface $http,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly array $filters = [],
        private readonly string $url = 'https://app.ticketmaster.com/discovery/v2',
    ) {
        $this->api = new JsonApi($http, 'Ticketmaster');
    }

    public function getName(): string
    {
        return 'ticketmaster';
    }

    public function getTitle(): string
    {
        return 'Ticketmaster';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            catalog: true,
            terms: ['https://developer.ticketmaster.com/support/terms-of-use/'],
            limits: ['default quota: 5,000 calls a day, 5 a second per key', 'deep paging: no further than the 1000th result of a search', 'link to the event\'s url to buy; display and caching follow Ticketmaster\'s terms of use'],
        );
    }

    public function events(EventQuery $query = new EventQuery()): iterable
    {
        $search = $this->filters + ['size' => (string) self::SIZE, 'sort' => 'date,asc'];
        $from = $query->from ?? ($query->past ? null : ($query->now ?? new \DateTimeImmutable()));
        if (null !== $from) {
            $search['startDateTime'] = self::utc($from);
        }
        if (null !== $query->to) {
            $search['endDateTime'] = self::utc($query->to);
        }
        if (null !== $query->keyword && '' !== $query->keyword) {
            $search['keyword'] = $query->keyword;
        }

        return $query->filter($this->search($search));
    }

    public function event(string $id): Event
    {
        return $this->toEvent($this->get('/events/'.rawurlencode($id).'.json', [], 'event "'.$id.'"'));
    }

    /**
     * @param array<string, string> $search
     *
     * @return \Generator<int, Event>
     */
    private function search(array $search): \Generator
    {
        for ($page = 0; ($page + 1) * self::SIZE <= self::DEEPEST; ++$page) {
            $answer = $this->get('/events.json', $search + ['page' => (string) $page], 'the events');
            foreach ($answer->list('_embedded.events') as $event) {
                yield $this->toEvent($event);
            }
            if ($page + 1 >= ($answer->int('page.totalPages') ?? 0)) {
                return;
            }
        }
    }

    private function toEvent(Data $event): Event
    {
        $start = $event->date('dates.start.dateTime') ?? $event->date('dates.start.localDate', self::zone($event->string('dates.timezone')));
        $salesStart = $event->date('sales.public.startDateTime');
        $range = $event->list('priceRanges')[0] ?? new Data();
        $currency = $range->string('currency');
        $venue = $event->list('_embedded.venues')[0] ?? new Data();
        $place = implode(', ', array_filter([$venue->string('name'), $venue->string('city.name')]));

        return new Event(
            (string) $event->string('id'),
            $event->string('name') ?? '',
            $start,
            $event->date('dates.end.dateTime'),
            match ($event->string('dates.status.code')) {
                'onsale' => null !== $salesStart && $salesStart > new \DateTimeImmutable() ? EventStatus::Upcoming : EventStatus::OnSale,
                'offsale' => EventStatus::SalesClosed,
                'cancelled', 'canceled' => EventStatus::Cancelled,
                'postponed' => EventStatus::Postponed,
                'rescheduled' => EventStatus::OnSale,
                default => EventStatus::Unknown,
            },
            $event->string('url'),
            '' !== $place ? $place : null,
            priceFrom: null !== $currency && null !== ($min = $range->string('min')) ? Money::fromMajor($min, $currency) : null,
            priceTo: null !== $currency && null !== ($max = $range->string('max')) ? Money::fromMajor($max, $currency) : null,
            provider: 'ticketmaster',
        );
    }

    /** @param array<string, string> $query */
    private function get(string $path, array $query, string $what): Data
    {
        return $this->api->request('GET', $this->url.$path, ['query' => ['apikey' => $this->apiKey] + $query], $what);
    }

    private static function utc(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private static function zone(?string $name): ?\DateTimeZone
    {
        try {
            return null !== $name ? new \DateTimeZone($name) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
