<?php

// REQ-014: Surface list intersection matrix (SURF-001). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface agent not effective when cap_surfaces=agent,mcp,http,cli global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $effective = BootHelpers::effective(['agent', 'mcp', 'http', 'cli'], $global);
    expect($effective)->not->toContain('agent');
});

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface mcp not effective when cap_surfaces=agent,mcp,http,cli global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $effective = BootHelpers::effective(['agent', 'mcp', 'http', 'cli'], $global);
    expect($effective)->not->toContain('mcp');
});

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface http not effective when cap_surfaces=agent,mcp,http,cli global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $effective = BootHelpers::effective(['agent', 'mcp', 'http', 'cli'], $global);
    expect($effective)->not->toContain('http');
});

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface cli not effective when cap_surfaces=agent,mcp,http,cli global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $effective = BootHelpers::effective(['agent', 'mcp', 'http', 'cli'], $global);
    expect($effective)->not->toContain('cli');
});

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,mcp,http,cli global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['agent', 'mcp', 'http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface http not effective when cap_surfaces=http,cli global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $effective = BootHelpers::effective(['http', 'cli'], $global);
    expect($effective)->not->toContain('http');
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface cli not effective when cap_surfaces=http,cli global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $effective = BootHelpers::effective(['http', 'cli'], $global);
    expect($effective)->not->toContain('cli');
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=http,cli global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['http', 'cli'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=job global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=job global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=job global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=job global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=job global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface job not effective when cap_surfaces=job global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $effective = BootHelpers::effective(['job'], $global);
    expect($effective)->not->toContain('job');
});

it('edge: intersection computed when cap_surfaces=job global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=job global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface agent not effective when cap_surfaces=agent global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $effective = BootHelpers::effective(['agent'], $global);
    expect($effective)->not->toContain('agent');
});

it('edge: intersection computed when cap_surfaces=agent global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['agent'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=mcp global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=mcp global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface mcp not effective when cap_surfaces=mcp global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $effective = BootHelpers::effective(['mcp'], $global);
    expect($effective)->not->toContain('mcp');
});

it('edge: intersection computed when cap_surfaces=mcp global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=mcp global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=mcp global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=mcp global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=mcp global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['mcp'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=artisan global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=artisan global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=artisan global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=artisan global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=artisan global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=artisan global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface artisan not effective when cap_surfaces=artisan global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $effective = BootHelpers::effective(['artisan'], $global);
    expect($effective)->not->toContain('artisan');
});

it('edge: intersection computed when cap_surfaces=artisan global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['artisan'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'agent') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface agent not effective when cap_surfaces=agent,job global_off=agent [SURF-001]', function () {
    $global = BootHelpers::globalMap(['agent' => false]);
    $effective = BootHelpers::effective(['agent', 'job'], $global);
    expect($effective)->not->toContain('agent');
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=mcp [SURF-001]', function () {
    $global = BootHelpers::globalMap(['mcp' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'mcp') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=http [SURF-001]', function () {
    $global = BootHelpers::globalMap(['http' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'http') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=cli [SURF-001]', function () {
    $global = BootHelpers::globalMap(['cli' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'cli') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'job') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('fail: surface job not effective when cap_surfaces=agent,job global_off=job [SURF-001]', function () {
    $global = BootHelpers::globalMap(['job' => false]);
    $effective = BootHelpers::effective(['agent', 'job'], $global);
    expect($effective)->not->toContain('job');
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=artisan [SURF-001]', function () {
    $global = BootHelpers::globalMap(['artisan' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'artisan') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});

it('edge: intersection computed when cap_surfaces=agent,job global_off=messaging [SURF-001]', function () {
    $global = BootHelpers::globalMap(['messaging' => false]);
    $caps = ['agent', 'job'];
    $effective = BootHelpers::effective($caps, $global);
    foreach ($caps as $s) {
        if ($s === 'messaging') {
            expect($effective)->not->toContain($s);
        } else {
            // only assert presence when global still on for that surface
            if (($global[$s] ?? false) === true) {
                expect($effective)->toContain($s);
            }
        }
    }
    expect($effective)->toBeArray();
});
