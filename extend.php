<?php

namespace Acpl\FlarumDbSnapshots;

use Acpl\FlarumDbSnapshots\Commands\{Create, Load};
use Flarum\Extend;

return [
    (new Extend\Console())->command(Create::class),
    (new Extend\Console())->command(Load::class),
];
