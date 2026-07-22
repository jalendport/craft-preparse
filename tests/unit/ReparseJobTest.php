<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use craft\elements\Entry;
use jalendport\preparse\jobs\ReparseElements;

/**
 * Unit coverage for the reparse job's configuration and field filter.
 *
 * Running the job needs a booted Craft application — an element query, the
 * queue, and the values service all reach for one — so what's covered here is
 * the part that decides *what* gets reparsed. The filter is the piece worth
 * pinning down: getting it wrong would silently reparse every field on an
 * element when the operator asked for one.
 */

it('defaults to the lightweight patch mode, not full saves', function() {
    $job = reparseJob();

    expect($job->fullSave)->toBeFalse()
        ->and($job->force)->toBeFalse()
        ->and($job->fieldHandles)->toBe([])
        ->and($job->siteIds)->toBeNull();
});

it('defaults to entries', function() {
    expect(reparseJob()->elementType)->toBe(Entry::class);
});

it('passes every preparse field through when no handles are given', function() {
    $filter = (new ReflectionMethod(ReparseElements::class, '_filter'))
        ->invoke(reparseJob());

    expect($filter)->toBeNull();
});

it('filters to the requested field handles', function() {
    $job = reparseJob(['fieldHandles' => ['readingTime', 'wordCount']]);
    $filter = (new ReflectionMethod(ReparseElements::class, '_filter'))->invoke($job);

    expect($filter(preparseField(['handle' => 'readingTime'])))->toBeTrue()
        ->and($filter(preparseField(['handle' => 'wordCount'])))->toBeTrue()
        ->and($filter(preparseField(['handle' => 'somethingElse'])))->toBeFalse();
});

it('keeps a sync threshold low enough to stay inside a web request', function() {
    expect(ReparseElements::SYNC_THRESHOLD)->toBeGreaterThan(0)
        ->and(ReparseElements::SYNC_THRESHOLD)->toBeLessThanOrEqual(100);
});
