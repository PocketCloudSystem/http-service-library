<?php

namespace r3pt1s\httpserver\event;

final class EventDispatcher {

    private array $listeners = [];

    public function listen(string $eventClazz, callable $listener, EventPriority|int $priority = 0): void {
        $priority = $priority instanceof EventPriority ? $priority->value : $priority;
        $this->listeners[$eventClazz][$priority][] = $listener;
    }

    public function dispatch(Event $event): void {
        $eventClass = $event::class;
        if (!isset($this->listeners[$eventClass])) return;
        krsort($this->listeners[$eventClass]);

        foreach ($this->listeners[$eventClass] as $listeners) {
            foreach ($listeners as $listener) {
                $listener($event);
                if ($event->isCancelable() && $event->isCanceled()) return;
            }
        }
    }
}