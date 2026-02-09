
<?php

function getConnection(): mysqli
{
    return new mysqli(
        "localhost",
        "golfelfaro",
        "g0lf3lf4r0",
        "golfelfaroDb"
    );
}
