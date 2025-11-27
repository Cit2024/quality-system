<?php
// Authentication and session management
// analytics/shared/auth.php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
