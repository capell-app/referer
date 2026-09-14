<?php

declare(strict_types=1);

use Capell\Referer\Tests\RefererTestCase;

pest()->extend(RefererTestCase::class)->group('referer')->in('.');
