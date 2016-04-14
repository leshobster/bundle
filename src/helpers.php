<?php
declare(strict_types = 1);
function dump($a) {
    echo '<pre>';print_r($a);echo '</pre>';
}
function ip() : string {
    foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR') as $value)
        if (isset($_SERVER[$value]))
            return $_SERVER[$value];
    return '0';
}
function sysError(\Huskee\Bundle\Db $db, array $params = []) {
    $description = $params['info'] ?? $params['info'] . PHP_EOL . PHP_EOL . " --- " . PHP_EOL . PHP_EOL;
    $debug = debug_backtrace();

    foreach ($debug as $key => $value) {
        $array = array();
        $array[0] = isset($value['line']) ? $value['line'] : 0;
        $array[1] = isset($value['file']) ? $value['file'] : 'na';
        $array[2] = isset($value['class']) ? $value['class'] . $value['type'] . $value['function'] : ($value['function'] ?? '');
        $array[2] .= isset($value['args']) ? '(' . json_encode($value['args']) . ')' : '';
        $array[3] = PHP_EOL;
        $description .= implode(':', $array);
    }

    $db->insert('sys_error', array(
        'uri' => $params['path'] ?? 'na',
        'post' => !empty($_POST) ? json_encode($_POST) : '',
        'get' => !empty($_GET) ? json_encode($_GET) : '',
        'description' => $description,
        'date' => time(),
        'ip' => ip(),
        'uid' => $params['uid'] ?? 0,
        'level' => $params['level'] ?? E_ERROR
    ));
}