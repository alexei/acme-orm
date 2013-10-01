<?php

function _ip_address() {
    static $ip_address;

    if (!$ip_address) {
        $ip_address = getenv('REMOTE_ADDR');

        $fwd = getenv('HTTP_X_FORWARDED_FOR');
        if ($fwd) {
            $addr = explode(',', $fwd);
            $ip_address = $addr[count($addr) - 1];
        }
    }

    return $ip_address;
}

/**
 * @xxx (09.10.20) alexei
 * Original code: http://api.drupal.org/api/function/node_teaser/6
 * - this method should suffice for simple tasks, but it may split inside tags
 * thus it should be used together with close_tags()
 *
 * @todo
 * - break points should not be prioritized; one may split too far thus making the teaser too short
 * - the string should be split based on the plain text version
 */
function _teaser($body, $size = 600) {
    if ($size == 0) {
        return $body;
    }

    if (mb_strlen($body) <= $size) {
        return $body;
    }

    // initial slice
    $teaser = mb_substr($body, 0, $size);

    $max_rpos = strlen($teaser);

    $min_rpos = $max_rpos;

    // store a reversed version of $teaser for speed and convenience
    $reversed = strrev($teaser);

    // an array of arrays of break points grouped by preference
    $break_points = array();
    $break_points[] = array('</p>' => 0);
    $break_points[] = array('<br />' => 6, '<br>' => 4);
    $break_points[] = array('. ' => 1, '! ' => 1, '? ' => 1, ' ' => 1);

    // iterate over the groups of break points until a break point is found
    foreach ($break_points as $points) {
        foreach ($points as $point => $offset) {
            $rpos = strpos($reversed, strrev($point));
            if ($rpos !== FALSE) {
                $min_rpos = min( $rpos + $offset, $min_rpos );
            }
        }

        // if a break point was found in this group, slice and return the teaser
        if ($min_rpos !== $max_rpos) {
            return ($min_rpos === 0) ? $teaser : substr($teaser, 0, 0 - $min_rpos);
        }
    }

    return $teaser;
}

/**
 * @xxx (09.10.20) alexei
 * original code: http://snipplr.com/view/3618/close-tags-in-a-htmlsnippet/
 *
 * Changes:
 * - use an improved re for mathing tags
 * - make sure that empty tags follow the XML std so that they're not confused with open tags
 */
function _close_tags( $html = '' ) {
    $attr_re = '(?:\"[^\"]*\"|\'[^\']*\'|[^\"\'>])*';
    $empty_tags = array('br', 'hr', 'meta', 'link', 'base', 'img', 'embed', 'param', 'area', 'col', 'input');
    $html = preg_replace('/<('. implode( '|', $empty_tags ) .')('. $attr_re .')(?<!\/)>/', '<$1$2 />', $html);

    preg_match_all('/<(\w[\w-]*)'. $attr_re .'(?<!\/)>/', $html, $result);
    $open_tags = $result[1];

    preg_match_all('/<\/(\w[\w-]*)>/', $html, $result);
    $close_tags = $result[1];

    $len_opened = count($open_tags);
    if (count($close_tags) == $len_opened) {
        return $html;
    }
    $open_tags = array_reverse($open_tags);
    for ($i = 0; $i < $len_opened; $i++) {
        if (!in_array($open_tags[$i], $close_tags)) {
            $html .= "</" . $open_tags[$i] . ">";
        }
        else {
            unset($close_tags[array_search($open_tags[$i], $close_tags)]);
        }
    }

    return $html;
}

function _get_subclasses($super_class) {
    $class_list = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, $super_class)) {
            $class_list[] = $class;
        }
    }
    return $class_list;
}

/**
 * Kudos to jesdisciple at gmail dot com
 * http://www.php.net/manual/en/function.strtoupper.php#81662
 */
function _camelize($str, $glue = ' ') {
    $str = explode($glue, strtolower($str));
    for ($i = 1, $str_len = count($str); $i < $str_len; $i++) {
        $str[$i] = strtoupper(substr($str[$i], 0, 1)) . substr($str[$i], 1);
    }
    return implode('', $str);
}

/**
 * Kudos to kevin at metalaxe dot com
 * http://www.php.net/manual/en/function.str-split.php#81836
 */
function _uncamelize($str, $glue = ' ') {
    return preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', $glue .'$0', $str);
}
