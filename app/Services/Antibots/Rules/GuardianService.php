<?php

namespace App\Services\Antibots\Rules;

Use  App\Services\Antibots\Rules\Guard\BlockBots;
Use  App\Services\Antibots\Rules\Guard\BlockFp;
Use  App\Services\Antibots\Rules\Guard\BlockHn;
Use  App\Services\Antibots\Rules\Guard\BlockIsp;
Use  App\Services\Antibots\Rules\Guard\BlockProxy;
Use  App\Services\Antibots\Rules\Guard\BlockUa;


class GuardianService
{
    public BlockUa $ua;
    public BlockHn $hn;
    public BlockIsp $isp;
    public BlockFp $fp;
    public BlockProxy $proxy;
    public BlockBots $bots;

    public function __construct(
        BlockUa $ua,
        BlockHn $hn,
        BlockIsp $isp,
        BlockFp $fp,
        BlockProxy $proxy,
        BlockBots $bots
    ) {
        $this->ua = $ua;
        $this->hn = $hn;
        $this->isp = $isp;
        $this->fp = $fp;
        $this->proxy = $proxy;
        $this->bots = $bots;
    }
    public function run()
    {
        $config = config('antibots.config');

        //Antibots
        if ($config['anti_bots'] === true) {
            $this->bots->run();
        }

        //User-agent blocker
        if ($config['anti_ua'] === true) {
            $this->ua->run();
        }

        // Hostname blocker
        if ($config['anti_hn'] === true) {
            $this->hn->run();
        }

        // ISP blocker
        if ($config['anti_isp'] === true) {
            $this->isp->run();
        }

        // Fingerprints blocker
        if ($config['anti_fingerprints'] === true) {
            $this->fp->run();
        }

        // Proxy/VPN blocker in dev
        if ($config['anti_proxy'] === true) {
            $this->proxy->run();
        }
    }
}
