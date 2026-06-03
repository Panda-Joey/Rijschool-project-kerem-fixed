<?php

/**
 * Uitloggen — http://localhost:8888/src/logout.php
 */
logout();

header('Location: ' . src_url('login.php'));
exit;
