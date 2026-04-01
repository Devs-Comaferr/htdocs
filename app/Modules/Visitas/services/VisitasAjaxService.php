<?php

function visitasJsonEncodeCustom($data)
{
    if (is_null($data)) {
        return 'null';
    }
    if ($data === false) {
        return 'false';
    }
    if ($data === true) {
        return 'true';
    }
    if (is_scalar($data)) {
        if (is_float($data)) {
            return floatval(str_replace(",", ".", strval($data)));
        }

        if (is_string($data)) {
            static $json_replaces = array("\\", "\"", "\n", "\t", "\r", "\b", "\f");
            static $json_escape = array('\\\\', '\\"', '\\n', '\\t', '\\r', '\\b', '\\f');
            return '"' . str_replace($json_replaces, $json_escape, $data) . '"';
        }

        return $data;
    }
    $isList = true;

    foreach ($data as $key => $value) {
        if (!is_numeric($key)) {
            $isList = false;
            break;
        }
    }

    $result = array();
    if ($isList) {
        foreach ($data as $value) {
            $result[] = visitasJsonEncodeCustom($value);
        }
        return '[' . join(',', $result) . ']';
    }

    foreach ($data as $key => $value) {
        $result[] = visitasJsonEncodeCustom(strval($key)) . ':' . visitasJsonEncodeCustom($value);
    }
    return '{' . join(',', $result) . '}';
}
