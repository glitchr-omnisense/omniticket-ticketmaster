<?php

namespace Omniticket\Ticketmaster;

use Omniticket\Config;
use Omniticket\Exception\InvalidConfigException;
use Omniticket\ProviderFactory;
use Omniticket\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ticketmaster's Discovery API, from its options:
 *
 *   api_key        the consumer key of an app on developer.ticketmaster.com
 *   country_code   ISO 3166-1 alpha-2, every search narrowed to it ("FR")
 *   city, venue_id, attraction_id, classification_name, locale
 *                  the same: a site lists the events of its venue, its artist, its city
 *   url            https://app.ticketmaster.com/discovery/v2
 */
final class TicketmasterFactory extends ProviderFactory
{
    private const FILTERS = ['country_code' => 'countryCode', 'city' => 'city', 'venue_id' => 'venueId', 'attraction_id' => 'attractionId', 'classification_name' => 'classificationName', 'locale' => 'locale'];

    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omniticket.factory_name' => 'ticketmaster',
            'omniticket.factory_title' => 'Ticketmaster',
            'omniticket.required_options' => ['api_key'],
            'url' => 'https://app.ticketmaster.com/discovery/v2',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $filters = [];
        foreach (self::FILTERS as $option => $parameter) {
            if (null !== ($value = $c->string($option))) {
                $filters[$parameter] = $value;
            }
        }

        return new TicketmasterProvider(
            $this->http ?? throw new InvalidConfigException('The "ticketmaster" provider needs an HTTP client: give one to TicketmasterFactory.'),
            (string) $c->string('api_key'),
            $filters,
            rtrim($c->string('url') ?? 'https://app.ticketmaster.com/discovery/v2', '/'),
        );
    }
}
