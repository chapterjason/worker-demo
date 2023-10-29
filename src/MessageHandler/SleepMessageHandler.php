<?php

namespace App\MessageHandler;

use App\Message\SleepMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SleepMessageHandler
{
    public function __invoke(SleepMessage $message)
    {
        sleep($message->seconds);
    }
}
