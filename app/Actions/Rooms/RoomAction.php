<?php

namespace App\Actions\Rooms;

use Illuminate\Validation\ValidationException;

abstract class RoomAction
{
    /**
     * Stop with a user-facing message attached to a form field / error key.
     *
     * @throws ValidationException
     */
    protected function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
