<?php

namespace Omniticket\Ticketmaster\Tests;

use Omniticket\Exception\NotFoundException;
use Omniticket\Exception\NotSupportedException;
use Omniticket\Model\Event;
use Omniticket\Model\EventQuery;
use Omniticket\Registry;
use Omniticket\Testing\RecordedHttpClient;
use Omniticket\Ticketmaster\TicketmasterFactory;
use Omniticket\Ticketmaster\TicketmasterProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ticketmaster's answers are composed for these tests from the fields its
 * Discovery API v2 documents (events, dates, sales, priceRanges, venues,
 * page); they were not recorded with a key. Every identifier is an example.
 */
final class TicketmasterProviderTest extends TestCase
{
    public function testASearch(): void
    {
        $http = self::http();
        $events = [...self::provider($http, ['countryCode' => 'FR'])->events(new EventQuery(keyword: 'example', now: new \DateTimeImmutable('2026-10-24T00:00:00Z')))];

        self::assertSame([['vvG1zZ9KbqA0Ex', 'Example Band - World Tour', 'on_sale', 'Example Arena, Paris', '45.50', '89.00', '2099-06-20T18:00:00+00:00'], ['vvG1zZ9KbqA0Ey', 'Example Festival', 'postponed', 'Example Park, Lyon', null, null, '2099-07-04T00:00:00+02:00']], array_map(static fn (Event $e) => [$e->id, $e->title, $e->status->value, $e->venue, $e->priceFrom?->major(), $e->priceTo?->major(), $e->start?->format(\DATE_ATOM)], $events));
        self::assertSame(['apikey' => 'example-key-not-real', 'countryCode' => 'FR', 'size' => '50', 'sort' => 'date,asc', 'startDateTime' => '2026-10-24T00:00:00Z', 'keyword' => 'example', 'page' => '0'], $http->requests[0]['options']['query']);
        self::assertCount(1, $http->requests, 'one page');
    }

    public function testPagesStopAtTheDeepestResult(): void
    {
        $pages = [];
        $http = new RecordedHttpClient(static function (string $method, string $url, array $options) use (&$pages): array {
            $query = \is_array($options['query'] ?? null) ? $options['query'] : [];
            $page = is_numeric($query['page'] ?? null) ? (int) $query['page'] : 0;
            $pages[] = $page;

            return [200, (string) json_encode(['_embedded' => ['events' => array_map(static fn (int $i) => ['id' => 'e'.$i, 'name' => 'E', 'dates' => ['start' => ['dateTime' => '2099-01-01T00:00:00Z']]], range($page * 50, $page * 50 + 49))], 'page' => ['size' => 50, 'totalPages' => 100, 'number' => $page]])];
        });

        self::assertCount(1000, [...self::provider($http)->events()]);
        self::assertSame(range(0, 19), $pages, 'size x page < 1000');
    }

    public function testOneEventAndAnUnknownOne(): void
    {
        self::assertSame('Example Band - World Tour', self::provider(self::http())->event('vvG1zZ9KbqA0Ex')->title);
        $this->expectException(NotFoundException::class);
        self::provider(self::http())->event('nope');
    }

    public function testACatalogueOnly(): void
    {
        $registry = new Registry([new TicketmasterFactory(self::http())], ['tm' => ['factory' => 'ticketmaster', 'options' => ['api_key' => 'example-key-not-real', 'country_code' => 'FR', 'city' => '']]]);

        self::assertSame(['catalog'], $registry->get('tm')->capabilities()->contracts());
        [...$registry->catalog('tm')->events()];
        $this->expectException(NotSupportedException::class);
        $registry->attendees('tm');
    }

    /** @param array<string, string> $filters */
    private static function provider(RecordedHttpClient $http, array $filters = []): TicketmasterProvider
    {
        return new TicketmasterProvider($http, 'example-key-not-real', $filters);
    }

    private static function http(): RecordedHttpClient
    {
        return RecordedHttpClient::routes([
            'GET /events.json' => (string) file_get_contents(__DIR__.'/Fixtures/events.json'),
            'GET /events/vvG1zZ9KbqA0Ex.json' => (string) file_get_contents(__DIR__.'/Fixtures/event.json'),
            'GET /events/nope.json' => [404, '{"errors":[{"code":"DIS1004","detail":"Resource not found with provided criteria (locale=en-us, id=nope)","status":"404"}]}'],
        ]);
    }
}
