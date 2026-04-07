<?php

namespace r3pt1s\httpserver\util;

final class HttpConstants {

    public const int MAX_REQUEST_SIZE = (1024 * 1024) * 10;
    public const int CHUNK_SIZE = 8192;
    public const int MAX_HEADERS = 100;
}