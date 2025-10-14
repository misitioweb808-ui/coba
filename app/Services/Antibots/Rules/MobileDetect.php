<?php

namespace App\Services\Antibots\Rules;

use Detection\MobileDetect as MobileDetectLib;

class MobileDetect
{
    private $detector;

    public function __construct()
    {
        $this->detector = new MobileDetectLib();
    }

    public function isMobile(): bool
    {

        $isMobile = $this->detector->isMobile();
        return $isMobile;
    }
    public function isTablet(): bool
    {
        $isTablet = $this->detector->isTablet();

        return $isTablet;
    }
}

