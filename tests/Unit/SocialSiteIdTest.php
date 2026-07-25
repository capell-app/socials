<?php

declare(strict_types=1);

use Capell\Socials\Support\SocialSiteId;

it('accepts only strict positive integer site identifiers from admin state', function (mixed $value, ?int $expected): void {
    expect(SocialSiteId::fromInput($value))->toBe($expected);
})->with([
    'integer' => [12, 12],
    'integer string' => ['12', 12],
    'zero integer' => [0, null],
    'zero string' => ['0', null],
    'negative integer' => [-12, null],
    'negative string' => ['-12', null],
    'decimal number' => [12.5, null],
    'decimal string' => ['12.5', null],
    'scientific notation' => ['1e2', null],
    'leading whitespace' => [' 12', null],
    'trailing whitespace' => ['12 ', null],
    'boolean' => [true, null],
    'empty string' => ['', null],
    'missing value' => [null, null],
    'overflowing integer string' => [((string) PHP_INT_MAX) . '0', null],
]);
