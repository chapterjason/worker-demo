<?php

namespace App\Message;

final class SleepMessage
{
    public function __construct(
        public readonly int $seconds = 2,
    )
    {
    }
}
