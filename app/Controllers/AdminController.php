<?php
class AdminController
{
    public static function page($file)
    {
        if ($file !== 'login.php') { requireAdmin(); }
        require BASE_PATH . '/admin/' . $file;
    }
}
