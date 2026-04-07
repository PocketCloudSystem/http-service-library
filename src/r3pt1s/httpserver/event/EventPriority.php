<?php

namespace r3pt1s\httpserver\event;

enum EventPriority: int {

    case LOW = 0;
    case MEDIUM = 1;
    case HIGH = 3;
}