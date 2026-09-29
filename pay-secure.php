<?php
/* Legacy secure-link URLs no longer process payments. */
require_once __DIR__ . '/includes/init.php';
header('Location: ' . url('pay-online'), true, 302);
exit;
