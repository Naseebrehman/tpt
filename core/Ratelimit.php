<?php
/** Fixed windows in a locked local file: works on shared hosting without Redis. */
class Ratelimit
{
    public static function allow($key, $limit, $seconds, $directory = null)
    {
        $directory = $directory ?: BASE_PATH . '/storage/runtime';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true)) { return false; }
        // One bounded file, not one file per attacker-controlled identity.
        $fp = @fopen($directory . '/ratelimits.json', 'c+');
        if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) { fclose($fp); } return false; }
        $entries = json_decode(stream_get_contents($fp), true) ?: array();
        $now = time();
        foreach ($entries as $id => $entry) { if ($entry[1] <= $now) { unset($entries[$id]); } }
        $id = hash('sha256', $key);
        if (!isset($entries[$id])) {
            if (count($entries) >= 10000) { flock($fp, LOCK_UN); fclose($fp); return false; }
            $entries[$id] = array(0, $now + $seconds);
        }
        $allowed = $entries[$id][0] < $limit;
        if ($allowed) { $entries[$id][0]++; }
        rewind($fp); ftruncate($fp, 0); fwrite($fp, json_encode($entries)); fflush($fp);
        flock($fp, LOCK_UN); fclose($fp);
        return $allowed;
    }
}
