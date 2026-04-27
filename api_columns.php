<?php
require 'helpers/gema_api_client.php';
$res = queryApiGema("TOP 1 * FROM gema10.d/salud/datos/glo_det");
if (!empty($res)) {
    echo "API glo_det columns:\n";
    print_r(array_keys($res[0]));
} else {
    echo "API returned empty for glo_det\n";
}
