<?php

namespace r3pt1s\httpserver\util\trait;

use r3pt1s\httpserver\event\EventPriority;

trait QuickEventListenersTrait {

    public function listen(string $eventClazz, callable $listener, EventPriority|int $priority = 0): void {
        $this->eventDispatcher->listen($eventClazz, $listener, $priority);
    }
}