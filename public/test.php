<?php

try {
    $result = file_get_contents('https://businessvideos.website/?q=ok');
    echo $result;
} catch (\Throwable $th) {
    echo $th->getMessage();
    return false;
}
