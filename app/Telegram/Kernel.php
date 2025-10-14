<?php

namespace App\Telegram;

use App\Telegram\Handlers\ButtonHandler;
use App\Telegram\Handlers\StartCommand;
use WeStacks\TeleBot\Kernel as TeleBotKernel;

class Kernel extends TeleBotKernel
{
    public function __construct()
    {
        parent::__construct([
            //
            StartCommand::class,
            ButtonHandler::class
        ]);
    }
}
