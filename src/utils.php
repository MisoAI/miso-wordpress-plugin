<?php

namespace Miso\Utils;

function format_date($date) {
    return $date ? date_create_immutable($date, timezone_open('UTC'))->format('Y-m-d\TH:i:s\Z') : null;
}
