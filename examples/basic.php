<?php

declare(strict_types=1);

use Rasuvaeff\Duration\Duration;

require dirname(__DIR__) . '/vendor/autoload.php';

$timeout = Duration::seconds(2.5);

printf("seconds: %s\n", $timeout->toSeconds());
printf("millis:  %d\n", $timeout->toMillis());
printf("micros:  %d\n", $timeout->toMicros());

$total = Duration::millis(500)->plus(Duration::seconds(1));
printf("500ms + 1s = %dms\n", $total->toMillis());

printf("sub-millisecond rounds up: %dms\n", Duration::micros(500)->toMillis());

printf(
    "1s greater than 500ms: %s\n",
    Duration::seconds(1)->isGreaterThan(Duration::millis(500)) ? 'yes' : 'no',
);

$lease = Duration::hours(2);
printf("lease: %s = %d minutes\n", $lease, (int) $lease->toMinutes());

$cap = Duration::max(Duration::seconds(5), Duration::seconds(1));
printf("clamp to larger: %s\n", $cap);

$effective = Duration::min($lease, Duration::minutes(30));
printf("lease capped at 30min: %s (positive: %s)\n", $effective, $effective->isPositive() ? 'yes' : 'no');

printf("stringable: %s, %s, %s\n", Duration::seconds(2.5), Duration::micros(500), Duration::zero());
