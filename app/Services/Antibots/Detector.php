<?php

namespace App\Services\Antibots;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Services\Antibots\Rules\CloackerService;
use App\Services\Antibots\Rules\GuardianService;
use App\Services\Antibots\Rules\BlockerService;
use App\Services\Antibots\Rules\MobileDetect;

class Detector
{
    public $cloaker;
    public $guardian;
    public $blocker;
    public $mobileDetect;

    public function __construct(
        CloackerService $cloaker,
        GuardianService $guardian,
        BlockerService $blocker,
        MobileDetect $mobileDetect
    ) {
        $this->cloaker = $cloaker;
        $this->guardian = $guardian;
        $this->blocker = $blocker;
        $this->mobileDetect = $mobileDetect;
    }

    public function run(): ?RedirectResponse
    {
        $config = config('antibots.config');

        // cloacker country
        if ($config['comprobate_country'] === true) {
            $this->cloaker->run();
        }

        // GUARD
        if ($config['GUARD'] === true) {
            $this->guardian->run();
        }

        // blocker
        if ($config['blocker'] === true) {
            $this->blocker->run();
        }

        // mobile detect
        if ($config['mobile_detect'] === true) {
            if ($this->mobileDetect->isMobile()) {
                // Es móvil, continúa con la ejecución normal

            } else {
                // No es móvil, redirige
                return redirect($config['url']);
            }
        }

        return null;
    }
}
