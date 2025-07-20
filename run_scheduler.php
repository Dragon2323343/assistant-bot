<?php

while (true) {
    echo "Running Laravel scheduler...\n";
    exec('php C:\\OpenServer\\domains\\assistant-bot\\artisan reminders:send');

    sleep(60);
}
