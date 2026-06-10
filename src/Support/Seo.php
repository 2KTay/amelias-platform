<?php

declare(strict_types=1);

namespace Amelias\Support;

/**
 * Structured-data (JSON-LD) builders. Emits valid schema.org Restaurant + Menu
 * markup so the site keeps its local-search position through cutover (Task 7.1).
 */
final class Seo
{
    /** Restaurant JSON-LD for the home/location pages. */
    public static function restaurant(array $opts = []): string
    {
        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Restaurant',
            'name'        => config('name'),
            'url'         => config('url'),
            'servesCuisine' => $opts['cuisine'] ?? 'American, Farm-to-table',
            'priceRange'  => $opts['priceRange'] ?? '$$',
            'image'       => $opts['image'] ?? (config('url') . '/assets/brand/AmeliasbyEat-sublogo.png'),
            'acceptsReservations' => 'https://www.ameliasaz.com/reserve',
        ];
        if (!empty($opts['address'])) {
            $data['address'] = ['@type' => 'PostalAddress'] + $opts['address'];
        }
        if (!empty($opts['telephone'])) {
            $data['telephone'] = $opts['telephone'];
        }
        if (!empty($opts['hours'])) {
            $data['openingHours'] = $opts['hours'];
        }
        return self::encode($data);
    }

    /** Menu JSON-LD from sections => [ [name, description, price_cents], ... ]. */
    public static function menu(array $sections): string
    {
        $menuSections = [];
        foreach ($sections as $sectionName => $items) {
            $menuItems = [];
            foreach ($items as $item) {
                $menuItems[] = array_filter([
                    '@type'       => 'MenuItem',
                    'name'        => $item['name'] ?? '',
                    'description' => $item['description'] ?? null,
                    'offers'      => isset($item['price_cents']) ? [
                        '@type'         => 'Offer',
                        'price'         => number_format($item['price_cents'] / 100, 2, '.', ''),
                        'priceCurrency' => 'USD',
                    ] : null,
                ], static fn ($v) => $v !== null);
            }
            $menuSections[] = [
                '@type'           => 'MenuSection',
                'name'            => (string) $sectionName,
                'hasMenuItem'     => $menuItems,
            ];
        }
        return self::encode([
            '@context'    => 'https://schema.org',
            '@type'       => 'Menu',
            'hasMenuSection' => $menuSections,
        ]);
    }

    private static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
