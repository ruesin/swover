<?php

namespace Swover\Contracts\Events;

interface Request extends Event
{
    /**
     * The event-type for bounds
     */
    const EVENT_TYPE = self::REQUEST;

    /**
     * Triggering request or receive event
     * @param \Swoole\Http\Request|array $request request data
     * @return mixed
     */
    public function trigger($request);
}