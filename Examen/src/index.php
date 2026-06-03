<?php

header('Location: ' . (isLoggedIn() ? dashboardUrlForRole() : src_url('homepage.php')));
exit;
