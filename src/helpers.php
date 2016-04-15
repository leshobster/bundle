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
function sysError($db, $logger = null, array $params = []) {
    $debug = debug_backtrace();
    $description = '';
    foreach ($debug as $key => $value) {
        $array = array();
        $array[0] = isset($value['line']) ? $value['line'] : 0;
        $array[1] = isset($value['file']) ? $value['file'] : 'na';
        $array[2] = isset($value['class']) ? $value['class'] . $value['type'] . $value['function'] : ($value['function'] ?? '');
        $array[2] .= isset($value['args']) ? '(' . json_encode($value['args']) . ')' : '';
        $array[3] = PHP_EOL;
        $description .= implode(':', $array);
    }
    $log = [
        'uri' => $params['path'] ?? 'na',
        'post' => !empty($_POST) ? json_encode($_POST) : '',
        'get' => !empty($_GET) ? json_encode($_GET) : '',
        'description' => $description,
        'date' => time(),
        'ip' => ip(),
        'uid' => $params['uid'] ?? 0,
        'level' => $params['level'] ?? E_ERROR
    ];
    if (is_object($db) && $db instanceof \Huskee\Bundle\Db) {
        if (isset($params['info']))
            $log['description'] = $params['info'] . PHP_EOL . PHP_EOL . " --- " . PHP_EOL . PHP_EOL . $log['description'];
        $db->insert('sys_error', $log);
    } else if (is_object($logger) && method_exists($logger, 'addError'))
        $logger->error($params['info'] ?? '', $log);
}