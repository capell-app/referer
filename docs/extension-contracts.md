# Extension contracts

Resolve and record a bounded source through typed package actions:

```php
<?php

declare(strict_types=1);

use Capell\Referer\Actions\RecordRefererCountAction;
use Capell\Referer\Actions\ResolveReferralSourceAction;

$source = ResolveReferralSourceAction::run($request, $site);

if ($source !== null) {
    RecordRefererCountAction::run($site, $source);
}
```

Callers must pass the already-resolved frontend `Site`; the recording action
does not resolve a site, enqueue request data, or write raw referral values.
