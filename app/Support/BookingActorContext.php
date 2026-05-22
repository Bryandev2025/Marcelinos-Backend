<?php

namespace App\Support;

use App\Models\User;

final class BookingActorContext
{
    private static ?User $actor = null;

    /**
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public static function run(?User $actor, callable $callback): mixed
    {
        $previousActor = self::$actor;
        self::$actor = $actor;

        try {
            return $callback();
        } finally {
            self::$actor = $previousActor;
        }
    }

    public static function current(): ?User
    {
        return self::$actor;
    }
}