<?php
session_start();
session_unset();
session_destroy();

header('Location: Giyu.html');
exit;
