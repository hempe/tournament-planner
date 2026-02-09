
<?php

function getConnection(): mysqli
{
    return new mysqli(
        "localhost",
        "TP",
        "g0lf3lf4r0",
        "TPDb"
    );
}
