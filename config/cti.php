<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-lead minimum call duration
    |--------------------------------------------------------------------------
    |
    | An unmatched (unknown) caller only becomes a "lead" customer when the call
    | was answered AND lasted at least this many seconds AND the outcome is not
    | "wrong_number". This guards the customers table against robocall / misdial
    | pollution — real conversations rarely last under this threshold.
    |
    */

    'lead_min_duration_sec' => (int) env('CTI_LEAD_MIN_DURATION_SEC', 10),

];
