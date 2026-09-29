<?php
class AdminController
{
    public static function page($file)
    {
        if ($file !== 'login.php') { requireAdmin(); }
        /* Keep the dashboard working on installs that were created by importing
           database.sql instead of running the CLI migrations (additive only). */
        require_once BASE_PATH . '/core/Schema.php';
        Schema::ensure();
        require BASE_PATH . '/admin/' . $file;
    }
}
