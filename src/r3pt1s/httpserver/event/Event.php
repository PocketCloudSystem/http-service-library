<?php

namespace r3pt1s\httpserver\event;

use RuntimeException;

abstract class Event {

    protected bool $canceled = false;

    public function cancel(): void {
        if (!$this->isCancelable()) throw new RuntimeException($this::class . " is not cancelable");
        $this->canceled = true;
    }

    public function uncancel(): void {
        if (!$this->isCancelable()) throw new RuntimeException($this::class . " is not cancelable");
        $this->canceled = false;
    }

    public function isCancelable(): bool {
        return $this instanceof CancelableEvent;
    }

    public function isCanceled(): bool {
        return $this->canceled;
    }
}