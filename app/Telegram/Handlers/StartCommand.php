<?php

namespace App\Telegram\Handlers;
use WeStacks\TeleBot\Foundation\CommandHandler;

class StartCommand extends CommandHandler
{
   protected static function aliases(): array
    {
        return ['/start'];
    }

    protected static function description(?string $locale = null): string
    {
        return trans('Start command', locale: $locale);
    }

    public function handle()
    {
        return $this->sendMessage([
            'text' => 'Hello, World!'
        ]);
    }
}
